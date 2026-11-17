        <script>
        // Venue packages data (passed from backend)
        const venuePackages = @json($venuePackages);

        document.addEventListener('DOMContentLoaded', function() {
            const venueSelect = document.getElementById('venue_id');
            const packageField = document.getElementById('packageField');
            const packageSelect = document.getElementById('package_id');
            const timeSlotField = document.getElementById('timeSlotField');
            const suiteInfo = document.getElementById('suiteInfo');
            const typeFilters = document.querySelectorAll('input[name="venue_type_filter"]');
            const filterTabs = document.querySelectorAll('.venue-filter-tab');
            
            // Floating price display elements
            const floatingPriceDisplay = document.getElementById('floatingPriceDisplay');
            const floatingDisplayPrice = document.getElementById('floatingDisplayPrice');
            const floatingPriceDescription = document.getElementById('floatingPriceDescription');
            const sectionTitle = document.getElementById('sectionTitle');
            const dropdownPlaceholder = document.getElementById('dropdownPlaceholder');
            const addonsSection = document.getElementById('addonsSection');
            
            // Add-ons elements
            const addonCategorySelector = document.getElementById('addon-category-selector');
            const noAddonCategoryMessage = document.getElementById('no-addon-category-message');
            const addonsTotal = document.getElementById('addons-total');
            const addonsSummary = document.getElementById('addons-summary');
            const selectedAddonsList = document.getElementById('selected-addons-list');
            
            // Floating price breakdown elements
            const floatingVenueLabel = document.getElementById('floatingVenueLabel');
            const floatingVenuePrice = document.getElementById('floatingVenuePrice');
            const floatingSelectedAddonsBreakdown = document.getElementById('floatingSelectedAddonsBreakdown');
            const floatingAddonsBreakdownList = document.getElementById('floatingAddonsBreakdownList');
            const floatingTotalAmount = document.getElementById('floatingTotalAmount');
            
            // Discount elements
            const discountTypeRadios = document.querySelectorAll('.discount-type-radio');
            const discountValue = document.getElementById('discount_value');
            const discountReason = document.getElementById('discount_reason');
            const discountCurrency = document.getElementById('discount-currency');
            const discountPercentage = document.getElementById('discount-percentage');
            const discountHelper = document.getElementById('discount-helper');
            const discountPreview = document.getElementById('discount-preview');
            const previewOriginal = document.getElementById('preview-original');
            const previewDiscount = document.getElementById('preview-discount');
            const previewFinal = document.getElementById('preview-final');
            
            // Add-ons functionality
            let selectedAddons = {};
            let currentVenuePrice = 0;
            let currentVenueDescription = '';
            let currentDiscountAmount = 0;
            let currentDiscountType = '';
            let currentDiscountValue = 0;
            
            // Function to update price display
            function updatePriceDisplay() {
                const selectedOption = venueSelect.options[venueSelect.selectedIndex];
                const selectedPackage = packageSelect.options[packageSelect.selectedIndex];
                
                if (!selectedOption || !selectedOption.value) {
                    if (floatingPriceDisplay) {
                        floatingPriceDisplay.classList.add('hidden');
                    }
                    return;
                }
                
                const venueType = selectedOption.getAttribute('data-type');
                let price = 0;
                let description = '';
                
                // Check if package is selected
                if (selectedPackage && selectedPackage.value && venueType === 'venue') {
                    const venueId = selectedOption.value;
                    const packageId = selectedPackage.value;
                    const packageData = venuePackages[venueId]?.find(pkg => pkg.id == packageId);
                    
                    // Check if multiple time slots are selected
                    const multipleSelected = document.querySelector('input[name="time_slot_type"][value="multiple"]')?.checked ?? false;
                    
                    if (multipleSelected) {
                        // Calculate price for selected time slots with package pricing
                        const selectedSlots = [];
                        const timeSlotCheckboxes = document.querySelectorAll('.time-slot-checkbox');
                        let hasSelectedSlots = false;
                        
                        timeSlotCheckboxes.forEach(checkbox => {
                            if (checkbox.checked) {
                                hasSelectedSlots = true;
                                const slot = checkbox.value;
                                selectedSlots.push(slot);
                            }
                        });
                        
                        if (hasSelectedSlots && selectedSlots.length > 0) {
                            // Check if all 3 time slots are selected
                            const hasAllSlots = selectedSlots.includes('morning') && 
                                              selectedSlots.includes('afternoon') && 
                                              selectedSlots.includes('evening');
                            
                            if (hasAllSlots && packageData) {
                                // All 3 slots selected - sum all 3 time slot prices
                                if (packageData.has_time_based_pricing) {
                                    price = (parseFloat(packageData.price_morning) || 0)
                                          + (parseFloat(packageData.price_afternoon) || 0)
                                          + (parseFloat(packageData.price_evening) || 0);
                                } else {
                                    // No time-based pricing — multiply base price by 3 slots
                                    price = parseFloat(packageData.price) * 3;
                                }
                                const packageName = selectedPackage.textContent.split(' - ')[0];
                                description = `${packageName} (Morning + Afternoon + Evening)`;
                            } else {
                                // Calculate price for individual selected slots
                                selectedSlots.forEach(slot => {
                                    if (packageData && packageData.has_time_based_pricing) {
                                        // Use package time-based pricing
                                        switch (slot) {
                                            case 'morning':
                                                price += parseFloat(packageData.price_morning) || parseFloat(packageData.price);
                                                break;
                                            case 'afternoon':
                                                price += parseFloat(packageData.price_afternoon) || parseFloat(packageData.price);
                                                break;
                                            case 'evening':
                                                price += parseFloat(packageData.price_evening) || parseFloat(packageData.price);
                                                break;
                                        }
                                    } else if (packageData) {
                                        // Use package base price for each selected time slot
                                        price += parseFloat(packageData.price);
                                    }
                                });
                                
                                const slotNames = selectedSlots.map(slot => {
                                    switch (slot) {
                                        case 'morning': return 'Morning';
                                        case 'afternoon': return 'Afternoon';
                                        case 'evening': return 'Evening';
                                        default: return slot;
                                    }
                                });
                                
                                const packageName = selectedPackage.textContent.split(' - ')[0];
                                description = `${packageName} (${slotNames.join(' + ')})`;
                            }
                        } else {
                            // No time slots selected yet - show estimated price based on package
                            if (packageData && packageData.has_time_based_pricing) {
                                // Show the lowest time slot price as starting estimate
                                const prices = [
                                    parseFloat(packageData.price_morning) || Infinity,
                                    parseFloat(packageData.price_afternoon) || Infinity,
                                    parseFloat(packageData.price_evening) || Infinity
                                ].filter(p => p !== Infinity);
                                
                                price = prices.length > 0 ? Math.min(...prices) : parseFloat(packageData.price);
                                description = `${selectedPackage.textContent.split(' - ')[0]} (Select time slots)`;
                            } else if (packageData) {
                                price = parseFloat(packageData.price);
                                description = `${selectedPackage.textContent.split(' - ')[0]} (Select time slots)`;
                            } else {
                                description = 'Select time slots';
                                price = 0;
                            }
                        }
                    } else {
                        // Full day package booking - use base package price
                        if (packageData) {
                            // Always use base package price for full day
                            price = parseFloat(packageData.price);
                            description = `${selectedPackage.textContent.split(' - ')[0]} (Full Day)`;
                        } else {
                            // Fallback to extracting price from package option text
                            const packageText = selectedPackage.textContent;
                            const priceMatch = packageText.match(/₱([\d,]+\.?\d*)/);
                            if (priceMatch) {
                                price = parseFloat(priceMatch[1].replace(/,/g, ''));
                                description = `${selectedPackage.textContent.split(' - ')[0]}`;
                            }
                        }
                    }
                } else {
                    // Use venue pricing
                    if (venueType === 'suite') {
                        // Base rate follows the selected rate source (Regular / booking platform)
                        const nightly = window.getSuiteNightlyRate
                            ? window.getSuiteNightlyRate(selectedOption)
                            : { rate: parseFloat(selectedOption.getAttribute('data-price')), label: 'Regular rate' };
                        const basePrice = nightly.rate;
                        const numberOfDaysInput = document.getElementById('number_of_days');
                        const numberOfDays = numberOfDaysInput ? parseInt(numberOfDaysInput.value) || 1 : 1;

                        price = basePrice * numberOfDays;

                        if (numberOfDays > 1) {
                            description = `Suite rental — ${nightly.label} (${numberOfDays} days × ₱${basePrice.toLocaleString('en-PH', {minimumFractionDigits: 2})})`;
                        } else {
                            description = `Suite rental — ${nightly.label} (22 hours)`;
                        }
                    } else {
                        // Check if multiple time slots are selected
                        const multipleSelected = document.querySelector('input[name="time_slot_type"][value="multiple"]')?.checked ?? false;
                        
                        if (multipleSelected) {
                            // Calculate price for selected time slots
                            const selectedSlots = [];
                            const timeSlotCheckboxes = document.querySelectorAll('.time-slot-checkbox');
                            timeSlotCheckboxes.forEach(checkbox => {
                                if (checkbox.checked) {
                                    selectedSlots.push(checkbox.value);
                                }
                            });
                            
                            if (selectedSlots.length > 0) {
                                // Check if all 3 time slots are selected
                                const hasAllSlots = selectedSlots.includes('morning') && 
                                                  selectedSlots.includes('afternoon') && 
                                                  selectedSlots.includes('evening');
                                
                                if (hasAllSlots) {
                                    // All 3 slots selected - use venue base price (full day price)
                                    price = parseFloat(selectedOption.getAttribute('data-price'));
                                    description = 'Full day rental (All Slots)';
                                } else {
                                    // Calculate price for individual selected slots
                                    selectedSlots.forEach(slot => {
                                        switch (slot) {
                                            case 'morning':
                                                price += parseFloat(selectedOption.getAttribute('data-price-morning')) || 0;
                                                break;
                                            case 'afternoon':
                                                price += parseFloat(selectedOption.getAttribute('data-price-afternoon')) || 0;
                                                break;
                                            case 'evening':
                                                price += parseFloat(selectedOption.getAttribute('data-price-evening')) || 0;
                                                break;
                                        }
                                    });
                                    
                                    const slotNames = selectedSlots.map(slot => {
                                        switch (slot) {
                                            case 'morning': return 'Morning';
                                            case 'afternoon': return 'Afternoon';
                                            case 'evening': return 'Evening';
                                            default: return slot;
                                        }
                                    });
                                    
                                    description = slotNames.join(' + ') + ' slots';
                                }
                            } else {
                                description = 'Select time slots';
                                price = 0;
                            }
                        } else {
                            // Full day booking
                            price = parseFloat(selectedOption.getAttribute('data-price'));
                            description = 'Full day rental';
                        }
                    }
                }
                
                // Update floating display
                if (price > 0 && floatingPriceDisplay) {
                    currentVenuePrice = price;
                    currentVenueDescription = description;
                    
                    floatingDisplayPrice.textContent = '₱' + price.toLocaleString('en-PH', {minimumFractionDigits: 2});
                    floatingPriceDescription.textContent = description;
                    
                    // Update venue price in breakdown
                    if (floatingVenueLabel) {
                        floatingVenueLabel.textContent = selectedOption.getAttribute('data-type') === 'suite' ? 'Suite' : 'Venue';
                    }
                    if (floatingVenuePrice) {
                        floatingVenuePrice.textContent = '₱' + price.toLocaleString('en-PH', {minimumFractionDigits: 2});
                    }
                    
                    floatingPriceDisplay.classList.remove('hidden');
                } else {
                    currentVenuePrice = 0;
                    currentVenueDescription = '';
                    if (floatingPriceDisplay) {
                        floatingPriceDisplay.classList.add('hidden');
                    }
                }
                
                updateTotalWithAddons();
            }
            
            // Add-ons category selection
            if (addonCategorySelector) {
                addonCategorySelector.addEventListener('change', function() {
                    const selectedCategory = this.value;
                    
                    // Hide all category sections and the no-category message
                    document.querySelectorAll('.addon-category-section').forEach(section => {
                        section.classList.add('hidden');
                    });
                    noAddonCategoryMessage.classList.add('hidden');
                    
                    if (selectedCategory) {
                        // Show selected category section
                        const categorySection = document.querySelector(`.addon-category-section[data-category="${selectedCategory}"]`);
                        if (categorySection) {
                            categorySection.classList.remove('hidden');
                        }
                    } else {
                        // Show no-category message if nothing is selected
                        noAddonCategoryMessage.classList.remove('hidden');
                    }
                });
            }

            // Handle addon item clicks (make entire card clickable)
            document.addEventListener('click', function(e) {
                const addonItem = e.target.closest('.addon-item');
                if (!addonItem || addonItem.classList.contains('opacity-60')) return;
                
                // Don't trigger if clicking on quantity controls or checkbox itself
                if (e.target.closest('.addon-quantity, .quantity-btn, .addon-quantity-input, .addon-checkbox, .addon-checkbox-custom')) {
                    return;
                }
                
                const checkbox = addonItem.querySelector('.addon-checkbox');
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            // Handle addon checkbox changes
            document.addEventListener('change', function(e) {
                if (!e.target.classList.contains('addon-checkbox')) return;
                
                e.stopPropagation(); // Prevent event bubbling
                
                const checkbox = e.target;
                const addonId = checkbox.dataset.addonId;
                const addonPrice = parseFloat(checkbox.dataset.addonPrice);
                const addonName = checkbox.dataset.addonName;
                const trackStock = checkbox.dataset.trackStock === 'true';
                const stockQuantity = parseInt(checkbox.dataset.stockQuantity);
                const addonItem = checkbox.closest('.addon-item');
                const quantitySection = addonItem.querySelector('.addon-quantity');
                const checkboxCustom = checkbox.parentElement.querySelector('.addon-checkbox-custom');
                const quantityInput = addonItem.querySelector('.addon-quantity-input');
                
                if (checkbox.checked) {
                    // Add addon to selection
                    selectedAddons[addonId] = {
                        name: addonName,
                        price: addonPrice,
                        quantity: 1,
                        trackStock: trackStock,
                        maxQuantity: trackStock ? stockQuantity : 99
                    };
                    
                    // Update UI
                    checkboxCustom.classList.add('bg-orange-600', 'border-orange-600');
                    checkboxCustom.querySelector('i').classList.remove('opacity-0');
                    checkboxCustom.querySelector('i').classList.add('opacity-100');
                    quantitySection.classList.add('expanded');
                    addonItem.classList.add('ring-2', 'ring-orange-300', 'bg-orange-50');
                    
                    // Set max quantity for input
                    quantityInput.setAttribute('max', trackStock ? stockQuantity : 99);
                    
                    // If stock is 1, disable quantity controls
                    if (trackStock && stockQuantity === 1) {
                        quantityInput.value = 1;
                        quantityInput.readOnly = true;
                        addonItem.querySelectorAll('.quantity-btn').forEach(btn => {
                            btn.disabled = true;
                            btn.classList.add('opacity-50', 'cursor-not-allowed');
                        });
                    }
                } else {
                    // Remove addon from selection
                    delete selectedAddons[addonId];
                    
                    // Update UI
                    checkboxCustom.classList.remove('bg-orange-600', 'border-orange-600');
                    checkboxCustom.querySelector('i').classList.remove('opacity-100');
                    checkboxCustom.querySelector('i').classList.add('opacity-0');
                    quantitySection.classList.remove('expanded');
                    addonItem.classList.remove('ring-2', 'ring-orange-300', 'bg-orange-50');
                    
                    // Reset quantity controls
                    quantityInput.readOnly = false;
                    addonItem.querySelectorAll('.quantity-btn').forEach(btn => {
                        btn.disabled = false;
                        btn.classList.remove('opacity-50', 'cursor-not-allowed');
                    });
                }
                
                updateAddonsSummary();
            });

            // Handle custom checkbox clicks
            document.addEventListener('click', function(e) {
                if (e.target.closest('.addon-checkbox-custom')) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const customCheckbox = e.target.closest('.addon-checkbox-custom');
                    const realCheckbox = customCheckbox.parentElement.querySelector('.addon-checkbox');
                    
                    if (realCheckbox && !realCheckbox.closest('.addon-item').classList.contains('opacity-60')) {
                        realCheckbox.checked = !realCheckbox.checked;
                        realCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            });

            // Handle quantity changes
            document.addEventListener('click', function(e) {
                if (e.target.closest('.quantity-plus')) {
                    e.preventDefault(); // Prevent form submission
                    
                    const btn = e.target.closest('.quantity-plus');
                    if (btn.disabled) return;
                    
                    const input = btn.parentElement.querySelector('.addon-quantity-input');
                    const currentValue = parseInt(input.value);
                    const maxValue = parseInt(input.getAttribute('max'));
                    const newValue = Math.min(currentValue + 1, maxValue);
                    
                    if (currentValue >= maxValue) return;
                    
                    input.value = newValue;
                    
                    const addonId = btn.closest('.addon-item').querySelector('.addon-checkbox').dataset.addonId;
                    if (selectedAddons[addonId]) {
                        selectedAddons[addonId].quantity = newValue;
                        updateAddonsSummary();
                    }
                    
                    // Update button states
                    if (newValue >= maxValue) {
                        btn.classList.add('opacity-50', 'cursor-not-allowed');
                        btn.disabled = true;
                    }
                    btn.parentElement.querySelector('.quantity-minus').classList.remove('opacity-50', 'cursor-not-allowed');
                    btn.parentElement.querySelector('.quantity-minus').disabled = false;
                    
                    return false; // Prevent any further event propagation
                }
                
                if (e.target.closest('.quantity-minus')) {
                    e.preventDefault(); // Prevent form submission
                    
                    const btn = e.target.closest('.quantity-minus');
                    if (btn.disabled) return;
                    
                    const input = btn.parentElement.querySelector('.addon-quantity-input');
                    const currentValue = parseInt(input.value);
                    const newValue = Math.max(currentValue - 1, 1);
                    
                    if (currentValue <= 1) return;
                    
                    input.value = newValue;
                    
                    const addonId = btn.closest('.addon-item').querySelector('.addon-checkbox').dataset.addonId;
                    if (selectedAddons[addonId]) {
                        selectedAddons[addonId].quantity = newValue;
                        updateAddonsSummary();
                    }
                    
                    // Update button states
                    if (newValue <= 1) {
                        btn.classList.add('opacity-50', 'cursor-not-allowed');
                        btn.disabled = true;
                    }
                    const maxValue = parseInt(input.getAttribute('max'));
                    if (newValue < maxValue) {
                        btn.parentElement.querySelector('.quantity-plus').classList.remove('opacity-50', 'cursor-not-allowed');
                        btn.parentElement.querySelector('.quantity-plus').disabled = false;
                    }
                    
                    return false; // Prevent any further event propagation
                }
            });

            // Handle quantity input changes
            document.addEventListener('change', function(e) {
                if (!e.target.classList.contains('addon-quantity-input')) return;
                
                e.stopPropagation(); // Prevent event bubbling
                
                const input = e.target;
                if (input.readOnly) return;
                
                const minValue = parseInt(input.getAttribute('min')) || 1;
                const maxValue = parseInt(input.getAttribute('max')) || 99;
                const newValue = Math.max(Math.min(parseInt(input.value) || 1, maxValue), minValue);
                input.value = newValue;
                
                const addonId = input.closest('.addon-item').querySelector('.addon-checkbox').dataset.addonId;
                if (selectedAddons[addonId]) {
                    selectedAddons[addonId].quantity = newValue;
                    updateAddonsSummary();
                }
                
                // Update button states
                const plusBtn = input.parentElement.querySelector('.quantity-plus');
                const minusBtn = input.parentElement.querySelector('.quantity-minus');
                
                if (newValue >= maxValue) {
                    plusBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    plusBtn.disabled = true;
                } else {
                    plusBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    plusBtn.disabled = false;
                }
                
                if (newValue <= minValue) {
                    minusBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    minusBtn.disabled = true;
                } else {
                    minusBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    minusBtn.disabled = false;
                }
            });

            // Update add-ons summary
            function updateAddonsSummary() {
                let addonsAmount = 0;
                let addonsList = '';
                
                Object.values(selectedAddons).forEach(addon => {
                    const subtotal = addon.price * addon.quantity;
                    addonsAmount += subtotal;
                    addonsList += `
                        <div class="flex justify-between text-orange-700 bg-orange-100 p-2 rounded">
                            <span class="font-medium">${addon.name} (${addon.quantity}x)</span>
                            <span class="font-semibold">₱${subtotal.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                        </div>
                    `;
                });
                
                // Update add-ons summary section
                if (addonsTotal) {
                    addonsTotal.textContent = '₱' + addonsAmount.toLocaleString('en-PH', {minimumFractionDigits: 2});
                }
                
                if (Object.keys(selectedAddons).length > 0) {
                    if (addonsSummary) addonsSummary.classList.remove('hidden');
                    if (selectedAddonsList) selectedAddonsList.innerHTML = addonsList;
                } else {
                    if (addonsSummary) addonsSummary.classList.add('hidden');
                }
                
                // Update main price display with breakdown
                updateTotalWithAddons();
            }
            
            // Discount functionality
            function handleDiscountTypeChange() {
                const selectedType = document.querySelector('input[name="discount_type"]:checked').value;
                currentDiscountType = selectedType;
                
                // Update radio button appearance
                discountTypeRadios.forEach(radio => {
                    const customRadio = radio.parentElement.querySelector('.discount-radio-custom');
                    const dot = customRadio.querySelector('div');
                    
                    if (radio.checked) {
                        customRadio.classList.add('border-red-600');
                        dot.classList.remove('opacity-0');
                        dot.classList.add('opacity-100');
                    } else {
                        customRadio.classList.remove('border-red-600');
                        dot.classList.remove('opacity-100');
                        dot.classList.add('opacity-0');
                    }
                });
                
                if (selectedType === '') {
                    // No discount
                    discountValue.disabled = true;
                    discountReason.disabled = true;
                    discountValue.value = '';
                    discountReason.value = '';
                    discountCurrency.classList.add('hidden');
                    discountPercentage.classList.add('hidden');
                    discountHelper.textContent = 'Select discount type to enable this field';
                    discountPreview.classList.add('hidden');
                    currentDiscountAmount = 0;
                    currentDiscountValue = 0;
                } else if (selectedType === 'amount') {
                    // Fixed amount discount
                    discountValue.disabled = false;
                    discountReason.disabled = false;
                    discountCurrency.classList.remove('hidden');
                    discountPercentage.classList.add('hidden');
                    discountHelper.textContent = 'Enter discount amount in Philippine Peso (₱)';
                    discountValue.setAttribute('max', '999999');
                    discountValue.setAttribute('placeholder', '0.00');
                } else if (selectedType === 'percentage') {
                    // Percentage discount
                    discountValue.disabled = false;
                    discountReason.disabled = false;
                    discountCurrency.classList.add('hidden');
                    discountPercentage.classList.remove('hidden');
                    discountHelper.textContent = 'Enter discount percentage (0-100%)';
                    discountValue.setAttribute('max', '100');
                    discountValue.setAttribute('placeholder', '0');
                }
                
                calculateDiscount();
            }
            
            function calculateDiscount() {
                const originalAmount = currentVenuePrice + Object.values(selectedAddons).reduce((sum, addon) => sum + (addon.price * addon.quantity), 0);
                const discountType = document.querySelector('input[name="discount_type"]:checked').value;
                const discountValueInput = parseFloat(discountValue.value) || 0;
                
                currentDiscountValue = discountValueInput;
                
                if (discountType === '' || discountValueInput <= 0) {
                    currentDiscountAmount = 0;
                    discountPreview.classList.add('hidden');
                } else {
                    let discountAmount = 0;
                    
                    if (discountType === 'amount') {
                        discountAmount = Math.min(discountValueInput, originalAmount);
                    } else if (discountType === 'percentage') {
                        const percentage = Math.min(discountValueInput, 100);
                        discountAmount = (originalAmount * percentage) / 100;
                    }
                    
                    currentDiscountAmount = discountAmount;
                    
                    // Update preview
                    previewOriginal.textContent = '₱' + originalAmount.toLocaleString('en-PH', {minimumFractionDigits: 2});
                    previewDiscount.textContent = '₱' + discountAmount.toLocaleString('en-PH', {minimumFractionDigits: 2});
                    previewFinal.textContent = '₱' + (originalAmount - discountAmount).toLocaleString('en-PH', {minimumFractionDigits: 2});
                    
                    discountPreview.classList.remove('hidden');
                }
                
                updateTotalWithAddons();
            }
            
            // Discount event listeners
            discountTypeRadios.forEach(radio => {
                radio.addEventListener('change', function(e) {
                    e.preventDefault(); // Prevent default behavior
                    e.stopPropagation(); // Stop event bubbling
                    handleDiscountTypeChange();
                    return false; // Prevent page movement
                });
            });
            
            // Handle custom radio button clicks
            document.addEventListener('click', function(e) {
                // Handle discount option clicks
                if (e.target.closest('.discount-option')) {
                    e.preventDefault(); // Prevent any default behavior
                    e.stopPropagation(); // Stop event bubbling
                    
                    const discountOption = e.target.closest('.discount-option');
                    const value = discountOption.getAttribute('data-value');
                    const realRadio = discountOption.querySelector('.discount-type-radio');
                    
                    if (realRadio) {
                        // Uncheck all other radio buttons
                        document.querySelectorAll('.discount-type-radio').forEach(radio => {
                            radio.checked = false;
                        });
                        
                        // Check the selected radio button
                        realRadio.checked = true;
                        handleDiscountTypeChange();
                    }
                    
                    return false; // Prevent any page movement
                }
                
                // Handle discount radio custom clicks (fallback)
                if (e.target.closest('.discount-radio-custom')) {
                    e.preventDefault(); // Prevent any default behavior
                    e.stopPropagation(); // Stop event bubbling
                    return false; // Prevent any page movement
                }
            });
            
            discountValue.addEventListener('input', function(e) {
                e.stopPropagation(); // Prevent event bubbling
                calculateDiscount();
            });
            
            // Initialize discount functionality
            handleDiscountTypeChange();
            
            // Handle number of days input change
            const numberOfDaysInput = document.getElementById('number_of_days');
            if (numberOfDaysInput) {
                numberOfDaysInput.addEventListener('input', function() {
                    updatePriceDisplay();
                    updateTotalWithAddons();
                });
            }

            // Re-price when the suite rate source changes
            const rateSourceSelect = document.getElementById('booking_platform_id');
            if (rateSourceSelect) {
                rateSourceSelect.addEventListener('change', function() {
                    updatePriceDisplay();
                    updateTotalWithAddons();
                });
            }

            // Update total price including add-ons and discount
            function updateTotalWithAddons() {
                let addonsAmount = 0;
                let addonsBreakdownHtml = '';
                
                // Calculate add-ons total and build breakdown
                Object.values(selectedAddons).forEach(addon => {
                    const subtotal = addon.price * addon.quantity;
                    addonsAmount += subtotal;
                    addonsBreakdownHtml += `
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-purple-600">${addon.name} (${addon.quantity}x)</span>
                            <span class="font-medium text-purple-700">₱${subtotal.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                        </div>
                    `;
                });
                
                const originalPrice = currentVenuePrice + addonsAmount;
                const finalPrice = originalPrice - currentDiscountAmount;
                
                // Update floating price display
                if (originalPrice > 0 && floatingPriceDisplay) {
                    floatingDisplayPrice.textContent = '₱' + finalPrice.toLocaleString('en-PH', {minimumFractionDigits: 2});
                    
                    // Update description to show components
                    let description = currentVenueDescription;
                    if (addonsAmount > 0) {
                        const addonCount = Object.keys(selectedAddons).length;
                        description += ` + ${addonCount} add-on${addonCount > 1 ? 's' : ''}`;
                    }
                    if (currentDiscountAmount > 0) {
                        description += ` (discount applied)`;
                    }
                    floatingPriceDescription.textContent = description;
                    
                    // Update breakdown
                    if (floatingVenuePrice) {
                        floatingVenuePrice.textContent = '₱' + currentVenuePrice.toLocaleString('en-PH', {minimumFractionDigits: 2});
                    }
                    
                    if (floatingTotalAmount) {
                        floatingTotalAmount.textContent = '₱' + finalPrice.toLocaleString('en-PH', {minimumFractionDigits: 2});
                    }
                    
                    // Show/hide add-ons breakdown
                    if (addonsAmount > 0 && floatingAddonsBreakdownList) {
                        floatingAddonsBreakdownList.innerHTML = addonsBreakdownHtml;
                        floatingSelectedAddonsBreakdown.classList.remove('hidden');
                    } else if (floatingSelectedAddonsBreakdown) {
                        floatingSelectedAddonsBreakdown.classList.add('hidden');
                    }
                    
                    // Add discount line to breakdown if applicable
                    if (currentDiscountAmount > 0) {
                        const discountBreakdownHtml = `
                            <div class="flex justify-between items-center text-xs border-t border-purple-200 pt-2 mt-2">
                                <span class="text-red-600">Discount Applied</span>
                                <span class="font-medium text-red-700">-₱${currentDiscountAmount.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                            </div>
                        `;
                        
                        // Add discount line to the breakdown
                        if (floatingAddonsBreakdownList) {
                            floatingAddonsBreakdownList.innerHTML += discountBreakdownHtml;
                        }
                    }
                    
                    floatingPriceDisplay.classList.remove('hidden');
                } else {
                    if (floatingPriceDisplay) {
                        floatingPriceDisplay.classList.add('hidden');
                    }
                }
            }

            // Time slot controls — scoped to #timeSlotField so clicks always register
            const multipleTimeSlotsContainer = document.getElementById('multipleTimeSlotsContainer');

            function syncTimeSlotTypeUi(radioValue) {
                document.querySelectorAll('#timeSlotField .time-slot-radio').forEach(r => {
                    const isSelected = r.value === radioValue;
                    r.checked = isSelected;
                    const customRadio = r.parentElement?.querySelector('.time-slot-radio-custom');
                    const dot = customRadio?.querySelector('div');
                    if (!customRadio || !dot) return;

                    if (isSelected) {
                        customRadio.classList.add('border-purple-600');
                        dot.classList.remove('opacity-0');
                        dot.classList.add('opacity-100');
                    } else {
                        customRadio.classList.remove('border-purple-600');
                        dot.classList.remove('opacity-100');
                        dot.classList.add('opacity-0');
                    }
                });
            }

            function clearTimeSlotCheckboxes() {
                document.querySelectorAll('#timeSlotField .time-slot-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                    const customCheckbox = checkbox.parentElement?.querySelector('.time-slot-checkbox-custom');
                    const icon = customCheckbox?.querySelector('i');
                    if (!customCheckbox || !icon) return;
                    customCheckbox.classList.remove('bg-purple-600', 'border-purple-600');
                    icon.classList.remove('opacity-100');
                    icon.classList.add('opacity-0');
                });
            }

            function selectTimeSlotType(radioValue) {
                syncTimeSlotTypeUi(radioValue);

                if (!multipleTimeSlotsContainer) return;

                if (radioValue === 'multiple') {
                    multipleTimeSlotsContainer.classList.remove('hidden');
                    updateTimeSlotPrices();
                } else {
                    multipleTimeSlotsContainer.classList.add('hidden');
                    clearTimeSlotCheckboxes();
                }

                updatePriceDisplay();
                updateTotalWithAddons();
            }

            function toggleTimeSlotCheckbox(checkboxOption) {
                const checkbox = checkboxOption.querySelector('.time-slot-checkbox');
                const customCheckbox = checkboxOption.querySelector('.time-slot-checkbox-custom');
                const icon = customCheckbox?.querySelector('i');
                if (!checkbox || !customCheckbox || !icon) return;

                if (!checkbox.checked) {
                    const currentlyChecked = [...document.querySelectorAll('#timeSlotField .time-slot-checkbox:checked')].map(c => c.value);
                    const newSlot = checkbox.value;

                    if (newSlot === 'evening' && currentlyChecked.includes('morning') && !currentlyChecked.includes('afternoon')) {
                        alert('Invalid combination: Morning and Evening cannot be selected without Afternoon. Please select Morning + Afternoon or Afternoon + Evening.');
                        return;
                    }
                    if (newSlot === 'morning' && currentlyChecked.includes('evening') && !currentlyChecked.includes('afternoon')) {
                        alert('Invalid combination: Morning and Evening cannot be selected without Afternoon. Please select Morning + Afternoon or Afternoon + Evening.');
                        return;
                    }
                }

                checkbox.checked = !checkbox.checked;

                if (checkbox.checked) {
                    customCheckbox.classList.add('bg-purple-600', 'border-purple-600');
                    icon.classList.remove('opacity-0');
                    icon.classList.add('opacity-100');
                } else {
                    customCheckbox.classList.remove('bg-purple-600', 'border-purple-600');
                    icon.classList.remove('opacity-100');
                    icon.classList.add('opacity-0');
                }

                updateTimeSlotTotal();
                updatePriceDisplay();
                updateTotalWithAddons();
            }

            if (timeSlotField) {
                timeSlotField.addEventListener('click', function(e) {
                    const typeOption = e.target.closest('.time-slot-option');
                    if (typeOption && timeSlotField.contains(typeOption)) {
                        e.preventDefault();
                        const value = typeOption.getAttribute('data-value');
                        selectTimeSlotType(value === 'multiple' ? 'multiple' : 'full_day');
                        return;
                    }

                    const checkboxOption = e.target.closest('.time-slot-checkbox-option');
                    if (checkboxOption && timeSlotField.contains(checkboxOption)) {
                        e.preventDefault();
                        toggleTimeSlotCheckbox(checkboxOption);
                    }
                });

                timeSlotField.querySelectorAll('.time-slot-radio').forEach(radio => {
                    radio.addEventListener('change', function() {
                        if (this.checked) {
                            selectTimeSlotType(this.value);
                        }
                    });
                });
            }
            
            // Pre-selection logic for venue_id parameter
            @if(isset($selectedVenueId) && $selectedVenueId)
            const selectedVenueId = {{ $selectedVenueId }};
            const selectedOption = venueSelect.querySelector(`option[value="${selectedVenueId}"]`);
            
            if (selectedOption) {
                const venueType = selectedOption.getAttribute('data-type');
                const numberOfDaysFieldPreselect = document.getElementById('numberOfDaysField');
                
                // Set the appropriate filter
                const filterToSelect = venueType === 'venue' ? 'venue' : 'suite';
                const filterInput = document.querySelector(`input[name="venue_type_filter"][value="${filterToSelect}"]`);
                if (filterInput) {
                    filterInput.checked = true;
                }
                
                // Filter venues first
                filterVenues();
                
                // Then select the venue
                venueSelect.value = selectedVenueId;
                
                // Trigger venue selection logic
                if (venueType === 'suite') {
                    timeSlotField.style.display = 'none';
                    suiteInfo.style.display = 'block';
                    packageField.style.display = 'none';
                    addonsSection.style.display = 'none'; // Hide add-ons for suites
                    
                    // Show number of days field for suites
                    if (numberOfDaysFieldPreselect) {
                        numberOfDaysFieldPreselect.style.display = 'block';
                    }
                } else if (venueType === 'venue') {
                    timeSlotField.style.display = 'block';
                    suiteInfo.style.display = 'none';
                    addonsSection.style.display = 'block'; // Show add-ons for venues
                    
                    // Hide number of days field for venues
                    if (numberOfDaysFieldPreselect) {
                        numberOfDaysFieldPreselect.style.display = 'none';
                    }
                    
                    loadPackages(selectedVenueId, venueType);
                    updateTimeSlotPrices(); // Update time slot prices
                }
                
                updatePriceDisplay();
            }
            @endif
            
            // Function to update section title and dropdown text based on filter
            function updateSectionText() {
                const selectedFilter = document.querySelector('input[name="venue_type_filter"]:checked').value;
                
                switch(selectedFilter) {
                    case 'all':
                        sectionTitle.textContent = 'Venue and Suites';
                        dropdownPlaceholder.textContent = 'Choose venue and suites';
                        break;
                    case 'venue':
                        sectionTitle.textContent = 'Venue & Package Selection';
                        dropdownPlaceholder.textContent = 'Select venue';
                        break;
                    case 'suite':
                        sectionTitle.textContent = 'Select Suites';
                        dropdownPlaceholder.textContent = 'Select suites';
                        break;
                }
            }
            
            // Function to update tab appearance
            function updateTabAppearance() {
                filterTabs.forEach(tab => {
                    const input = tab.parentElement.querySelector('input');
                    if (input.checked) {
                        tab.classList.add('active');
                        tab.classList.remove('inactive');
                    } else {
                        tab.classList.remove('active');
                        tab.classList.add('inactive');
                    }
                });
            }
            
            // Function to load packages for selected venue
            function loadPackages(venueId, venueType) {
                packageSelect.innerHTML = '<option value="">Standard Venue Rental</option>';
                
                if (venueType === 'venue' && venuePackages[venueId] && venuePackages[venueId].length > 0) {
                    packageField.style.display = 'block';
                    venuePackages[venueId].forEach(package => {
                        const option = document.createElement('option');
                        option.value = package.id;
                        option.textContent = `${package.name} - ₱${parseFloat(package.price).toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
                        packageSelect.appendChild(option);
                    });
                } else {
                    packageField.style.display = 'none';
                }
                
                updatePriceDisplay();
            }
            
            // Function to filter venue options based on selected type
            function filterVenues() {
                const selectedFilter = document.querySelector('input[name="venue_type_filter"]:checked').value;
                const options = venueSelect.querySelectorAll('.venue-option');
                
                // Reset selection
                venueSelect.value = '';
                timeSlotField.style.display = 'none';
                suiteInfo.style.display = 'none';
                packageField.style.display = 'none';
                addonsSection.style.display = 'none'; // Hide add-ons when filtering
                
                // Reset time slot selections
                const fullDayRadio = document.querySelector('input[name="time_slot_type"][value="full_day"]');
                if (fullDayRadio) {
                    fullDayRadio.checked = true;
                    // Hide multiple slots container
                    const multipleContainer = document.getElementById('multipleTimeSlotsContainer');
                    if (multipleContainer) {
                        multipleContainer.classList.add('hidden');
                    }
                }
                
                // Show/hide options based on filter
                let visibleCount = 0;
                options.forEach(option => {
                    const optionType = option.getAttribute('data-type');
                    
                    if (selectedFilter === 'all') {
                        option.style.display = 'block';
                        visibleCount++;
                    } else if (selectedFilter === optionType) {
                        option.style.display = 'block';
                        visibleCount++;
                    } else {
                        option.style.display = 'none';
                    }
                });
                
                updateTabAppearance();
                updateSectionText(); // Add this line to update text when filter changes
            }
            
            // Add event listeners to radio buttons
            typeFilters.forEach(filter => {
                filter.addEventListener('change', filterVenues);
            });
            
            // Add click handlers to tabs
            filterTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const input = this.parentElement.querySelector('input');
                    input.checked = true;
                    filterVenues();
                });
            });
            
            // Handle venue selection change
            venueSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const venueType = selectedOption.getAttribute('data-type');
                const venueId = this.value;
                const numberOfDaysField = document.getElementById('numberOfDaysField');
                const notesField = document.getElementById('notesField');
                
                console.log('Venue selected:', venueType, 'ID:', venueId);
                console.log('numberOfDaysField element:', numberOfDaysField);
                
                if (venueType === 'suite') {
                    timeSlotField.style.display = 'none';
                    suiteInfo.style.display = 'block';
                    packageField.style.display = 'none';
                    addonsSection.style.display = 'none'; // Hide add-ons for suites
                    
                    // Show number of days field for suites
                    if (numberOfDaysField) {
                        console.log('Showing numberOfDaysField for suite');
                        numberOfDaysField.style.display = 'block';
                    } else {
                        console.error('numberOfDaysField element not found!');
                    }
                    
                    // Reset time slot selections for suites
                    const fullDayRadio = document.querySelector('input[name="time_slot_type"][value="full_day"]');
                    if (fullDayRadio) {
                        fullDayRadio.checked = true;
                        // Trigger the radio button change event
                        fullDayRadio.dispatchEvent(new Event('change'));
                    }
                } else if (venueType === 'venue') {
                    timeSlotField.style.display = 'block';
                    suiteInfo.style.display = 'none';
                    addonsSection.style.display = 'block'; // Show add-ons for venues
                    
                    // Hide number of days field for venues
                    if (numberOfDaysField) {
                        console.log('Hiding numberOfDaysField for venue');
                        numberOfDaysField.style.display = 'none';
                        document.getElementById('number_of_days').value = 1;
                    }
                    
                    loadPackages(venueId, venueType);
                    updateTimeSlotPrices(); // Update time slot prices when venue changes
                } else {
                    // No venue selected
                    timeSlotField.style.display = 'none';
                    suiteInfo.style.display = 'none';
                    packageField.style.display = 'none';
                    addonsSection.style.display = 'none'; // Hide add-ons when no venue selected
                    
                    // Hide number of days field
                    if (numberOfDaysField) {
                        numberOfDaysField.style.display = 'none';
                        document.getElementById('number_of_days').value = 1;
                    }
                }
                
                updatePriceDisplay();
                updateTotalWithAddons(); // Update total price including add-ons
            });
            
            // Update time slot prices based on selected venue and package
            function updateTimeSlotPrices() {
                const selectedOption = venueSelect.options[venueSelect.selectedIndex];
                if (!selectedOption || !selectedOption.value) return;
                
                const selectedPackage = packageSelect.options[packageSelect.selectedIndex];
                let morningPrice, afternoonPrice, eveningPrice;
                
                // Check if a package is selected
                if (selectedPackage && selectedPackage.value) {
                    const venueId = selectedOption.value;
                    const packageId = selectedPackage.value;
                    
                    // Find the selected package data
                    const packageData = venuePackages[venueId]?.find(pkg => pkg.id == packageId);
                    
                    if (packageData && packageData.has_time_based_pricing) {
                        // Use package time-based pricing
                        morningPrice = parseFloat(packageData.price_morning) || parseFloat(packageData.price);
                        afternoonPrice = parseFloat(packageData.price_afternoon) || parseFloat(packageData.price);
                        eveningPrice = parseFloat(packageData.price_evening) || parseFloat(packageData.price);
                    } else if (packageData) {
                        // Use package base price for all time slots
                        morningPrice = afternoonPrice = eveningPrice = parseFloat(packageData.price);
                    } else {
                        // Fallback to venue pricing
                        morningPrice = parseFloat(selectedOption.getAttribute('data-price-morning')) || 0;
                        afternoonPrice = parseFloat(selectedOption.getAttribute('data-price-afternoon')) || 0;
                        eveningPrice = parseFloat(selectedOption.getAttribute('data-price-evening')) || 0;
                    }
                } else {
                    // No package selected, use venue pricing
                    morningPrice = parseFloat(selectedOption.getAttribute('data-price-morning')) || 0;
                    afternoonPrice = parseFloat(selectedOption.getAttribute('data-price-afternoon')) || 0;
                    eveningPrice = parseFloat(selectedOption.getAttribute('data-price-evening')) || 0;
                }
                
                // Update the display
                document.getElementById('morningPrice').textContent = '₱' + morningPrice.toLocaleString('en-PH', {minimumFractionDigits: 0});
                document.getElementById('afternoonPrice').textContent = '₱' + afternoonPrice.toLocaleString('en-PH', {minimumFractionDigits: 0});
                document.getElementById('eveningPrice').textContent = '₱' + eveningPrice.toLocaleString('en-PH', {minimumFractionDigits: 0});
                
                updateTimeSlotTotal();
            }
            
            // Update time slot total
            function updateTimeSlotTotal() {
                const selectedOption = venueSelect.options[venueSelect.selectedIndex];
                if (!selectedOption || !selectedOption.value) return;
                
                const selectedPackage = packageSelect.options[packageSelect.selectedIndex];
                let total = 0;
                
                document.querySelectorAll('#timeSlotField .time-slot-checkbox').forEach(checkbox => {
                    if (checkbox.checked) {
                        const slot = checkbox.value;
                        let slotPrice = 0;
                        
                        // Check if a package is selected
                        if (selectedPackage && selectedPackage.value) {
                            const venueId = selectedOption.value;
                            const packageId = selectedPackage.value;
                            
                            // Find the selected package data
                            const packageData = venuePackages[venueId]?.find(pkg => pkg.id == packageId);
                            
                            if (packageData && packageData.has_time_based_pricing) {
                                // Use package time-based pricing
                                switch (slot) {
                                    case 'morning':
                                        slotPrice = parseFloat(packageData.price_morning) || parseFloat(packageData.price);
                                        break;
                                    case 'afternoon':
                                        slotPrice = parseFloat(packageData.price_afternoon) || parseFloat(packageData.price);
                                        break;
                                    case 'evening':
                                        slotPrice = parseFloat(packageData.price_evening) || parseFloat(packageData.price);
                                        break;
                                }
                            } else if (packageData) {
                                // Use package base price for all time slots
                                slotPrice = parseFloat(packageData.price);
                            } else {
                                // Fallback to venue pricing
                                switch (slot) {
                                    case 'morning':
                                        slotPrice = parseFloat(selectedOption.getAttribute('data-price-morning')) || 0;
                                        break;
                                    case 'afternoon':
                                        slotPrice = parseFloat(selectedOption.getAttribute('data-price-afternoon')) || 0;
                                        break;
                                    case 'evening':
                                        slotPrice = parseFloat(selectedOption.getAttribute('data-price-evening')) || 0;
                                        break;
                                }
                            }
                        } else {
                            // No package selected, use venue pricing
                            switch (slot) {
                                case 'morning':
                                    slotPrice = parseFloat(selectedOption.getAttribute('data-price-morning')) || 0;
                                    break;
                                case 'afternoon':
                                    slotPrice = parseFloat(selectedOption.getAttribute('data-price-afternoon')) || 0;
                                    break;
                                case 'evening':
                                    slotPrice = parseFloat(selectedOption.getAttribute('data-price-evening')) || 0;
                                    break;
                            }
                        }
                        
                        total += slotPrice;
                    }
                });
                
                document.getElementById('timeSlotsTotal').textContent = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits: 0});
            }

            // Handle package change
            packageSelect.addEventListener('change', function() {
                updateTimeSlotPrices(); // Update time slot prices when package changes
                updatePriceDisplay();
                updateTotalWithAddons(); // Update total price including add-ons
            });
            
            // Initialize tab appearance
            updateTabAppearance();
            
            // Initialize section text
            updateSectionText();
        });
        
        // Form submission validation for past dates
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const bookingDateInput = document.getElementById('booking_date');
            const selectedDate = new Date(bookingDateInput.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Reset time to start of day
            
            if (selectedDate < today) {
                e.preventDefault();
                alert('Cannot book past dates. Please select today or a future date.');
                bookingDateInput.focus();
                return false;
            }
        });
        </script>
