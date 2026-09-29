<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VenuePackage extends Model
{
    protected $fillable = [
        'venue_id',
        'name',
        'description',
        'price',
        'price_morning',
        'price_afternoon',
        'price_evening',
        'has_time_based_pricing',
        'inclusions',
        'is_active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_morning' => 'decimal:2',
        'price_afternoon' => 'decimal:2',
        'price_evening' => 'decimal:2',
        'has_time_based_pricing' => 'boolean',
        'inclusions' => 'array',
        'is_active' => 'boolean'
    ];

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'package_id');
    }

    /**
     * Add-ons included free with this package (as opposed to paid extras
     * selected on a booking).
     */
    public function addons()
    {
        return $this->belongsToMany(VenueAddon::class, 'package_addon_inclusions', 'venue_package_id', 'venue_addon_id')
                    ->withTimestamps();
    }

    /**
     * Combined display list: catalog add-on names + free-text inclusions.
     */
    public function getInclusionLabelsAttribute(): array
    {
        return array_merge($this->addons->pluck('name')->all(), $this->inclusions ?? []);
    }

    /**
     * Get the price for a specific time slot
     */
    public function getPriceForTimeSlot($timeSlot = null)
    {
        if (!$this->has_time_based_pricing || !$timeSlot) {
            return $this->price;
        }

        switch ($timeSlot) {
            case 'morning':
                return $this->price_morning ?? $this->price;
            case 'afternoon':
                return $this->price_afternoon ?? $this->price;
            case 'evening':
                return $this->price_evening ?? $this->price;
            default:
                return $this->price;
        }
    }

    /**
     * Check if package has time-based pricing configured
     */
    public function hasTimeBasedPricing()
    {
        return $this->has_time_based_pricing && 
               ($this->price_morning || $this->price_afternoon || $this->price_evening);
    }

    /**
     * Get available time slots for this package
     */
    public function getAvailableTimeSlots()
    {
        if (!$this->has_time_based_pricing) {
            return ['full-day'];
        }

        $slots = [];
        if ($this->price_morning) $slots[] = 'morning';
        if ($this->price_afternoon) $slots[] = 'afternoon';
        if ($this->price_evening) $slots[] = 'evening';
        
        // Always include full-day option
        $slots[] = 'full-day';
        
        return $slots;
    }
}
