@extends('layouts.public')

@section('main-content')
<div class="container mx-auto px-4 py-12">
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <!-- Image Gallery Carousel -->
        @if($venue->images && count($venue->images) > 0)
        <div class="relative h-[500px] bg-black overflow-hidden group">
            @foreach($venue->images as $index => $image)
            <div class="carousel-item absolute inset-0 transition-opacity duration-700 {{ $index === 0 ? 'opacity-100' : 'opacity-0' }}" data-carousel="venue-details">
                <img src="{{ \App\Helpers\ImageHelper::getImageUrl($image) }}" alt="{{ $venue->name }}" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
            </div>
            @endforeach
            
            @if(count($venue->images) > 1)
            <!-- Navigation Arrows -->
            <button onclick="prevSlide('venue-details')" class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white p-3 rounded-full transition-all duration-200 opacity-0 group-hover:opacity-100 shadow-lg border border-white/30">
                <i class="fas fa-chevron-left text-lg"></i>
            </button>
            <button onclick="nextSlide('venue-details')" class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white p-3 rounded-full transition-all duration-200 opacity-0 group-hover:opacity-100 shadow-lg border border-white/30">
                <i class="fas fa-chevron-right text-lg"></i>
            </button>
            
            <!-- Image Counter -->
            <div class="absolute top-6 right-6 bg-black/70 backdrop-blur-sm text-white px-4 py-2 rounded-full text-sm font-semibold border border-white/20">
                <i class="fas fa-images mr-2"></i>
                <span class="current-slide">1</span> / {{ count($venue->images) }}
            </div>
            
            <!-- Venue Name Overlay -->
            <div class="absolute bottom-20 left-6 right-6 text-white">
                <h1 class="text-4xl md:text-5xl font-bold mb-2 drop-shadow-lg">{{ $venue->name }}</h1>
                <p class="text-lg text-white/90 drop-shadow">
                    <i class="fas fa-{{ $venue->type == 'venue' ? 'building' : 'bed' }} mr-2"></i>
                    {{ ucfirst($venue->type) }}
                </p>
            </div>
            
            <!-- Thumbnail Navigation -->
            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex space-x-2 max-w-full overflow-x-auto px-4 pb-2">
                @foreach($venue->images as $index => $image)
                <button onclick="goToSlide('venue-details', {{ $index }})" 
                        class="carousel-thumb flex-shrink-0 w-20 h-14 rounded-lg overflow-hidden border-2 transition-all duration-200 {{ $index === 0 ? 'border-white scale-110 shadow-xl' : 'border-white/30 opacity-60 hover:opacity-100 hover:border-white/60' }}" 
                        data-carousel="venue-details" 
                        data-index="{{ $index }}">
                    <img src="{{ \App\Helpers\ImageHelper::getImageUrl($image) }}" alt="Thumbnail {{ $index + 1 }}" class="w-full h-full object-cover">
                </button>
                @endforeach
            </div>
            @endif
        </div>
        @else
        <div class="w-full h-[500px] bg-gradient-to-br from-purple-500 via-purple-600 to-pink-500 flex items-center justify-center">
            <div class="text-center text-white">
                <i class="fas fa-{{ $venue->type == 'venue' ? 'building' : 'bed' }} text-8xl mb-4 opacity-50"></i>
                <h1 class="text-4xl font-bold">{{ $venue->name }}</h1>
            </div>
        </div>
        @endif

        <!-- Venue Details -->
        <div class="p-8">
            <p class="text-gray-500 mb-8 text-lg leading-loose tracking-wide font-light italic border-l-4 border-purple-300 pl-5">{{ $venue->description }}</p>

            <!-- Amenities Section -->
            @if($venue->amenities && count($venue->amenities) > 0)
            <div class="mb-8">
                <h3 class="text-2xl font-bold mb-4 text-gray-800">Amenities</h3>
                @php
                    $amenities = is_array($venue->amenities)
                        ? $venue->amenities
                        : array_filter(array_map('trim', explode("\n", $venue->amenities)));
                @endphp
                <div class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3">
                    @foreach($amenities as $amenity)
                    <div class="flex items-start gap-2 py-2 border-b border-gray-100">
                        <i class="fas fa-check text-purple-500 mt-0.5 flex-shrink-0 text-xs"></i>
                        <span class="text-gray-700 text-sm">{{ trim($amenity) }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="grid md:grid-cols-3 gap-6 mb-8">
                <div class="bg-purple-50 p-4 rounded-lg">
                    <i class="fas fa-users text-purple-600 text-2xl mb-2"></i>
                    <p class="text-gray-700 font-semibold">Capacity</p>
                    <p class="text-2xl font-bold text-purple-600">{{ $venue->capacity }} guests</p>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg">
                    <i class="fas fa-tag text-purple-600 text-2xl mb-2"></i>
                    <p class="text-gray-700 font-semibold">Base Price</p>
                    <p class="text-2xl font-bold text-purple-600">₱{{ number_format($venue->price_per_day, 2) }}</p>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg">
                    <i class="fas fa-building text-purple-600 text-2xl mb-2"></i>
                    <p class="text-gray-700 font-semibold">Type</p>
                    <p class="text-2xl font-bold text-purple-600">{{ ucfirst($venue->type) }}</p>
                </div>
            </div>

            @if($venue->type == 'suite' && $venue->room_number)
            <div class="mb-6">
                <div class="inline-flex items-center gap-2 bg-blue-50 border border-blue-200 rounded-xl px-5 py-3">
                    <i class="fas fa-door-open text-blue-600 text-xl"></i>
                    <span class="text-gray-700 font-semibold">Room Number:</span>
                    <span class="text-xl font-bold text-blue-700">{{ $venue->room_number }}</span>
                </div>
            </div>
            @endif

            <!-- Interactive Pricing Selection -->
            <div class="mb-8">
                <h3 class="text-3xl font-bold mb-4 text-gray-800 flex items-center">
                    <i class="fas fa-calculator text-purple-600 mr-3"></i>
                    Select Your Booking Option
                </h3>
                <p class="text-sm text-gray-600 mb-6">
                    <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                    You can select multiple time slots. Selecting all 3 converts to Full Day pricing. Morning + Evening cannot be combined.
                </p>
                
                <div class="grid gap-6" id="pricingOptions">
                    @if($venue->type == 'venue')
                        <!-- Time-Based Options Grid -->
                        <div class="grid md:grid-cols-3 gap-4">
                            <!-- Full Day Option -->
                            <div class="pricing-option bg-gradient-to-br from-purple-50 to-indigo-50 border-2 border-purple-200 rounded-xl p-4 cursor-pointer hover:border-purple-400 transition-all duration-300 transform hover:scale-105" 
                                 data-type="full-day" 
                                 data-price="{{ $venue->price_per_day }}"
                                 data-name="Full Day Rental">
                                <div class="flex items-center mb-3">
                                    <div class="w-5 h-5 border-2 border-purple-400 rounded-full mr-3 flex items-center justify-center pricing-radio">
                                        <div class="w-2.5 h-2.5 bg-purple-600 rounded-full opacity-0 transition-opacity duration-200"></div>
                                    </div>
                                    <i class="fas fa-calendar-day text-purple-600 text-xl mr-2"></i>
                                    <h4 class="font-semibold text-gray-800">Full Day</h4>
                                </div>
                                <p class="text-sm text-gray-600 mb-2">Complete venue access</p>
                                <div class="text-2xl font-bold text-purple-600">₱{{ number_format($venue->price_per_day, 0) }}</div>
                            </div>

                            @if($venue->price_morning)
                            <div class="pricing-option bg-gradient-to-br from-blue-50 to-cyan-50 border-2 border-blue-200 rounded-xl p-4 cursor-pointer hover:border-blue-400 transition-all duration-300 transform hover:scale-105" 
                                 data-type="morning" 
                                 data-price="{{ $venue->price_morning }}"
                                 data-name="Morning Slot">
                                <div class="flex items-center mb-3">
                                    <div class="w-5 h-5 border-2 border-blue-400 rounded-full mr-3 flex items-center justify-center pricing-radio">
                                        <div class="w-2.5 h-2.5 bg-blue-600 rounded-full opacity-0 transition-opacity duration-200"></div>
                                    </div>
                                    <i class="fas fa-sun text-blue-600 text-xl mr-2"></i>
                                    <h4 class="font-semibold text-gray-800">Morning</h4>
                                </div>
                                <p class="text-sm text-gray-600 mb-2">8:00 AM - 12:00 PM</p>
                                <div class="text-2xl font-bold text-blue-600">₱{{ number_format($venue->price_morning, 0) }}</div>
                            </div>
                            @endif

                            @if($venue->price_afternoon)
                            <div class="pricing-option bg-gradient-to-br from-orange-50 to-yellow-50 border-2 border-orange-200 rounded-xl p-4 cursor-pointer hover:border-orange-400 transition-all duration-300 transform hover:scale-105" 
                                 data-type="afternoon" 
                                 data-price="{{ $venue->price_afternoon }}"
                                 data-name="Afternoon Slot">
                                <div class="flex items-center mb-3">
                                    <div class="w-5 h-5 border-2 border-orange-400 rounded-full mr-3 flex items-center justify-center pricing-radio">
                                        <div class="w-2.5 h-2.5 bg-orange-600 rounded-full opacity-0 transition-opacity duration-200"></div>
                                    </div>
                                    <i class="fas fa-cloud-sun text-orange-600 text-xl mr-2"></i>
                                    <h4 class="font-semibold text-gray-800">Afternoon</h4>
                                </div>
                                <p class="text-sm text-gray-600 mb-2">1:00 PM - 5:00 PM</p>
                                <div class="text-2xl font-bold text-orange-600">₱{{ number_format($venue->price_afternoon, 0) }}</div>
                            </div>
                            @endif

                            @if($venue->price_evening)
                            <div class="pricing-option bg-gradient-to-br from-indigo-50 to-purple-50 border-2 border-indigo-200 rounded-xl p-4 cursor-pointer hover:border-indigo-400 transition-all duration-300 transform hover:scale-105" 
                                 data-type="evening" 
                                 data-price="{{ $venue->price_evening }}"
                                 data-name="Evening Slot">
                                <div class="flex items-center mb-3">
                                    <div class="w-5 h-5 border-2 border-indigo-400 rounded-full mr-3 flex items-center justify-center pricing-radio">
                                        <div class="w-2.5 h-2.5 bg-indigo-600 rounded-full opacity-0 transition-opacity duration-200"></div>
                                    </div>
                                    <i class="fas fa-moon text-indigo-600 text-xl mr-2"></i>
                                    <h4 class="font-semibold text-gray-800">Evening</h4>
                                </div>
                                <p class="text-sm text-gray-600 mb-2">6:00 PM - 10:00 PM</p>
                                <div class="text-2xl font-bold text-indigo-600">₱{{ number_format($venue->price_evening, 0) }}</div>
                            </div>
                            @endif
                        </div>

                        <!-- Event Packages -->
                        @if($venue->activePackages && $venue->activePackages->count() > 0)
                        <div class="mt-6">
                            <h4 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-box-open text-purple-600 mr-2"></i>
                                Event Packages
                            </h4>
                            <div class="grid md:grid-cols-3 gap-4">
                                @foreach($venue->activePackages as $package)
                                    @if($package->hasTimeBasedPricing())
                                        <!-- Package with Time-Based Pricing -->
                                        <div class="pricing-option bg-gradient-to-br from-purple-50 to-pink-50 border-2 border-purple-200 rounded-xl p-4 cursor-pointer hover:border-purple-400 transition-all duration-300 transform hover:scale-105" 
                                             data-type="package" 
                                             data-price="{{ $package->price }}"
                                             data-name="{{ $package->name }}"
                                             data-package-id="{{ $package->id }}"
                                             data-has-time-pricing="true"
                                             data-price-morning="{{ $package->price_morning ?? 0 }}"
                                             data-price-afternoon="{{ $package->price_afternoon ?? 0 }}"
                                             data-price-evening="{{ $package->price_evening ?? 0 }}"
                                             data-time-slot="full-day">
                                            <div class="flex items-center mb-3">
                                                <div class="w-5 h-5 border-2 border-purple-400 rounded-full mr-3 flex items-center justify-center pricing-radio">
                                                    <div class="w-2.5 h-2.5 bg-purple-600 rounded-full opacity-0 transition-opacity duration-200"></div>
                                                </div>
                                                <i class="fas fa-box-open text-purple-600 text-xl mr-2"></i>
                                                <h4 class="font-semibold text-gray-800">{{ $package->name }}</h4>
                                            </div>
                                            @if($package->description)
                                            <p class="text-sm text-gray-600 mb-2">{{ Str::limit($package->description, 50) }}</p>
                                            @endif
                                            <div class="text-sm text-gray-600 mb-2">
                                                <i class="fas fa-clock text-purple-500 mr-1"></i>
                                                Time-based pricing available
                                            </div>
                                            <div class="text-2xl font-bold text-purple-600">Starting at ₱{{ number_format(min($package->price_morning ?? $package->price, $package->price_afternoon ?? $package->price, $package->price_evening ?? $package->price), 0) }}</div>
                                            @if(count($package->inclusion_labels) > 0)
                                            <div class="flex items-center justify-between mt-2">
                                                <div class="text-xs text-gray-500">
                                                    <i class="fas fa-check-circle text-green-500 mr-1"></i>
                                                    {{ count($package->inclusion_labels) }} inclusions
                                                </div>
                                                <button onclick="showPackageInclusions({{ $package->id }}, '{{ $package->name }}', {{ json_encode($package->inclusion_labels) }})" class="text-xs text-purple-600 hover:text-purple-800 font-medium underline">
                                                    View Details
                                                </button>
                                            </div>
                                            @endif
                                        </div>
                                    @else
                                        <!-- Regular Package - Will show time slot selection -->
                                        <div class="pricing-option bg-gradient-to-br from-purple-50 to-pink-50 border-2 border-purple-200 rounded-xl p-4 cursor-pointer hover:border-purple-400 transition-all duration-300 transform hover:scale-105" 
                                             data-type="package" 
                                             data-price="{{ $package->price }}"
                                             data-name="{{ $package->name }}"
                                             data-package-id="{{ $package->id }}"
                                             data-has-time-pricing="false"
                                             data-price-morning="{{ $venue->price_morning ?? $package->price }}"
                                             data-price-afternoon="{{ $venue->price_afternoon ?? $package->price }}"
                                             data-price-evening="{{ $venue->price_evening ?? $package->price }}"
                                             data-time-slot="full-day">
                                            <div class="flex items-center mb-3">
                                                <div class="w-5 h-5 border-2 border-purple-400 rounded-full mr-3 flex items-center justify-center pricing-radio">
                                                    <div class="w-2.5 h-2.5 bg-purple-600 rounded-full opacity-0 transition-opacity duration-200"></div>
                                                </div>
                                                <i class="fas fa-box-open text-purple-600 text-xl mr-2"></i>
                                                <h4 class="font-semibold text-gray-800">{{ $package->name }}</h4>
                                            </div>
                                            @if($package->description)
                                            <p class="text-sm text-gray-600 mb-2">{{ Str::limit($package->description, 50) }}</p>
                                            @endif
                                            <div class="text-2xl font-bold text-purple-600">₱{{ number_format($package->price, 0) }}</div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <i class="fas fa-clock text-purple-500 mr-1"></i>
                                                Select time slot after choosing
                                            </div>
                                            @if(count($package->inclusion_labels) > 0)
                                            <div class="flex items-center justify-between mt-2">
                                                <div class="text-xs text-gray-500">
                                                    <i class="fas fa-check-circle text-green-500 mr-1"></i>
                                                    {{ count($package->inclusion_labels) }} inclusions
                                                </div>
                                                <button onclick="showPackageInclusions({{ $package->id }}, '{{ $package->name }}', {{ json_encode($package->inclusion_labels) }})" class="text-xs text-purple-600 hover:text-purple-800 font-medium underline">
                                                    View Details
                                                </button>
                                            </div>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Time Slot Selection (appears when package is selected) -->
                        <div id="packageTimeSlotSection" class="mt-6 hidden">
                            <h4 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-clock text-purple-600 mr-2"></i>
                                Select Time Slot for Package
                            </h4>
                            <p class="text-sm text-gray-600 mb-4">
                                <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                                Choose your preferred time slot for this package
                            </p>
                            <div class="grid md:grid-cols-4 gap-4" id="packageTimeSlotOptions">
                                <!-- Time slots will be populated by JavaScript -->
                            </div>
                        </div>

                    @else
                        <!-- Suite Booking -->
                        <div class="grid md:grid-cols-3 gap-4">
                            <div class="pricing-option bg-gradient-to-br from-blue-50 to-cyan-50 border-2 border-blue-200 rounded-xl p-4 cursor-pointer hover:border-blue-400 transition-all duration-300 transform hover:scale-105 selected" 
                                 data-type="suite" 
                                 data-price="{{ $venue->price_per_day }}"
                                 data-name="Suite Booking">
                                <div class="flex items-center mb-3">
                                    <div class="w-5 h-5 border-2 border-blue-400 rounded-full mr-3 flex items-center justify-center pricing-radio">
                                        <div class="w-2.5 h-2.5 bg-blue-600 rounded-full opacity-100 transition-opacity duration-200"></div>
                                    </div>
                                    <i class="fas fa-bed text-blue-600 text-xl mr-2"></i>
                                    <h4 class="font-semibold text-gray-800">Suite Booking</h4>
                                </div>
                                <p class="text-sm text-gray-600 mb-2">22-hour stay (2:00 PM - 12:00 PM next day)</p>
                                <p class="text-xs text-blue-700 mb-2"><i class="fas fa-walking mr-1"></i>Same-day walk-in available if suite is open</p>
                                <div class="text-2xl font-bold text-blue-600">₱{{ number_format($venue->price_per_day, 0) }}</div>
                                <div class="text-xs text-gray-500 mt-1">per night</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            <!-- Add-ons Section (Only for Venues) -->
            @if($venue->type == 'venue')
            <div class="mb-8" id="addonsSection" style="display: none;">
                <h3 class="text-3xl font-bold mb-6 text-gray-800 flex items-center">
                    <i class="fas fa-plus-circle text-orange-600 mr-3"></i>
                    Enhance Your Event
                </h3>
                
                <div class="bg-gradient-to-r from-orange-50 to-yellow-50 border-2 border-orange-200 rounded-xl p-4 mb-6 max-w-2xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-lg font-bold text-gray-800 mb-2">Add Premium Services</h4>
                            <p class="text-gray-600 text-sm">Select from our curated add-ons to make your event unforgettable</p>
                        </div>
                        <button id="loadAddons" class="bg-gradient-to-r from-orange-600 to-yellow-600 text-white px-4 py-2 rounded-lg font-medium hover:from-orange-700 hover:to-yellow-700 transition-all duration-300 shadow-md hover:shadow-lg whitespace-nowrap ml-4">
                            <i class="fas fa-plus-circle mr-2 text-sm"></i>
                            Browse Add-ons
                        </button>
                    </div>
                </div>

                <!-- Add-ons will be loaded here -->
                <div id="addonsContainer" style="display: none;">
                    <!-- Category Selector -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-3">
                            <i class="fas fa-filter text-orange-600 mr-2"></i>
                            Select Category:
                        </label>
                        <select id="addon-category-selector" class="w-full md:w-1/2 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-600 focus:border-orange-600 transition-colors">
                            <option value="">Choose a category...</option>
                        </select>
                    </div>

                    <!-- Default message when no category is selected -->
                    <div id="no-addon-category-message" class="text-center py-8">
                        <i class="fas fa-arrow-up text-gray-300 text-4xl mb-3"></i>
                        <h3 class="text-lg font-medium text-gray-600 mb-2">Select a Category</h3>
                        <p class="text-gray-500">Choose a category from the dropdown above to view available add-ons.</p>
                    </div>

                    <!-- Add-ons by Category (will be populated dynamically) -->
                    <div id="addons-categories-container">
                        <!-- Categories will be loaded here -->
                    </div>
                </div>
            </div>
            @endif

            <!-- Price Summary -->
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 border-2 border-gray-200 rounded-xl p-6 mb-8" id="priceSummary" style="display: none;">
                <h3 class="text-2xl font-bold mb-4 text-gray-800 flex items-center">
                    <i class="fas fa-receipt text-green-600 mr-3"></i>
                    Booking Summary
                </h3>
                
                <div class="space-y-3">
                    <div class="flex justify-between items-center py-2 border-b border-gray-200">
                        <span class="text-lg font-medium text-gray-700" id="selectedOption">No option selected</span>
                        <span class="text-lg font-bold text-gray-800" id="basePrice">₱0</span>
                    </div>
                    
                    <div id="addonsBreakdown" style="display: none;">
                        <div class="text-sm font-medium text-gray-600 mb-2">Selected Add-ons:</div>
                        <div id="addonsList" class="space-y-1 mb-3"></div>
                    </div>
                    
                    <div class="flex justify-between items-center py-3 border-t-2 border-gray-300 bg-green-50 rounded-lg px-4">
                        <span class="text-xl font-bold text-gray-800">Total Amount:</span>
                        <span class="text-3xl font-bold text-green-600" id="totalPrice">₱0</span>
                    </div>
                </div>
                
                <div class="mt-6 flex flex-col sm:flex-row gap-3">
                    <button id="contactForBooking" class="flex-1 bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-3 px-4 rounded-lg font-medium hover:from-purple-700 hover:to-indigo-700 transition-all duration-300 shadow-md hover:shadow-lg">
                        <i class="fas fa-phone mr-2 text-sm"></i>
                        Contact to Book
                    </button>
                    <button id="checkAvailability" class="flex-1 bg-gradient-to-r from-green-600 to-teal-600 text-white py-3 px-4 rounded-lg font-medium hover:from-green-700 hover:to-teal-700 transition-all duration-300 shadow-md hover:shadow-lg">
                        <i class="fas fa-calendar-check mr-2 text-sm"></i>
                        Check Availability
                    </button>
                </div>
            </div>

            <!-- Contact Information Modal -->
            <div id="contactModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" style="display: none;">
                <div class="bg-white rounded-xl shadow-2xl w-80 p-4">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-2xl font-bold text-gray-800">Contact Us to Book</h3>
                        <button id="closeContactModal" class="text-gray-400 hover:text-gray-600 p-1 rounded-full hover:bg-gray-100 transition-all duration-200">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                    
                    <p class="text-gray-700 mb-6">Ready to make your event unforgettable? Contact us through any of the following:</p>
                    
                    <div class="space-y-4">
                        @if($contact && $contact->phone)
                        <a href="tel:{{ $contact->phone }}" class="flex items-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-all duration-300 group">
                            <div class="w-12 h-12 bg-purple-600 rounded-full flex items-center justify-center mr-4 group-hover:scale-110 transition-transform duration-300">
                                <i class="fas fa-phone text-white text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-800">Call Us</h4>
                                <p class="text-purple-600 font-medium">{{ $contact->phone }}</p>
                            </div>
                        </a>
                        @endif
                        
                        @if($contact && $contact->google_form_url)
                        <a href="{{ $contact->google_form_url }}" target="_blank" class="flex items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-all duration-300 group">
                            <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center mr-4 group-hover:scale-110 transition-transform duration-300">
                                <i class="fas fa-envelope text-white text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-800">Book via Form</h4>
                                <p class="text-blue-600 font-medium">Online Booking Form</p>
                            </div>
                        </a>
                        @elseif($contact && $contact->email)
                        <a href="mailto:{{ $contact->email }}" class="flex items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-all duration-300 group">
                            <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center mr-4 group-hover:scale-110 transition-transform duration-300">
                                <i class="fas fa-envelope text-white text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-800">Email Us</h4>
                                <p class="text-blue-600 font-medium">{{ $contact->email }}</p>
                            </div>
                        </a>
                        @endif
                        
                        @if($contact && $contact->whatsapp)
                        <a href="#" id="whatsappContact" data-whatsapp="{{ $contact->whatsapp }}" class="flex items-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-all duration-300 group">
                            <div class="w-12 h-12 bg-green-600 rounded-full flex items-center justify-center mr-4 group-hover:scale-110 transition-transform duration-300">
                                <i class="fab fa-whatsapp text-white text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-800">WhatsApp</h4>
                                <p class="text-green-600 font-medium">Send Message</p>
                            </div>
                        </a>
                        @endif
                        
                        @if($contact && $contact->facebook)
                        <a href="{{ $contact->facebook }}" target="_blank" class="flex items-center p-4 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-all duration-300 group">
                            <div class="w-12 h-12 bg-indigo-600 rounded-full flex items-center justify-center mr-4 group-hover:scale-110 transition-transform duration-300">
                                <i class="fab fa-facebook text-white text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-800">Facebook Page</h4>
                                <p class="text-indigo-600 font-medium">Visit Our Page</p>
                            </div>
                        </a>
                        @endif
                    </div>
                    
                    @if(isset($selectedPricing) || !empty($selectedAddons))
                    <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                        <h4 class="font-semibold text-gray-800 mb-2">Your Selection Summary:</h4>
                        <div id="contactModalSummary" class="text-sm text-gray-600">
                            <!-- Summary will be populated by JavaScript -->
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Availability Calendar Modal -->
            <div id="availabilityModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" style="display: none;">
                <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
                    <div class="flex items-center justify-between p-6 border-b border-gray-200">
                        <h3 class="text-2xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-calendar-alt text-green-600 mr-3"></i>
                            Availability Calendar
                        </h3>
                        <button id="closeModal" class="text-gray-400 hover:text-gray-600 p-2 rounded-full hover:bg-gray-100 transition-all duration-200">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
                        <!-- Calendar Header -->
                        <div class="flex items-center justify-between mb-6">
                            <button id="prevMonth" class="flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                                <i class="fas fa-chevron-left mr-2"></i>
                                Previous
                            </button>
                            
                            <h4 id="calendarTitle" class="text-xl font-bold text-gray-800">Loading...</h4>
                            
                            <button id="nextMonth" class="flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                                Next
                                <i class="fas fa-chevron-right ml-2"></i>
                            </button>
                        </div>
                        
                        <!-- Legend -->
                        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-green-200 border border-green-300 rounded mr-2"></div>
                                    <span>Available</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-yellow-200 border border-yellow-300 rounded mr-2"></div>
                                    <span>Partially Booked</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-red-200 border border-red-300 rounded mr-2"></div>
                                    <span>Fully Booked</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-gray-200 border border-gray-300 rounded mr-2"></div>
                                    <span>Past Date</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Calendar Grid -->
                        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                            <!-- Days of Week Header -->
                            <div class="grid grid-cols-7 bg-gray-50 border-b border-gray-200">
                                <div class="p-3 text-center font-semibold text-gray-700 text-sm">Sun</div>
                                <div class="p-3 text-center font-semibold text-gray-700 text-sm">Mon</div>
                                <div class="p-3 text-center font-semibold text-gray-700 text-sm">Tue</div>
                                <div class="p-3 text-center font-semibold text-gray-700 text-sm">Wed</div>
                                <div class="p-3 text-center font-semibold text-gray-700 text-sm">Thu</div>
                                <div class="p-3 text-center font-semibold text-gray-700 text-sm">Fri</div>
                                <div class="p-3 text-center font-semibold text-gray-700 text-sm">Sat</div>
                            </div>
                            
                            <!-- Calendar Days -->
                            <div id="calendarGrid" class="grid grid-cols-7">
                                <!-- Days will be populated by JavaScript -->
                            </div>
                        </div>
                        
                        <!-- Selected Date Info -->
                        <div id="selectedDateInfo" class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg hidden">
                            <h5 class="font-semibold text-blue-800 mb-2">Selected Date Information</h5>
                            <div id="selectedDateContent">
                                <!-- Content will be populated by JavaScript -->
                            </div>
                        </div>
                        
                        <!-- Loading State -->
                        <div id="calendarLoading" class="text-center py-8">
                            <i class="fas fa-spinner fa-spin text-gray-400 text-3xl mb-3"></i>
                            <p class="text-gray-600">Loading calendar...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Package Inclusions Modal -->
            <div id="packageInclusionsModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" style="display: none;">
                <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
                    <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-purple-50 to-pink-50">
                        <h3 class="text-2xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-box-open text-purple-600 mr-3"></i>
                            <span id="packageModalTitle">Package Inclusions</span>
                        </h3>
                        <button id="closePackageModal" class="text-gray-400 hover:text-gray-600 p-2 rounded-full hover:bg-white transition-all duration-200">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
                        <div id="packageInclusionsList" class="space-y-3">
                            <!-- Inclusions will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            @if($contact)
            <div class="bg-purple-50 p-6 rounded-lg">
                <h3 class="text-2xl font-bold mb-4 text-gray-800">Contact Us to Book</h3>
                <p class="text-gray-700 mb-4">Ready to make your event unforgettable? Contact us through any of the following:</p>
                <div class="grid md:grid-cols-2 gap-4">
                    @if($contact->phone)
                    <div class="flex items-center">
                        <i class="fas fa-phone text-purple-600 mr-3 text-xl"></i>
                        <a href="tel:{{ $contact->phone }}" class="text-purple-600 hover:underline font-medium">{{ $contact->phone }}</a>
                    </div>
                    @endif
                    @if($contact->google_form_url)
                    <div class="flex items-center">
                        <i class="fas fa-envelope text-purple-600 mr-3 text-xl"></i>
                        <a href="{{ $contact->google_form_url }}" target="_blank" class="text-purple-600 hover:underline font-medium">Book via Form</a>
                    </div>
                    @elseif($contact->email)
                    <div class="flex items-center">
                        <i class="fas fa-envelope text-purple-600 mr-3 text-xl"></i>
                        <a href="mailto:{{ $contact->email }}" class="text-purple-600 hover:underline font-medium">{{ $contact->email }}</a>
                    </div>
                    @endif
                    @if($contact->whatsapp)
                    <div class="flex items-center">
                        <i class="fab fa-whatsapp text-purple-600 mr-3 text-xl"></i>
                        <a href="https://wa.me/{{ $contact->whatsapp }}" target="_blank" class="text-purple-600 hover:underline font-medium">WhatsApp</a>
                    </div>
                    @endif
                    @if($contact->facebook)
                    <div class="flex items-center">
                        <i class="fab fa-facebook text-purple-600 mr-3 text-xl"></i>
                        <a href="{{ $contact->facebook }}" target="_blank" class="text-purple-600 hover:underline font-medium">Facebook Page</a>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@push('scripts')
@include('public.partials.venue-details-script')
@endpush
@endsection
