@extends('layouts.admin')

@section('page-title', 'Create Booking')

@section('main-content')
<div class="booking-form-container">
    <!-- Header Section -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <a href="{{ route('admin.bookings.create') }}" class="inline-flex items-center text-sm text-purple-600 hover:text-purple-800 font-medium mb-2">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Calendar
                </a>
                <h1 class="text-2xl font-bold text-gray-900">Create New Booking</h1>
                <p class="text-gray-600 mt-1">Fill in the details below to create a new venue or suite booking</p>
            </div>
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-calendar-plus text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <form action="{{ route('admin.bookings.store') }}" method="POST" class="space-y-6" id="bookingForm">
        @csrf
        
        <!-- Venue Selection Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center mb-6">
                <div class="w-8 h-8 bg-purple-600 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-building text-white text-sm"></i>
                </div>
                <h2 class="text-lg font-semibold text-gray-900" id="sectionTitle">Venue & Package Selection</h2>
            </div>
            
            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Venue Selection -->
                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Select Venue or Suite *</label>
                    
                    <!-- Type Filter Tabs -->
                    <div class="flex mb-4 bg-gray-50 rounded-lg p-1 w-fit">
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

                    <!-- Venue Dropdown -->
                    <select name="venue_id" id="venue_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600 focus:border-purple-600 transition-colors">
                        <option value="" id="dropdownPlaceholder">Choose venue and suites</option>
                        @foreach($venues as $venue)
                        <option value="{{ $venue->id }}" 
                                data-price="{{ $venue->price_per_day }}" 
                                data-price-morning="{{ $venue->price_morning ?? 0 }}"
                                data-price-afternoon="{{ $venue->price_afternoon ?? 0 }}"
                                data-price-evening="{{ $venue->price_evening ?? 0 }}"
                                data-platform-rates='@json($venue->platformRates->pluck('price_per_night', 'booking_platform_id')->map(fn ($p) => (float) $p))'
                                data-type="{{ $venue->type }}"
                                class="venue-option venue-type-{{ $venue->type }}"
                                {{ isset($selectedVenueId) && $selectedVenueId == $venue->id ? 'selected' : '' }}>
                            {{ $venue->name }}
                        </option>
                        @endforeach
                    </select>
                    @if($venues->count() == 0)
                    <p class="text-sm text-red-500 mt-2">No active venues or suites available. Please create one first.</p>
                    @endif

                    <!-- Event Package Selection -->
                    <div id="packageField" class="mt-4" style="display: none;">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Event Package (Optional)</label>
                        <select name="package_id" id="package_id" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600 focus:border-purple-600 transition-colors">
                            <option value="">Standard Venue Rental</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Choose an event package or use standard venue rental pricing</p>
                    </div>

                    <!-- Time Slot Selection (for Venues) -->
                    <div id="timeSlotField" class="mt-4" style="display: none;">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Time Slots *</label>
                        <p class="text-xs text-gray-500 mb-3">Select one or multiple time slots. Prices will be combined for multiple selections.</p>
                        
                        <div class="space-y-3">
                            <!-- Full Day Option -->
                            <div class="flex items-center cursor-pointer time-slot-option" data-value="">
                                <input type="radio" name="time_slot_type" value="full_day" checked class="sr-only time-slot-radio">
                                <div class="time-slot-radio-custom w-5 h-5 border-2 border-gray-300 rounded-full flex items-center justify-center mr-3 transition-all duration-200">
                                    <div class="w-2.5 h-2.5 bg-purple-600 rounded-full opacity-100 transition-opacity duration-200"></div>
                                </div>
                                <span class="text-gray-700 select-none font-medium">Full Day</span>
                                <span class="ml-2 text-sm text-gray-500">(Complete venue access)</span>
                            </div>
                            
                            <!-- Multiple Time Slots Option -->
                            <div class="flex items-center cursor-pointer time-slot-option" data-value="multiple">
                                <input type="radio" name="time_slot_type" value="multiple" class="sr-only time-slot-radio">
                                <div class="time-slot-radio-custom w-5 h-5 border-2 border-gray-300 rounded-full flex items-center justify-center mr-3 transition-all duration-200">
                                    <div class="w-2.5 h-2.5 bg-purple-600 rounded-full opacity-0 transition-opacity duration-200"></div>
                                </div>
                                <span class="text-gray-700 select-none font-medium">Select Specific Time Slots</span>
                                <span class="ml-2 text-sm text-gray-500">(Choose one or more slots)</span>
                            </div>
                        </div>
                        
                        <!-- Multiple Time Slots Selection -->
                        <div id="multipleTimeSlotsContainer" class="mt-4 hidden">
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <label class="block text-sm font-medium text-gray-700 mb-3">Select Time Slots:</label>
                                <div class="space-y-3">
                                    <div class="flex items-center cursor-pointer time-slot-checkbox-option">
                                        <input type="checkbox" name="time_slots[]" value="morning" class="sr-only time-slot-checkbox">
                                        <div class="time-slot-checkbox-custom w-5 h-5 border-2 border-gray-300 rounded flex items-center justify-center mr-3 transition-all duration-200">
                                            <i class="fas fa-check text-white text-sm opacity-0 transition-opacity duration-200"></i>
                                        </div>
                                        <div class="flex-1">
                                            <span class="text-gray-700 select-none font-medium">Morning</span>
                                            <span class="ml-2 text-sm text-gray-500">(8:00 AM - 12:00 PM)</span>
                                            <span class="ml-2 text-sm font-medium text-blue-600" id="morningPrice">₱0</span>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center cursor-pointer time-slot-checkbox-option">
                                        <input type="checkbox" name="time_slots[]" value="afternoon" class="sr-only time-slot-checkbox">
                                        <div class="time-slot-checkbox-custom w-5 h-5 border-2 border-gray-300 rounded flex items-center justify-center mr-3 transition-all duration-200">
                                            <i class="fas fa-check text-white text-sm opacity-0 transition-opacity duration-200"></i>
                                        </div>
                                        <div class="flex-1">
                                            <span class="text-gray-700 select-none font-medium">Afternoon</span>
                                            <span class="ml-2 text-sm text-gray-500">(1:00 PM - 5:00 PM)</span>
                                            <span class="ml-2 text-sm font-medium text-orange-600" id="afternoonPrice">₱0</span>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center cursor-pointer time-slot-checkbox-option">
                                        <input type="checkbox" name="time_slots[]" value="evening" class="sr-only time-slot-checkbox">
                                        <div class="time-slot-checkbox-custom w-5 h-5 border-2 border-gray-300 rounded flex items-center justify-center mr-3 transition-all duration-200">
                                            <i class="fas fa-check text-white text-sm opacity-0 transition-opacity duration-200"></i>
                                        </div>
                                        <div class="flex-1">
                                            <span class="text-gray-700 select-none font-medium">Evening</span>
                                            <span class="ml-2 text-sm text-gray-500">(6:00 PM - 10:00 PM)</span>
                                            <span class="ml-2 text-sm font-medium text-indigo-600" id="eveningPrice">₱0</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-blue-800">Selected Time Slots Total:</span>
                                        <span class="text-lg font-bold text-blue-800" id="timeSlotsTotal">₱0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Price Display & Suite Info -->
                <div class="lg:col-span-1">
                    <div class="sticky-price-container" id="stickyPriceContainer">
                        <!-- Suite Info -->
                        <div id="suiteInfo" style="display: none;">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 shadow-lg">
                                <div class="text-center mb-3">
                                    <i class="fas fa-bed text-blue-600 text-2xl mb-2"></i>
                                    <p class="font-semibold text-blue-800">Suite Booking</p>
                                </div>
                                <div class="text-sm text-blue-700 space-y-2">
                                    <div class="flex items-center">
                                        <i class="fas fa-clock mr-2 w-4"></i>
                                        <span><strong>Check-in:</strong> 2:00 PM</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-clock mr-2 w-4"></i>
                                        <span><strong>Check-out:</strong> 12:00 PM (next day)</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-info-circle mr-2 w-4"></i>
                                        <span>22-hour booking period</span>
                                    </div>
                                    <div class="flex items-start">
                                        <i class="fas fa-walking mr-2 w-4 mt-0.5"></i>
                                        <span><strong>Walk-in:</strong> Same-day booking is allowed if the suite is available</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Client Information Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center mb-6">
                <div class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-user text-white text-sm"></i>
                </div>
                <h2 class="text-lg font-semibold text-gray-900">Client Information</h2>
            </div>
            
            <div class="grid md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Client Name *</label>
                    <input type="text" name="client_name" value="{{ old('client_name') }}" required dusk="client-name" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600 focus:border-green-600 transition-colors">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Client Email (optional)</label>
                    <input type="email" name="client_email" value="{{ old('client_email') }}" dusk="client-email" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600 focus:border-green-600 transition-colors">
                    <p class="text-xs text-gray-500 mt-1">Optional. Needed for the booking confirmation, payment and reminder emails.</p>
                    @error('client_email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Client Phone *</label>
                    <input type="text" name="client_phone" value="{{ old('client_phone') }}" required dusk="client-phone" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600 focus:border-green-600 transition-colors">
                </div>
            </div>
        </div>

        <!-- Booking Details Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center mb-6">
                <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-calendar text-white text-sm"></i>
                </div>
                <h2 class="text-lg font-semibold text-gray-900">Booking Details</h2>
            </div>
            
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Booking Date *</label>
                    <input type="date" name="booking_date" id="booking_date" value="{{ old('booking_date', request('booking_date')) }}" required min="{{ date('Y-m-d') }}"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600 transition-colors">
                </div>

                <!-- Number of Days (for Suites only) -->
                <div id="numberOfDaysField" style="display: none;">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Number of Days *</label>
                    <input type="number" name="number_of_days" id="number_of_days" dusk="number-of-days" value="{{ old('number_of_days', 1) }}" min="1" max="365"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600 transition-colors">
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        Price will be calculated per day
                    </p>

                    @include('admin.bookings.partials.rate-source-fields', [
                        'selectedPlatformId' => old('booking_platform_id'),
                        'platformReference' => old('platform_reference'),
                    ])
                </div>

                <div id="notesField">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
                    <textarea name="notes" rows="3" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600 transition-colors resize-none"
                        placeholder="Any special requirements or notes...">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <!-- Add-ons Selection Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" id="addonsSection">
            <div class="flex items-center mb-6">
                <div class="w-8 h-8 bg-orange-600 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-plus-circle text-white text-sm"></i>
                </div>
                <h2 class="text-lg font-semibold text-gray-900">Add-ons & Services</h2>
                <span class="ml-2 text-sm text-gray-500">(Optional)</span>
            </div>
            
            @if($addons->count() > 0)
            <div class="space-y-6">
                <!-- Category Selector -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">
                        <i class="fas fa-filter text-orange-600 mr-2"></i>
                        Select Category:
                    </label>
                    <select id="addon-category-selector" class="w-full md:w-1/2 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-600 focus:border-orange-600 transition-colors">
                        <option value="">Choose a category...</option>
                        @foreach($addons as $category => $categoryAddons)
                        <option value="{{ $category }}">
                            {{ ucfirst($category) }} ({{ count($categoryAddons) }} items)
                        </option>
                        @endforeach
                    </select>
                </div>

                <!-- Default message when no category is selected -->
                <div id="no-addon-category-message" class="text-center py-8">
                    <i class="fas fa-arrow-up text-gray-300 text-4xl mb-3"></i>
                    <h3 class="text-lg font-medium text-gray-600 mb-2">Select a Category</h3>
                    <p class="text-gray-500">Choose a category from the dropdown above to view available add-ons.</p>
                </div>

                <!-- Add-ons by Category -->
                @foreach($addons as $category => $categoryAddons)
                <div class="addon-category-section hidden" data-category="{{ $category }}">
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-800 capitalize mb-4">
                            <i class="fas fa-{{ $category == 'catering' ? 'utensils' : ($category == 'decoration' ? 'palette' : ($category == 'equipment' ? 'tools' : 'concierge-bell')) }} text-orange-600 mr-2"></i>
                            {{ ucfirst($category) }}
                            <span class="text-sm text-gray-500 font-normal ml-2">({{ count($categoryAddons) }} items available)</span>
                        </h3>
                        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($categoryAddons as $addon)
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-orange-300 transition-colors addon-item {{ $addon->isOutOfStock() ? 'opacity-60' : '' }}" data-addon-id="{{ $addon->id }}">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <h4 class="font-medium text-gray-900 mb-1">{{ $addon->name }}</h4>
                                        <p class="text-sm text-gray-600 mb-2">{{ $addon->description }}</p>
                                        <p class="text-lg font-bold text-orange-600">₱{{ number_format($addon->price, 2) }}</p>
                                        
                                        <!-- Stock Information -->
                                        @if($addon->track_stock)
                                            <div class="mt-2">
                                                @if($addon->isOutOfStock())
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                        <i class="fas fa-times-circle mr-1"></i>
                                                        Out of Stock
                                                    </span>
                                                @elseif($addon->isLowStock())
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                                        Only {{ $addon->stock_quantity }} left
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        <i class="fas fa-check-circle mr-1"></i>
                                                        {{ $addon->stock_quantity }} available
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    <div class="ml-3">
                                        @if(!$addon->isOutOfStock())
                                            <label class="flex items-center cursor-pointer">
                                                <input type="checkbox" 
                                                       name="addons[]" 
                                                       value="{{ $addon->id }}"
                                                       class="addon-checkbox sr-only" 
                                                       data-addon-id="{{ $addon->id }}" 
                                                       data-addon-price="{{ $addon->price }}"
                                                       data-addon-name="{{ $addon->name }}"
                                                       data-track-stock="{{ $addon->track_stock ? 'true' : 'false' }}"
                                                       data-stock-quantity="{{ $addon->stock_quantity ?? 999 }}">
                                                <div class="addon-checkbox-custom w-6 h-6 border-2 border-gray-300 rounded flex items-center justify-center transition-all duration-200">
                                                    <i class="fas fa-check text-white text-sm opacity-0 transition-opacity duration-200"></i>
                                                </div>
                                            </label>
                                        @else
                                            <div class="w-6 h-6 border-2 border-gray-300 rounded flex items-center justify-center bg-gray-200">
                                                <i class="fas fa-ban text-gray-400 text-sm"></i>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                
                                <!-- Quantity selector (hidden by default) -->
                                @if(!$addon->isOutOfStock())
                                    <div class="addon-quantity border-t border-gray-200">
                                        <label class="block text-xs font-medium text-gray-700 mb-2">Quantity:</label>
                                        <div class="flex items-center space-x-3">
                                            <button type="button" class="quantity-btn quantity-minus w-8 h-8 bg-gray-200 hover:bg-gray-300 rounded-full flex items-center justify-center transition-all duration-200">
                                                <i class="fas fa-minus text-sm"></i>
                                            </button>
                                            <input type="number" 
                                                   name="addon_quantities[{{ $addon->id }}]"
                                                   class="addon-quantity-input w-16 text-center border border-gray-300 rounded px-2 py-1 text-sm font-medium" 
                                                   value="1" 
                                                   min="1" 
                                                   max="{{ $addon->track_stock ? $addon->stock_quantity : 99 }}">
                                            <button type="button" class="quantity-btn quantity-plus w-8 h-8 bg-gray-200 hover:bg-gray-300 rounded-full flex items-center justify-center transition-all duration-200">
                                                <i class="fas fa-plus text-sm"></i>
                                            </button>
                                        </div>
                                        @if($addon->track_stock && $addon->stock_quantity <= 5)
                                            <p class="text-xs text-orange-600 mt-1">
                                                <i class="fas fa-info-circle mr-1"></i>
                                                Limited stock: Maximum {{ $addon->stock_quantity }} available
                                            </p>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endforeach

                <!-- Add-ons Summary -->
                <div id="addons-summary" class="hidden bg-orange-50 border border-orange-200 rounded-lg p-4">
                    <h4 class="font-medium text-orange-800 mb-3">Selected Add-ons:</h4>
                    <div id="selected-addons-list" class="space-y-2 text-sm mb-3"></div>
                    <div class="flex justify-between items-center pt-3 border-t border-orange-200">
                        <span class="font-medium text-orange-800">Add-ons Subtotal:</span>
                        <span class="font-bold text-orange-800" id="addons-total">₱0.00</span>
                    </div>
                </div>
            </div>
            @else
            <div class="text-center py-8">
                <i class="fas fa-plus-circle text-gray-300 text-4xl mb-3"></i>
                <h3 class="text-lg font-medium text-gray-600 mb-2">No Add-ons Available</h3>
                <p class="text-gray-500">Add-on services are not currently available.</p>
            </div>
            @endif
        </div>

        <!-- Discount Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center mb-6">
                <div class="w-8 h-8 bg-red-600 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-percent text-white text-sm"></i>
                </div>
                <h2 class="text-lg font-semibold text-gray-900">Discount</h2>
                <span class="ml-2 text-sm text-gray-500">(Optional)</span>
            </div>
            
            <div class="grid md:grid-cols-3 gap-6">
                <!-- Discount Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">Discount Type</label>
                    <div class="space-y-3">
                        <div class="flex items-center cursor-pointer discount-option" data-value="">
                            <input type="radio" name="discount_type" value="" checked class="sr-only discount-type-radio">
                            <div class="discount-radio-custom w-5 h-5 border-2 border-gray-300 rounded-full flex items-center justify-center mr-3 transition-all duration-200">
                                <div class="w-2.5 h-2.5 bg-red-600 rounded-full opacity-0 transition-opacity duration-200"></div>
                            </div>
                            <span class="text-gray-700 select-none">No Discount</span>
                        </div>
                        <div class="flex items-center cursor-pointer discount-option" data-value="amount" dusk="discount-type-amount">
                            <input type="radio" name="discount_type" value="amount" class="sr-only discount-type-radio">
                            <div class="discount-radio-custom w-5 h-5 border-2 border-gray-300 rounded-full flex items-center justify-center mr-3 transition-all duration-200">
                                <div class="w-2.5 h-2.5 bg-red-600 rounded-full opacity-0 transition-opacity duration-200"></div>
                            </div>
                            <span class="text-gray-700 select-none">Fixed Amount (₱)</span>
                        </div>
                        <div class="flex items-center cursor-pointer discount-option" data-value="percentage" dusk="discount-type-percentage">
                            <input type="radio" name="discount_type" value="percentage" class="sr-only discount-type-radio">
                            <div class="discount-radio-custom w-5 h-5 border-2 border-gray-300 rounded-full flex items-center justify-center mr-3 transition-all duration-200">
                                <div class="w-2.5 h-2.5 bg-red-600 rounded-full opacity-0 transition-opacity duration-200"></div>
                            </div>
                            <span class="text-gray-700 select-none">Percentage (%)</span>
                        </div>
                    </div>
                </div>

                <!-- Discount Value -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Discount Value</label>
                    <div class="relative">
                        <input type="number" 
                               name="discount_value" 
                               id="discount_value"
                               dusk="discount-value"
                               value="{{ old('discount_value') }}" 
                               min="0" 
                               step="0.01"
                               disabled
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-red-600 transition-colors disabled:bg-gray-100 disabled:cursor-not-allowed">
                        <div id="discount-currency" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 text-sm hidden">
                            ₱
                        </div>
                        <div id="discount-percentage" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 text-sm hidden">
                            %
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1" id="discount-helper">Select discount type to enable this field</p>
                </div>

                <!-- Discount Reason -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Discount Reason</label>
                    <input type="text" 
                           name="discount_reason" 
                           id="discount_reason"
                           dusk="discount-reason"
                           value="{{ old('discount_reason') }}" 
                           disabled
                           placeholder="e.g., Senior citizen, Loyalty discount, etc."
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-red-600 transition-colors disabled:bg-gray-100 disabled:cursor-not-allowed">
                    <p class="text-xs text-gray-500 mt-1">Provide reason for applying discount</p>
                </div>
            </div>

            <!-- Discount Preview -->
            <div id="discount-preview" class="hidden mt-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <h4 class="font-medium text-red-800 mb-3">Discount Preview:</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between items-center">
                        <span class="text-red-700">Original Amount:</span>
                        <span class="font-medium text-red-800" id="preview-original">₱0.00</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-red-700">Discount:</span>
                        <span class="font-medium text-red-800" id="preview-discount">₱0.00</span>
                    </div>
                    <div class="flex justify-between items-center pt-2 border-t border-red-200 font-semibold">
                        <span class="text-red-800">Final Amount:</span>
                        <span class="text-red-800" id="preview-final" dusk="discount-preview-final">₱0.00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>
                    All required fields must be completed before submission
                </div>
                <div class="flex space-x-4">
                    <a href="{{ route('admin.bookings.index') }}" 
                       class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </a>
                    <button type="submit" dusk="booking-submit"
                            class="px-6 py-3 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 transition-colors shadow-sm">
                        <i class="fas fa-save mr-2"></i>Create Booking
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

@include('admin.bookings.partials.create-script')
@include('admin.bookings.partials.create-style')
@endsection
