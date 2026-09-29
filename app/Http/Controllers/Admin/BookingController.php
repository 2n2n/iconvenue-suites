<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Venue;
use App\Mail\BookingStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::with(['venue', 'package', 'staff']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('booking_reference', 'like', "%{$search}%")
                  ->orWhere('client_name', 'like', "%{$search}%")
                  ->orWhere('client_email', 'like', "%{$search}%")
                  ->orWhere('client_phone', 'like', "%{$search}%");
            });
        }

        $bookings = $query->latest()->paginate(15);
        return view('admin.bookings.index', compact('bookings'));
    }

    public function create(Request $request)
    {
        if (!$request->filled('venue_id') || !$request->filled('booking_date')) {
            return view('admin.bookings.calendar', [
                'isBookingEntry' => true,
                'highlightVenueId' => $request->get('venue_id'),
            ]);
        }

        $venues = Venue::where('is_active', true)->with('activePackages')->get();
        $addons = \App\Models\VenueAddon::active()->orderBy('category')->orderBy('sort_order')->get()->groupBy('category');
        $selectedVenueId = $request->get('venue_id');

        $venuePackages = $venues->mapWithKeys(function ($venue) {
            return [$venue->id => $venue->activePackages->map(function ($package) {
                return [
                    'id' => $package->id,
                    'name' => $package->name,
                    'price' => $package->price,
                    'price_morning' => $package->price_morning,
                    'price_afternoon' => $package->price_afternoon,
                    'price_evening' => $package->price_evening,
                    'has_time_based_pricing' => $package->has_time_based_pricing,
                ];
            })];
        });

        return view('admin.bookings.create', compact('venues', 'addons', 'selectedVenueId', 'venuePackages'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'venue_id' => 'required|exists:venues_and_suites,id',
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email',
            'client_phone' => 'required|string',
            'booking_date' => 'required|date|after_or_equal:today',
            'number_of_days' => 'nullable|integer|min:1|max:365',
            'time_slot_type' => 'nullable|in:full_day,multiple',
            'time_slots' => 'nullable|array',
            'time_slots.*' => 'in:morning,afternoon,evening',
            'notes' => 'nullable|string',
            'addons' => 'nullable|array',
            'addons.*' => 'exists:venue_addons,id',
            'addon_quantities' => 'nullable|array',
            'addon_quantities.*' => 'integer|min:1|max:99',
            // Discount fields
            'discount_type' => 'nullable|in:amount,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string|max:255'
        ], [
            'booking_date.after_or_equal' => 'Cannot book past dates. Please select today or a future date.'
        ]);

        // Additional check for past dates
        $bookingDate = \Carbon\Carbon::parse($validated['booking_date']);
        if ($bookingDate->isPast() && !$bookingDate->isToday()) {
            return back()->withErrors([
                'booking_date' => 'Cannot book past dates. The selected date has already passed. Please choose today or a future date.'
            ])->withInput();
        }

        $venue = Venue::findOrFail($validated['venue_id']);

        // If booking is for TODAY, check that selected time slots haven't already started (venues only)
        // Suites allow same-day walk-in bookings as long as the suite is not already booked.
        if ($bookingDate->isToday() && $venue->type !== 'suite') {
            $now = \Carbon\Carbon::now();
            $currentHour = $now->hour;

            // Time slot START hours — a slot cannot be booked once it has started
            $slotStartHours = [
                'morning'   =>  8, // starts 8:00 AM
                'afternoon' => 13, // starts 1:00 PM
                'evening'   => 18, // starts 6:00 PM
            ];

            $isFullDay = empty($validated['time_slots']) || ($request->time_slot_type === 'full_day');

            if ($isFullDay) {
                // Full day: cannot book if the morning slot has already started
                if ($currentHour >= 8) {
                    return back()->withErrors([
                        'booking_date' => 'Cannot create a full-day booking for today — the morning slot has already started (8:00 AM).'
                    ])->withInput();
                }
            } else {
                // Check each selected slot
                $expiredSlots = [];
                foreach ($validated['time_slots'] as $slot) {
                    if (isset($slotStartHours[$slot]) && $currentHour >= $slotStartHours[$slot]) {
                        $expiredSlots[] = ucfirst($slot);
                    }
                }

                if (!empty($expiredSlots)) {
                    $slotLabels = [
                        'Morning'   => '8:00 AM – 12:00 PM',
                        'Afternoon' => '1:00 PM – 5:00 PM',
                        'Evening'   => '6:00 PM – 10:00 PM',
                    ];
                    $details = implode(', ', array_map(
                        fn($s) => "{$s} ({$slotLabels[$s]})",
                        $expiredSlots
                    ));
                    return back()->withErrors([
                        'time_slots' => "The following time slot(s) have already started or passed for today: {$details}. Please select a future time slot or choose a different date."
                    ])->withInput();
                }
            }
        }

        // Validate time slot combination — morning+evening without afternoon is not allowed
        if (!empty($validated['time_slots']) && count($validated['time_slots']) === 2) {
            $slots = $validated['time_slots'];
            if (in_array('morning', $slots) && in_array('evening', $slots)) {
                return back()->withErrors([
                    'time_slots' => 'Invalid time slot combination. You can only select Morning + Afternoon or Afternoon + Evening.'
                ])->withInput();
            }
        }

        // Calculate number of days and end_date
        $days = $request->number_of_days ?? 1;
        $validated['number_of_days'] = $days;
        
        // Calculate end_date based on number of days
        $bookingDate = \Carbon\Carbon::parse($validated['booking_date']);
        if ($venue->type === 'suite' && $days > 1) {
            // For multi-day suite bookings, end_date is booking_date + (days - 1)
            $validated['end_date'] = $bookingDate->copy()->addDays($days - 1)->format('Y-m-d');
        } else {
            // For single-day bookings or venues, end_date is same as booking_date
            $validated['end_date'] = $validated['booking_date'];
        }

        $requestedTimeSlots = ($request->time_slot_type === 'multiple' && !empty($validated['time_slots']))
            ? $validated['time_slots']
            : [];

        if (!$venue->isAvailable($validated['booking_date'], $validated['end_date'], null, $requestedTimeSlots)) {
            $errorMessage = !empty($requestedTimeSlots)
                ? 'One or more selected time slots are already booked for this date.'
                : 'Venue is not available for selected date range.';
            return back()->withErrors(['booking_date' => $errorMessage])->withInput();
        }
        
        // Calculate venue/package amount
        if ($request->package_id) {
            // Package booking
            $package = \App\Models\VenuePackage::findOrFail($request->package_id);
            
            // Check if multiple time slots are selected
            if ($request->time_slot_type === 'multiple' && $validated['time_slots']) {
                $venueAmount = 0;
                foreach ($validated['time_slots'] as $slot) {
                    if ($package->hasTimeBasedPricing()) {
                        $venueAmount += $package->getPriceForTimeSlot($slot) * $days;
                    } else {
                        $venueAmount += $package->price * $days;
                    }
                }
            } elseif ($package->hasTimeBasedPricing() && $validated['time_slots']) {
                // Single time slot with package time-based pricing
                $venueAmount = 0;
                foreach ($validated['time_slots'] as $slot) {
                    $venueAmount += $package->getPriceForTimeSlot($slot) * $days;
                }
            } else {
                // Full day package booking
                if ($package->hasTimeBasedPricing()) {
                    // Calculate full day as sum of all 3 time slots
                    $venueAmount = ($package->getPriceForTimeSlot('morning') + 
                                   $package->getPriceForTimeSlot('afternoon') + 
                                   $package->getPriceForTimeSlot('evening')) * $days;
                } else {
                    // Package without time-based pricing
                    $venueAmount = $package->price * $days;
                }
            }
            
            $validated['package_id'] = $request->package_id;
        } elseif ($request->time_slot_type === 'multiple' && $validated['time_slots']) {
            // Multiple time slots booking
            $venueAmount = 0;
            foreach ($validated['time_slots'] as $slot) {
                $pricePerSlot = match($slot) {
                    'morning' => $venue->price_morning ?? $venue->price_per_day,
                    'afternoon' => $venue->price_afternoon ?? $venue->price_per_day,
                    'evening' => $venue->price_evening ?? $venue->price_per_day,
                    default => $venue->price_per_day
                };
                $venueAmount += $pricePerSlot * $days;
            }
        } else {
            // Full day booking uses the dedicated full day price
            $venueAmount = $venue->price_per_day * $days;
        }

        // Calculate add-ons amount
        $addonsAmount = 0;
        $selectedAddons = [];
        if ($request->addons) {
            $addons = \App\Models\VenueAddon::whereIn('id', $request->addons)->get();
            foreach ($addons as $addon) {
                $quantity = $request->addon_quantities[$addon->id] ?? 1;
                
                // Check stock availability
                if ($addon->track_stock && $addon->stock_quantity < $quantity) {
                    return back()->withErrors(['addons' => "Insufficient stock for {$addon->name}. Only {$addon->stock_quantity} available."])->withInput();
                }
                
                $subtotal = $addon->price * $quantity;
                $addonsAmount += $subtotal;
                $selectedAddons[$addon->id] = [
                    'quantity' => $quantity,
                    'price_at_booking' => $addon->price
                ];
            }
        }

        $originalAmount = $venueAmount + $addonsAmount;
        
        // Apply discount if provided
        $discountAmount = 0;
        $discountPercentage = null;
        
        if ($request->discount_type && $request->discount_value > 0) {
            if ($request->discount_type === 'amount') {
                $discountAmount = min($request->discount_value, $originalAmount);
                $discountPercentage = ($discountAmount / $originalAmount) * 100;
            } elseif ($request->discount_type === 'percentage') {
                $discountPercentage = min($request->discount_value, 100);
                $discountAmount = ($originalAmount * $discountPercentage) / 100;
            }
        }
        
        $finalAmount = $originalAmount - $discountAmount;

        $validated['staff_id'] = auth()->id();
        $validated['original_amount'] = $originalAmount;
        $validated['discount_amount'] = $discountAmount;
        $validated['discount_percentage'] = $discountPercentage;
        $validated['total_amount'] = $finalAmount;
        $validated['status'] = 'pending'; // Stays pending until payment is verified

        // Wrap booking creation, addon attachment, and stock decrement in a transaction
        $booking = DB::transaction(function () use ($validated, $selectedAddons) {
            $booking = Booking::create($validated);

            if (!empty($selectedAddons)) {
                $booking->addons()->attach($selectedAddons);

                foreach ($selectedAddons as $addonId => $data) {
                    $addon = \App\Models\VenueAddon::find($addonId);
                    if ($addon && $addon->track_stock) {
                        $addon->decrement('stock_quantity', $data['quantity']);
                    }
                }
            }

            return $booking;
        });

        // Load relationships for email (outside transaction)
        $booking->load(['venue', 'package', 'addons']);

        // Send email notification after successful transaction
        try {
            Mail::to($booking->client_email)->send(new BookingStatusNotification($booking));
        } catch (\Exception $e) {
            \Log::error('Failed to send new booking email: ' . $e->getMessage());
        }

        return redirect()->route('admin.bookings.show', $booking)
            ->with('success', 'Booking created successfully! Status is pending until payment is verified.');
    }

    public function show(Booking $booking)
    {
        $booking->load(['venue', 'package', 'staff', 'payments.verifiedBy']);
        return view('admin.bookings.show', compact('booking'));
    }

    public function edit(Booking $booking)
    {
        $venues = Venue::with('activePackages')->where('is_active', true)->get();

        $venuePackages = $venues->mapWithKeys(function ($venue) {
            return [$venue->id => $venue->activePackages->map(function ($package) {
                return [
                    'id'                   => $package->id,
                    'name'                 => $package->name,
                    'price'                => $package->price,
                    'price_morning'        => $package->price_morning,
                    'price_afternoon'      => $package->price_afternoon,
                    'price_evening'        => $package->price_evening,
                    'has_time_based_pricing' => $package->has_time_based_pricing,
                ];
            })];
        });

        return view('admin.bookings.edit', compact('booking', 'venues', 'venuePackages'));
    }

    public function update(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'venue_id' => 'required|exists:venues_and_suites,id',
            'package_id' => 'nullable|exists:venue_packages,id',
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email',
            'client_phone' => 'required|string',
            'booking_date' => 'required|date|after_or_equal:today',
            'number_of_days' => 'nullable|integer|min:1|max:365',
            'time_slot_type' => 'nullable|in:full_day,multiple',
            'time_slots' => 'nullable|array',
            'time_slots.*' => 'in:morning,afternoon,evening',
            'status' => 'required|in:confirmed,cancelled,completed',
            'notes' => 'nullable|string'
        ], [
            'booking_date.after_or_equal' => 'Cannot book past dates. Please select today or a future date.'
        ]);

        // Additional check for past dates
        $bookingDate = \Carbon\Carbon::parse($validated['booking_date']);
        if ($bookingDate->isPast() && !$bookingDate->isToday()) {
            return back()->withErrors([
                'booking_date' => 'Cannot update to a past date. The selected date has already passed. Please choose today or a future date.'
            ])->withInput();
        }

        // Validate time slot combination
        if (!empty($validated['time_slots']) && count($validated['time_slots']) === 2) {
            $slots = $validated['time_slots'];
            if (in_array('morning', $slots) && in_array('evening', $slots)) {
                return back()->withErrors([
                    'time_slots' => 'Invalid time slot combination. You can only select Morning + Afternoon or Afternoon + Evening.'
                ])->withInput();
            }
        }

        $venue = Venue::findOrFail($validated['venue_id']);

        $days = $request->number_of_days ?? 1;
        $validated['number_of_days'] = $days;

        $bookingDate = \Carbon\Carbon::parse($validated['booking_date']);
        if ($venue->type === 'suite' && $days > 1) {
            $validated['end_date'] = $bookingDate->copy()->addDays($days - 1)->format('Y-m-d');
        } else {
            $validated['end_date'] = $validated['booking_date'];
        }

        $requestedTimeSlots = ($request->time_slot_type === 'multiple' && !empty($validated['time_slots']))
            ? $validated['time_slots']
            : [];

        if (!$venue->isAvailable($validated['booking_date'], $validated['end_date'], $booking->id, $requestedTimeSlots)) {
            $errorMessage = !empty($requestedTimeSlots)
                ? 'One or more selected time slots are already booked for this date.'
                : 'Venue is not available for selected date range.';
            return back()->withErrors(['booking_date' => $errorMessage])->withInput();
        }

        // Recalculate amount — mirrors store() logic
        if ($request->package_id) {
            $package = \App\Models\VenuePackage::findOrFail($request->package_id);

            if ($request->time_slot_type === 'multiple' && !empty($validated['time_slots'])) {
                $venueAmount = 0;
                foreach ($validated['time_slots'] as $slot) {
                    $venueAmount += $package->hasTimeBasedPricing()
                        ? $package->getPriceForTimeSlot($slot) * $days
                        : $package->price * $days;
                }
            } elseif ($package->hasTimeBasedPricing() && !empty($validated['time_slots'])) {
                $venueAmount = 0;
                foreach ($validated['time_slots'] as $slot) {
                    $venueAmount += $package->getPriceForTimeSlot($slot) * $days;
                }
            } else {
                $venueAmount = $package->hasTimeBasedPricing()
                    ? ($package->getPriceForTimeSlot('morning') + $package->getPriceForTimeSlot('afternoon') + $package->getPriceForTimeSlot('evening')) * $days
                    : $package->price * $days;
            }

            $validated['package_id'] = $request->package_id;
        } elseif ($request->time_slot_type === 'multiple' && !empty($validated['time_slots'])) {
            $venueAmount = 0;
            foreach ($validated['time_slots'] as $slot) {
                $pricePerSlot = match($slot) {
                    'morning'   => $venue->price_morning ?? $venue->price_per_day,
                    'afternoon' => $venue->price_afternoon ?? $venue->price_per_day,
                    'evening'   => $venue->price_evening ?? $venue->price_per_day,
                    default     => $venue->price_per_day
                };
                $venueAmount += $pricePerSlot * $days;
            }
            $validated['package_id'] = null;
        } else {
            $validated['package_id'] = null;
            $venueAmount = $venue->price_per_day * $days;
        }

        $validated['total_amount'] = $venueAmount;

        $booking->update($validated);

        return redirect()->route('admin.bookings.show', $booking)
            ->with('success', 'Booking updated successfully!');
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();

        return redirect()->route('admin.bookings.index')
            ->with('success', 'Booking deleted successfully!');
    }

    public function confirm(Booking $booking)
    {
        $previousStatus = $booking->status;
        $booking->update(['status' => 'confirmed']);

        // Load package relationship for email
        $booking->load(['venue', 'package']);

        // Send email notification
        try {
            Mail::to($booking->client_email)->send(new BookingStatusNotification($booking, $previousStatus));
        } catch (\Exception $e) {
            // Log error but don't fail the status update
            \Log::error('Failed to send booking confirmation email: ' . $e->getMessage());
        }

        return redirect()->route('admin.bookings.index')
            ->with('success', 'Booking confirmed successfully! Email notification sent to client.');
    }

    public function cancel(Booking $booking)
    {
        $previousStatus = $booking->status;

        // Restore addon stock before cancelling
        $booking->load('addons');
        foreach ($booking->addons as $addon) {
            if ($addon->track_stock) {
                $quantity = $addon->pivot->quantity ?? 1;
                $addon->increment('stock_quantity', $quantity);
            }
        }

        $booking->update(['status' => 'cancelled']);

        $booking->load(['venue', 'package']);

        try {
            Mail::to($booking->client_email)->send(new BookingStatusNotification($booking, $previousStatus));
        } catch (\Exception $e) {
            \Log::error('Failed to send booking cancellation email: ' . $e->getMessage());
        }

        return redirect()->route('admin.bookings.index')
            ->with('success', 'Booking cancelled successfully! Email notification sent to client.');
    }

    public function complete(Booking $booking)
    {
        $previousStatus = $booking->status;
        $booking->update(['status' => 'completed']);

        // Load package relationship for email
        $booking->load(['venue', 'package']);

        // Send email notification
        try {
            Mail::to($booking->client_email)->send(new BookingStatusNotification($booking, $previousStatus));
        } catch (\Exception $e) {
            // Log error but don't fail the status update
            \Log::error('Failed to send booking completion email: ' . $e->getMessage());
        }

        return redirect()->route('admin.bookings.index')
            ->with('success', 'Booking marked as completed! Email notification sent to client.');
    }
    public function calendar()
    {
        return redirect()->route('admin.bookings.create');
    }

    public function calendarData(Request $request)
    {
        $year = (int) ($request->year ?? date('Y'));
        $month = (int) ($request->month ?? date('n'));
        $month = max(1, min(12, $month));
        $year = max(2020, min(2030, $year));

        $monthAnchor = Carbon::createFromDate($year, $month, 1);
        $today = Carbon::today();
        $isCurrentMonth = $year === (int) $today->year && $month === (int) $today->month;

        // For the current month, start the grid at today instead of the 1st
        // so the admin sees today's date first without having to scroll past
        // days that have already passed.
        $start = $isCurrentMonth ? $today->copy() : $monthAnchor->copy()->startOfMonth();
        $end = $monthAnchor->copy()->endOfMonth();

        $venues = Venue::where('is_active', true)->orderBy('type')->orderBy('name')->get();

        $bookings = Booking::with(['venue', 'package'])
            ->whereIn('venue_id', $venues->pluck('id'))
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('booking_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('booking_date', '<=', $start)
                            ->where('end_date', '>=', $end);
                    });
            })
            ->get();

        $slotStartHours = ['morning' => 8, 'afternoon' => 13, 'evening' => 18];
        $todayPassedSlots = [];
        foreach ($slotStartHours as $slot => $startHour) {
            if (Carbon::now()->hour >= $startHour) {
                $todayPassedSlots[] = $slot;
            }
        }

        $days = [];
        $current = $start->copy();
        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $isToday = $current->isToday();
            $isPast = $current->isPast() && !$isToday;
            $passedSlots = $isToday ? $todayPassedSlots : [];

            $dayData = [
                'date' => $dateStr,
                'day' => $current->day,
                'day_name' => $current->format('D'),
                'is_today' => $isToday,
                'is_past' => $isPast,
                'is_weekend' => $current->isWeekend(),
                'venues' => [],
            ];

            foreach ($venues as $venue) {
                $dayBookings = $bookings->filter(function ($b) use ($venue, $dateStr) {
                    return $b->venue_id === $venue->id
                        && $b->booking_date->format('Y-m-d') <= $dateStr
                        && $b->end_date->format('Y-m-d') >= $dateStr;
                });

                $dayData['venues'][$venue->id] = [
                    'bookings' => $dayBookings->map(function ($b) {
                        return [
                            'id' => $b->id,
                            'reference' => $b->booking_reference,
                            'client_name' => $b->client_name,
                            'client_email' => $b->client_email,
                            'client_phone' => $b->client_phone,
                            'status' => $b->status,
                            'payment_status' => $b->payment_status,
                            'total_amount' => $b->total_amount,
                            'time_slots' => $b->getTimeSlotsDisplay(),
                            'time_slots_raw' => $b->getTimeSlots(),
                            'package' => $b->package?->name,
                            'booking_date' => $b->booking_date->format('M d, Y'),
                            'end_date' => $b->end_date->format('M d, Y'),
                            'number_of_days' => $b->number_of_days ?? 1,
                        ];
                    })->values(),
                    'availability' => $this->calculateVenueDayAvailability($venue, $dayBookings, $isPast, $passedSlots),
                ];
            }

            $days[] = $dayData;
            $current->addDay();
        }

        $totalVenues = $venues->count();
        $occupiedDays = 0;
        $partialDays = 0;
        $totalDays = count($days) * $totalVenues;
        foreach ($days as $day) {
            foreach ($day['venues'] as $venueDay) {
                if ($venueDay['availability']['status'] === 'partially-booked') {
                    $partialDays++;
                } elseif ($venueDay['availability']['status'] === 'fully-booked') {
                    $occupiedDays++;
                }
            }
        }

        return response()->json([
            'year' => $year,
            'month' => $month,
            'month_name' => $monthAnchor->format('F Y'),
            'prev' => ['year' => $monthAnchor->copy()->subMonth()->year, 'month' => $monthAnchor->copy()->subMonth()->month],
            'next' => ['year' => $monthAnchor->copy()->addMonth()->year, 'month' => $monthAnchor->copy()->addMonth()->month],
            'venues' => $venues->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'type' => $v->type,
                'capacity' => $v->capacity,
                'room_number' => $v->room_number,
            ]),
            'days' => $days,
            'stats' => [
                'total_venues' => $totalVenues,
                'occupied' => $occupiedDays,
                'partial' => $partialDays,
                'available' => $totalDays - $occupiedDays - $partialDays,
                'occupancy_rate' => $totalDays > 0 ? round((($occupiedDays + $partialDays) / $totalDays) * 100, 1) : 0,
            ],
        ]);
    }

    private function calculateVenueDayAvailability(Venue $venue, $dayBookings, bool $isPast, array $passedSlots = []): array
    {
        if ($isPast) {
            return [
                'status' => 'past',
                'can_book' => false,
                'booked_slots' => [],
                'available_slots' => [],
            ];
        }

        if ($venue->type === 'suite') {
            if ($dayBookings->isNotEmpty()) {
                return [
                    'status' => 'fully-booked',
                    'can_book' => false,
                    'booked_slots' => ['suite'],
                    'available_slots' => [],
                ];
            }

            return [
                'status' => 'available',
                'can_book' => true,
                'booked_slots' => [],
                'available_slots' => ['suite'],
            ];
        }

        $bookedSlots = [];
        foreach ($dayBookings as $booking) {
            $slots = $booking->getTimeSlots();
            if (empty($slots)) {
                return [
                    'status' => 'fully-booked',
                    'can_book' => false,
                    'booked_slots' => ['full-day'],
                    'available_slots' => [],
                ];
            }

            $bookedSlots = array_merge($bookedSlots, $slots);
        }

        $bookedSlots = array_values(array_unique($bookedSlots));
        $allSlots = ['morning', 'afternoon', 'evening'];
        $unavailableSlots = array_values(array_unique(array_merge($bookedSlots, $passedSlots)));
        $availableSlots = array_values(array_diff($allSlots, $unavailableSlots));

        if (empty($availableSlots)) {
            // If there are no actual bookings, all slots are just time-expired — not truly "fully booked"
            if (empty($bookedSlots)) {
                return [
                    'status' => 'time-passed',
                    'can_book' => false,
                    'booked_slots' => [],
                    'available_slots' => [],
                ];
            }

            return [
                'status' => 'fully-booked',
                'can_book' => false,
                'booked_slots' => $bookedSlots,
                'available_slots' => [],
            ];
        }

        if (empty($bookedSlots)) {
            return [
                'status' => 'available',
                'can_book' => true,
                'booked_slots' => [],
                'available_slots' => $availableSlots,
            ];
        }

        return [
            'status' => 'partially-booked',
            'can_book' => true,
            'booked_slots' => $bookedSlots,
            'available_slots' => $availableSlots,
        ];
    }
}
