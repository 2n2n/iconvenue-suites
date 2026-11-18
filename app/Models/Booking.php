<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'booking_reference', 'venue_id', 'package_id', 'staff_id', 'client_name', 'client_email',
        'client_phone', 'booking_date', 'number_of_days', 'end_date', 'time_slots', 'total_amount',
        'discount_amount', 'discount_percentage', 'discount_reason', 'original_amount',
        'status', 'payment_status', 'notes', 'reminder_sent_at',
        'booking_platform_id', 'rate_per_night', 'platform_reference',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'end_date' => 'date',
        'time_slots' => 'array',
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'rate_per_night' => 'decimal:2',
        'reminder_sent_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($booking) {
            if (empty($booking->booking_reference)) {
                $booking->booking_reference = self::generateBookingReference();
            }
        });
    }

    public static function generateBookingReference()
    {
        do {
            // Format: IVS-YYYY-XXXX (Icon Venue & Suites - Year - Random)
            $year = date('Y');
            $random = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 4));
            $reference = "IVS-{$year}-{$random}";
        } while (self::where('booking_reference', $reference)->exists());

        return $reference;
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(VenuePackage::class, 'package_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /** The booking platform whose rate was used. Null means the regular rate. */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(BookingPlatform::class, 'booking_platform_id');
    }

    /** Client email is optional; without it no client emails are sent. */
    public function hasClientEmail(): bool
    {
        return trim((string) $this->client_email) !== '';
    }

    /** Clients page key: the email, or "tel-<phone>" for clients without an email. */
    public function getClientKeyAttribute(): string
    {
        return $this->hasClientEmail() ? (string) $this->client_email : 'tel-'.$this->client_phone;
    }

    /** "Regular" or the platform name, for views and reports. */
    public function getRateSourceLabelAttribute(): string
    {
        return $this->platform?->name ?? 'Regular';
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function addons()
    {
        return $this->belongsToMany(VenueAddon::class, 'booking_addons')
            ->withPivot('quantity', 'price_at_booking')
            ->withTimestamps();
    }

    public function getAddonsTotal()
    {
        return $this->addons->sum(function ($addon) {
            return $addon->pivot->quantity * $addon->pivot->price_at_booking;
        });
    }

    /**
     * Calculate and apply discount to the booking
     */
    public function applyDiscount($discountAmount = null, $discountPercentage = null, $reason = null)
    {
        // Store original amount if not already set
        if (! $this->original_amount) {
            $this->original_amount = $this->total_amount;
        }

        if ($discountAmount) {
            // Fixed amount discount
            $this->discount_amount = min($discountAmount, $this->original_amount);
            $this->discount_percentage = ($this->discount_amount / $this->original_amount) * 100;
        } elseif ($discountPercentage) {
            // Percentage discount
            $this->discount_percentage = min($discountPercentage, 100);
            $this->discount_amount = ($this->original_amount * $this->discount_percentage) / 100;
        }

        $this->discount_reason = $reason;
        $this->total_amount = $this->original_amount - $this->discount_amount;

        return $this;
    }

    /**
     * Get the final amount after discount
     */
    public function getFinalAmount()
    {
        return $this->total_amount;
    }

    /**
     * Get the discount amount
     */
    public function getDiscountAmount()
    {
        return $this->discount_amount ?? 0;
    }

    /**
     * Get the original amount before discount
     */
    public function getOriginalAmount()
    {
        return $this->original_amount ?? $this->total_amount;
    }

    /**
     * Check if booking has discount applied
     */
    public function hasDiscount()
    {
        return $this->discount_amount > 0;
    }

    /**
     * Format a single time slot with its time range.
     */
    public static function formatTimeSlotLabel(string $slot): string
    {
        return match (strtolower(trim($slot))) {
            'morning' => 'Morning (8AM-12PM)',
            'afternoon' => 'Afternoon (1PM-5PM)',
            'evening' => 'Evening (6PM-10PM)',
            default => ucfirst(trim($slot)),
        };
    }

    /**
     * Get formatted time slots display
     */
    public function getTimeSlotsDisplay()
    {
        $slots = $this->getTimeSlots();

        if (empty($slots)) {
            return 'Full Day';
        }

        return implode(' + ', array_map(
            fn ($slot) => self::formatTimeSlotLabel($slot),
            $slots
        ));
    }

    /**
     * Time slot label for reports and exports.
     */
    public function getReportTimeSlotDisplay(): string
    {
        if ($this->venue && $this->venue->type === 'suite') {
            return 'Suite (22 hours)';
        }

        return $this->getTimeSlotsDisplay();
    }

    /**
     * Check if booking has specific time slot
     */
    public function hasTimeSlot($slot)
    {
        return in_array(strtolower(trim((string) $slot)), $this->getTimeSlots(), true);
    }

    /**
     * Get time slots as array
     */
    public function getTimeSlots()
    {
        $slots = $this->time_slots ?? [];

        if (is_string($slots)) {
            $slots = [$slots];
        }

        return array_values(array_filter(array_map(
            fn ($slot) => strtolower(trim((string) $slot)),
            is_array($slots) ? $slots : []
        )));
    }

    /**
     * Check if booking is full day (no specific time slots)
     */
    public function isFullDay()
    {
        return ! $this->time_slots || empty($this->time_slots);
    }
}
