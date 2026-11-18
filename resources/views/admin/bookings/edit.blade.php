@extends('layouts.admin')

@section('page-title', 'Edit Booking')

@section('main-content')
<div class="bg-white rounded-lg shadow p-6">
    <form action="{{ route('admin.bookings.update', $booking) }}" method="POST" id="bookingEditForm">
        @csrf
        @method('PUT')
        
        <div class="grid md:grid-cols-2 gap-6">
            <!-- Venue/Suite Selection Section -->
            <div class="md:col-span-2">
                <label class="block text-gray-700 font-semibold mb-3">Select Venue or Suite *</label>
                
                <!-- Type Filter Tabs -->
                <div class="flex mb-4 bg-gray-100 rounded-lg p-1 w-fit">
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="venue_type_filter" value="all" checked class="sr-only">
                        <span class="px-4 py-2 rounded-md text-sm font-medium transition-all duration-200 venue-filter-tab active">
                            <i class="fas fa-th mr-2"></i>All
                        </span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="venue_type_filter" value="venue" class="sr-only">
                        <span class="px-4 py-2 rounded-md text-sm font-medium transition-all duration-200 venue-filter-tab">
                            <i class="fas fa-building mr-2"></i>Venues
                        </span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="venue_type_filter" value="suite" class="sr-only">
                        <span class="px-4 py-2 rounded-md text-sm font-medium transition-all duration-200 venue-filter-tab">
                            <i class="fas fa-bed mr-2"></i>Suites
                        </span>
                    </label>
                </div>

                <!-- Venue Selection -->
                <div class="max-w-md">
                    <select name="venue_id" id="venue_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600 focus:border-transparent">
                        @foreach($venues as $venue)
                        <option value="{{ $venue->id }}" 
                                data-type="{{ $venue->type }}"
                                data-price="{{ $venue->price_per_day }}"
                                data-price-morning="{{ $venue->price_morning ?? 0 }}"
                                data-price-afternoon="{{ $venue->price_afternoon ?? 0 }}"
                                data-price-evening="{{ $venue->price_evening ?? 0 }}"
                                data-platform-rates='@json($venue->platformRates->pluck('price_per_night', 'booking_platform_id')->map(fn ($p) => (float) $p))'
                                class="venue-option venue-type-{{ $venue->type }}"
                                {{ $booking->venue_id == $venue->id ? 'selected' : '' }}>
                            {{ $venue->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Event Package Selection (for Venues only) -->
            <div class="md:col-span-2" id="packageField" style="display: none;">
                <label class="block text-gray-700 font-semibold mb-2">Event Package (Optional)</label>
                <div class="max-w-md">
                    <select name="package_id" id="package_id" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600 focus:border-transparent">
                        <option value="">Standard Venue Rental</option>
                    </select>
                </div>
                <p class="text-sm text-gray-500 mt-1">Choose an event package or use standard venue rental pricing</p>
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-2">Status *</label>
                @php $currentStatus = old('status', $booking->status); @endphp
                <select name="status" required dusk="booking-status" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
                    {{-- Pending must be listed (and pre-selected) or the browser submits "confirmed" for pending bookings --}}
                    <option value="pending" {{ $currentStatus == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="confirmed" {{ $currentStatus == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                    <option value="cancelled" {{ $currentStatus == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    <option value="completed" {{ $currentStatus == 'completed' ? 'selected' : '' }}>Completed</option>
                </select>
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-2">Client Name *</label>
                <input type="text" name="client_name" value="{{ old('client_name', $booking->client_name) }}" required 
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-2">Client Email (optional)</label>
                <input type="email" name="client_email" value="{{ old('client_email', $booking->client_email) }}" dusk="client-email" 
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
                <p class="text-xs text-gray-500 mt-1">Optional. Needed for the booking confirmation, payment and reminder emails.</p>
                @error('client_email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-2">Client Phone *</label>
                <input type="text" name="client_phone" value="{{ old('client_phone', $booking->client_phone) }}" required 
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-2">Booking Date *</label>
                <input type="date" name="booking_date" id="booking_date" value="{{ old('booking_date', $booking->booking_date->format('Y-m-d')) }}" required min="{{ date('Y-m-d') }}"
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
            </div>

            <!-- Number of Days (for Suites only) -->
            <div id="numberOfDaysField" style="display: none;">
                <label class="block text-gray-700 font-semibold mb-2">Number of Days *</label>
                <input type="number" name="number_of_days" id="number_of_days" value="{{ old('number_of_days', $booking->number_of_days ?? 1) }}" min="1" max="365"
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
                <p class="text-sm text-gray-500 mt-1">
                    <i class="fas fa-info-circle mr-1"></i>
                    Price will be calculated per day
                </p>

                @include('admin.bookings.partials.rate-source-fields', [
                    'selectedPlatformId' => old('booking_platform_id', $booking->booking_platform_id),
                    'platformReference' => old('platform_reference', $booking->platform_reference),
                ])
            </div>

            <div id="timeSlotField">
                <label class="block text-gray-700 font-semibold mb-2">Time Slots</label>
                <p class="text-xs text-gray-500 mb-3">Select one or multiple time slots.</p>

                <div class="space-y-2 mb-3">
                    <!-- Full Day -->
                    <div class="flex items-center cursor-pointer time-slot-option">
                        <input type="radio" name="time_slot_type" value="full_day" id="ts_full_day"
                               class="sr-only time-slot-radio"
                               {{ empty($booking->time_slots) ? 'checked' : '' }}>
                        <div class="time-slot-radio-custom w-5 h-5 border-2 border-gray-300 rounded-full flex items-center justify-center mr-3 transition-all duration-200">
                            <div class="w-2.5 h-2.5 bg-purple-600 rounded-full transition-opacity duration-200 {{ empty($booking->time_slots) ? 'opacity-100' : 'opacity-0' }}"></div>
                        </div>
                        <span class="text-gray-700 font-medium select-none">Full Day</span>
                        <span class="ml-2 text-sm text-gray-500">(Complete venue access)</span>
                    </div>

                    <!-- Multiple Slots -->
                    <div class="flex items-center cursor-pointer time-slot-option">
                        <input type="radio" name="time_slot_type" value="multiple" id="ts_multiple"
                               class="sr-only time-slot-radio"
                               {{ !empty($booking->time_slots) ? 'checked' : '' }}>
                        <div class="time-slot-radio-custom w-5 h-5 border-2 border-gray-300 rounded-full flex items-center justify-center mr-3 transition-all duration-200">
                            <div class="w-2.5 h-2.5 bg-purple-600 rounded-full transition-opacity duration-200 {{ !empty($booking->time_slots) ? 'opacity-100' : 'opacity-0' }}"></div>
                        </div>
                        <span class="text-gray-700 font-medium select-none">Select Specific Time Slots</span>
                    </div>
                </div>

                <!-- Checkboxes -->
                <div id="multipleTimeSlotsContainer" class="{{ empty($booking->time_slots) ? 'hidden' : '' }} bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <div class="space-y-3">
                        @foreach(['morning' => ['label'=>'Morning','time'=>'8:00 AM – 12:00 PM','color'=>'text-blue-600'], 'afternoon' => ['label'=>'Afternoon','time'=>'1:00 PM – 5:00 PM','color'=>'text-orange-600'], 'evening' => ['label'=>'Evening','time'=>'6:00 PM – 10:00 PM','color'=>'text-indigo-600']] as $slot => $info)
                        <div class="flex items-center cursor-pointer time-slot-checkbox-option">
                            <input type="checkbox" name="time_slots[]" value="{{ $slot }}"
                                   class="sr-only time-slot-checkbox"
                                   {{ is_array($booking->time_slots) && in_array($slot, $booking->time_slots) ? 'checked' : '' }}>
                            <div class="time-slot-checkbox-custom w-5 h-5 border-2 border-gray-300 rounded flex items-center justify-center mr-3 transition-all duration-200 {{ is_array($booking->time_slots) && in_array($slot, $booking->time_slots) ? 'bg-purple-600 border-purple-600' : '' }}">
                                <i class="fas fa-check text-white text-xs {{ is_array($booking->time_slots) && in_array($slot, $booking->time_slots) ? 'opacity-100' : 'opacity-0' }} transition-opacity duration-200"></i>
                            </div>
                            <div class="flex-1">
                                <span class="text-gray-700 font-medium select-none">{{ $info['label'] }}</span>
                                <span class="ml-2 text-sm text-gray-500">({{ $info['time'] }})</span>
                                <span class="ml-2 text-sm font-medium {{ $info['color'] }}" id="{{ $slot }}Price">₱0</span>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg flex items-center justify-between">
                        <span class="text-sm font-medium text-blue-800">Selected Slots Total:</span>
                        <span class="text-lg font-bold text-blue-800" id="timeSlotsTotal">₱0</span>
                    </div>
                </div>
            </div>
            
            <div id="suiteInfo" style="display: none;">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="font-semibold text-blue-800 mb-2">Suite Booking Information</p>
                    <div class="text-sm text-blue-700 space-y-1">
                        <p><i class="fas fa-clock mr-2"></i><strong>Check-in:</strong> 2:00 PM</p>
                        <p><i class="fas fa-clock mr-2"></i><strong>Check-out:</strong> 12:00 PM (next day)</p>
                        <p><i class="fas fa-walking mr-2"></i><strong>Walk-in:</strong> Same-day booking allowed if available</p>
                        <p><i class="fas fa-info-circle mr-2"></i>Standard 22-hour booking period</p>
                    </div>
                </div>
            </div>

            <!-- Price Summary -->
            <div class="md:col-span-2">
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-purple-700" id="priceSummaryLabel">Booking Duration</p>
                            <p class="text-xs text-gray-500" id="priceSummaryDetail"></p>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-bold text-purple-700" id="priceSummaryTotal">₱{{ number_format($booking->total_amount, 2) }}</p>
                            <p class="text-xs text-gray-500">Total Amount</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const venueSelect = document.getElementById('venue_id');
            const packageSelect = document.getElementById('package_id');
            const packageField = document.getElementById('packageField');
            const timeSlotField = document.getElementById('timeSlotField');
            const suiteInfo = document.getElementById('suiteInfo');
            const typeFilters = document.querySelectorAll('input[name="venue_type_filter"]');
            const filterTabs = document.querySelectorAll('.venue-filter-tab');
            const multipleTimeSlotsContainer = document.getElementById('multipleTimeSlotsContainer');
            const numberOfDaysField = document.getElementById('numberOfDaysField');
            const numberOfDaysInput = document.getElementById('number_of_days');

            const venuePackages = @json($venuePackages);

            // ── Tab appearance ──────────────────────────────────────────
            function updateTabAppearance() {
                filterTabs.forEach(tab => {
                    const input = tab.parentElement.querySelector('input');
                    tab.classList.toggle('active', input.checked);
                    tab.classList.toggle('inactive', !input.checked);
                });
            }

            // ── Load packages for selected venue ────────────────────────
            function loadPackages() {
                const venueId = venueSelect.value;
                packageSelect.innerHTML = '<option value="">Standard Venue Rental</option>';
                if (venueId && venuePackages[venueId]) {
                    venuePackages[venueId].forEach(pkg => {
                        const opt = document.createElement('option');
                        opt.value = pkg.id;
                        opt.textContent = `${pkg.name} - ₱${parseFloat(pkg.price).toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
                        @if($booking->package_id)
                        if (pkg.id == {{ $booking->package_id }}) opt.selected = true;
                        @endif
                        packageSelect.appendChild(opt);
                    });
                }
                updateTimeSlotPrices();
            }

            // ── Update per-slot price labels ────────────────────────────
            function updateTimeSlotPrices() {
                const selectedOption = venueSelect.options[venueSelect.selectedIndex];
                if (!selectedOption || !selectedOption.value) return;

                const venueId = selectedOption.value;
                const packageId = packageSelect.value;
                const packageData = packageId ? venuePackages[venueId]?.find(p => p.id == packageId) : null;

                const prices = {
                    morning:   packageData ? (parseFloat(packageData.price_morning)   || parseFloat(packageData.price)) : parseFloat(selectedOption.getAttribute('data-price-morning') || 0),
                    afternoon: packageData ? (parseFloat(packageData.price_afternoon) || parseFloat(packageData.price)) : parseFloat(selectedOption.getAttribute('data-price-afternoon') || 0),
                    evening:   packageData ? (parseFloat(packageData.price_evening)   || parseFloat(packageData.price)) : parseFloat(selectedOption.getAttribute('data-price-evening') || 0),
                };

                ['morning','afternoon','evening'].forEach(slot => {
                    const el = document.getElementById(slot + 'Price');
                    if (el) el.textContent = prices[slot] > 0 ? '₱' + prices[slot].toLocaleString('en-PH', {minimumFractionDigits: 0}) : '';
                });

                updatePriceSummary();
            }

            // ── Main price summary ──────────────────────────────────────
            function updatePriceSummary() {
                const selectedOption = venueSelect.options[venueSelect.selectedIndex];
                if (!selectedOption || !selectedOption.value) return;

                const venueType = selectedOption.getAttribute('data-type');
                const venueId   = selectedOption.value;
                const packageId = packageSelect.value;
                const packageData = packageId ? venuePackages[venueId]?.find(p => p.id == packageId) : null;
                const days = parseInt(numberOfDaysInput?.value) || 1;

                let price = 0;
                let label = '';
                let detail = '';

                if (venueType === 'suite') {
                    // Mirrors BookingController@update: keep the booked rate while suite + rate source are unchanged.
                    const booked = @json(['venueId' => $booking->venue_id, 'platformId' => $booking->booking_platform_id, 'rate' => $booking->rate_per_night !== null ? (float) $booking->rate_per_night : null]);
                    const nightly = window.getSuiteNightlyRate(selectedOption);
                    const platformSel = document.getElementById('booking_platform_id')?.value || '';
                    const sameSource = booked.rate !== null
                        && String(booked.venueId) === String(venueId)
                        && String(booked.platformId ?? '') === String(platformSel);
                    if (sameSource) {
                        const note = document.getElementById('rateFallbackNote');
                        if (note) note.style.display = 'none';
                    }
                    const base = sameSource ? booked.rate : nightly.rate;
                    price = base * days;
                    label = days > 1 ? `Suite rental — ${nightly.label} (${days} days)` : `Suite rental — ${nightly.label} (22 hours)`;
                    detail = days > 1 ? `₱${base.toLocaleString('en-PH', {minimumFractionDigits: 2})} × ${days} days` : 'Check-in 2PM → Check-out 12PM';
                } else {
                    const isMultiple = document.querySelector('input[name="time_slot_type"][value="multiple"]')?.checked;

                    if (isMultiple) {
                        const checked = [...document.querySelectorAll('.time-slot-checkbox:checked')].map(c => c.value);
                        const slotPrices = {
                            morning:   packageData ? (parseFloat(packageData.price_morning)   || parseFloat(packageData.price)) : parseFloat(selectedOption.getAttribute('data-price-morning') || 0),
                            afternoon: packageData ? (parseFloat(packageData.price_afternoon) || parseFloat(packageData.price)) : parseFloat(selectedOption.getAttribute('data-price-afternoon') || 0),
                            evening:   packageData ? (parseFloat(packageData.price_evening)   || parseFloat(packageData.price)) : parseFloat(selectedOption.getAttribute('data-price-evening') || 0),
                        };
                        checked.forEach(s => { price += slotPrices[s] || 0; });
                        const names = {morning:'Morning', afternoon:'Afternoon', evening:'Evening'};
                        label  = checked.length ? checked.map(s => names[s]).join(' + ') : 'No slots selected';
                        detail = checked.length ? `₱${price.toLocaleString('en-PH', {minimumFractionDigits: 2})} total` : '';

                        // Update slots total display
                        const totalEl = document.getElementById('timeSlotsTotal');
                        if (totalEl) totalEl.textContent = '₱' + price.toLocaleString('en-PH', {minimumFractionDigits: 2});
                    } else {
                        // Full day
                        const base = parseFloat(selectedOption.getAttribute('data-price') || 0);
                        price = packageData ? parseFloat(packageData.price) : base;
                        label  = packageData ? `${packageData.name} (Full Day)` : 'Full Day Rental';
                        detail = `₱${price.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
                    }
                }

                const summaryLabel  = document.getElementById('priceSummaryLabel');
                const summaryDetail = document.getElementById('priceSummaryDetail');
                const summaryTotal  = document.getElementById('priceSummaryTotal');
                if (summaryLabel)  summaryLabel.textContent  = label;
                if (summaryDetail) summaryDetail.textContent = detail;
                if (summaryTotal)  summaryTotal.textContent  = '₱' + price.toLocaleString('en-PH', {minimumFractionDigits: 2});
            }

            // ── Show/hide fields based on venue type ────────────────────
            function updateFieldsBasedOnVenue() {
                const selectedOption = venueSelect.options[venueSelect.selectedIndex];
                const venueType = selectedOption?.getAttribute('data-type');

                if (venueType === 'suite') {
                    timeSlotField.style.display = 'none';
                    packageField.style.display  = 'none';
                    suiteInfo.style.display     = 'block';
                    if (numberOfDaysField) numberOfDaysField.style.display = 'block';
                } else {
                    timeSlotField.style.display = 'block';
                    packageField.style.display  = 'block';
                    suiteInfo.style.display     = 'none';
                    if (numberOfDaysField) numberOfDaysField.style.display = 'none';
                    loadPackages();
                }
                updatePriceSummary();
            }

            // ── Filter venue dropdown ───────────────────────────────────
            function filterVenues() {
                const filter = document.querySelector('input[name="venue_type_filter"]:checked').value;
                venueSelect.querySelectorAll('.venue-option').forEach(opt => {
                    opt.style.display = (filter === 'all' || opt.getAttribute('data-type') === filter) ? '' : 'none';
                });
                updateFieldsBasedOnVenue();
                updateTabAppearance();
            }

            // ── Time slot radio toggle ──────────────────────────────────
            document.querySelectorAll('.time-slot-option').forEach(option => {
                option.addEventListener('click', function() {
                    const radio = this.querySelector('.time-slot-radio');
                    radio.checked = true;
                    // Update radio visuals
                    document.querySelectorAll('.time-slot-option').forEach(o => {
                        const dot = o.querySelector('.time-slot-radio-custom div');
                        if (dot) dot.style.opacity = o.querySelector('.time-slot-radio').checked ? '1' : '0';
                    });
                    if (radio.value === 'multiple') {
                        multipleTimeSlotsContainer.classList.remove('hidden');
                    } else {
                        multipleTimeSlotsContainer.classList.add('hidden');
                    }
                    updatePriceSummary();
                });
            });

            // ── Checkbox toggle ─────────────────────────────────────────
            document.querySelectorAll('.time-slot-checkbox-option').forEach(option => {
                option.addEventListener('click', function() {
                    const cb   = this.querySelector('.time-slot-checkbox');
                    const box  = this.querySelector('.time-slot-checkbox-custom');
                    const icon = box.querySelector('i');

                    // If trying to check, validate combination
                    if (!cb.checked) {
                        const currentlyChecked = [...document.querySelectorAll('.time-slot-checkbox:checked')].map(c => c.value);
                        const newSlot = cb.value;

                        if (newSlot === 'evening' && currentlyChecked.includes('morning') && !currentlyChecked.includes('afternoon')) {
                            alert('Invalid combination: Morning and Evening cannot be selected without Afternoon. Allowed: Morning + Afternoon or Afternoon + Evening.');
                            return;
                        }
                        if (newSlot === 'morning' && currentlyChecked.includes('evening') && !currentlyChecked.includes('afternoon')) {
                            alert('Invalid combination: Morning and Evening cannot be selected without Afternoon. Allowed: Morning + Afternoon or Afternoon + Evening.');
                            return;
                        }
                    }

                    cb.checked = !cb.checked;
                    if (cb.checked) {
                        box.classList.add('bg-purple-600', 'border-purple-600');
                        if (icon) icon.classList.replace('opacity-0', 'opacity-100');
                    } else {
                        box.classList.remove('bg-purple-600', 'border-purple-600');
                        if (icon) icon.classList.replace('opacity-100', 'opacity-0');
                    }
                    updatePriceSummary();
                });
            });

            // ── Event listeners ─────────────────────────────────────────
            typeFilters.forEach(f => f.addEventListener('change', filterVenues));
            filterTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    this.parentElement.querySelector('input').checked = true;
                    filterVenues();
                });
            });
            venueSelect.addEventListener('change', updateFieldsBasedOnVenue);
            packageSelect.addEventListener('change', () => { updateTimeSlotPrices(); updatePriceSummary(); });
            if (numberOfDaysInput) numberOfDaysInput.addEventListener('input', updatePriceSummary);
            document.getElementById('booking_platform_id')?.addEventListener('change', updatePriceSummary);

            // ── Init ────────────────────────────────────────────────────
            updateFieldsBasedOnVenue();
            updateTabAppearance();

            // Form submit validation
            document.getElementById('bookingEditForm').addEventListener('submit', function(e) {
                const dateInput = document.getElementById('booking_date');
                const selected  = new Date(dateInput.value);
                const today     = new Date(); today.setHours(0,0,0,0);
                if (selected < today) {
                    e.preventDefault();
                    alert('Cannot update to a past date. Please select today or a future date.');
                    dateInput.focus();
                }
            });
        });
        </script>

        <style>
        .venue-filter-tab.active {
            background-color: #8B5CF6;
            color: white;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }
        .venue-filter-tab.inactive {
            background-color: transparent;
            color: #6B7280;
        }
        .venue-filter-tab:hover { background-color: #7C3AED; color: white; }
        .venue-filter-tab.inactive:hover { background-color: #F3F4F6; color: #374151; }
        .time-slot-option:hover .time-slot-radio-custom { border-color: #7C3AED; }
        .time-slot-checkbox-option:hover .time-slot-checkbox-custom { border-color: #7C3AED; }
        </style>

        <div class="mt-6">
            <label class="block text-gray-700 font-semibold mb-2">Notes</label>
            <textarea name="notes" rows="4" dusk="booking-notes-input"
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">{{ old('notes', $booking->notes) }}</textarea>
        </div>

        <div class="mt-8 flex space-x-4">
            <button type="submit" dusk="booking-update" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition">
                Update Booking
            </button>
            <a href="{{ route('admin.bookings.show', $booking) }}" class="bg-gray-300 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-400 transition">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
