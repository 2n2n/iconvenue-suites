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
        
        .venue-filter-tab:hover {
            background-color: #7C3AED;
            color: white;
        }
        
        .venue-filter-tab.inactive:hover {
            background-color: #F3F4F6;
            color: #374151;
        }
        
        /* Add-ons Styles */
        .addon-checkbox-custom {
            transition: all 0.3s ease;
        }

        .addon-checkbox:checked + .addon-checkbox-custom {
            background-color: #EA580C;
            border-color: #EA580C;
        }

        .addon-item {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .addon-item:hover:not(.opacity-60) {
            border-color: #FB923C;
        }

        .addon-item.opacity-60 {
            cursor: not-allowed;
        }

        /* Smooth expand/collapse for quantity section — no scroll jump */
        .addon-quantity {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding-top 0.3s ease, opacity 0.3s ease;
            padding-top: 0;
            opacity: 0;
        }
        .addon-quantity.expanded {
            max-height: 200px;
            padding-top: 0.75rem; /* pt-3 */
            opacity: 1;
        }

        .quantity-btn {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .quantity-btn:hover:not(:disabled) {
            background-color: #EA580C;
            color: white;
        }

        .quantity-btn:disabled {
            cursor: not-allowed;
        }

        .addon-quantity-input:focus {
            outline: none;
            border-color: #EA580C;
            box-shadow: 0 0 0 2px rgba(234, 88, 12, 0.1);
        }

        /* Prevent button from submitting form */
        .quantity-btn[type="button"] {
            background: #e5e7eb;
        }
        
        .quantity-btn[type="button"]:hover:not(:disabled) {
            background-color: #EA580C;
            color: white;
        }
        
        /* Discount Styles */
        .discount-radio-custom {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .discount-type-radio:checked + .discount-radio-custom {
            border-color: #DC2626;
        }

        .discount-type-radio:checked + .discount-radio-custom div {
            opacity: 1;
        }
        
        /* Prevent layout shifts and page jumps */
        .discount-radio-custom:hover {
            border-color: #F87171;
            transform: scale(1.02);
        }
        
        .discount-radio-custom:active {
            transform: scale(0.98);
        }
        
        /* Ensure smooth interactions */
        label[for*="discount"] {
            cursor: pointer;
            user-select: none;
        }
        
        .discount-option {
            cursor: pointer;
            user-select: none;
            padding: 8px;
            border-radius: 6px;
            transition: background-color 0.15s ease;
            position: relative;
        }
        
        .discount-option:hover {
            background-color: #FEF2F2;
        }
        
        .discount-option:active {
            background-color: #FEE2E2;
            transform: translateY(1px);
        }
        
        /* Prevent any unwanted scrolling behavior */
        .discount-type-radio {
            position: absolute;
            opacity: 0;
            pointer-events: none;
            width: 0;
            height: 0;
        }
        
        /* Smooth focus states */
        .discount-option:focus-within {
            outline: 2px solid #DC2626;
            outline-offset: 2px;
        }
        
        /* Prevent layout shifts when showing/hiding elements */
        #discount-preview {
            transition: all 0.25s ease;
            overflow: hidden;
        }
        
        #discount-preview.hidden {
            opacity: 0;
            max-height: 0;
            margin-top: 0;
            padding-top: 0;
            padding-bottom: 0;
        }
        
        #discount-preview:not(.hidden) {
            opacity: 1;
            max-height: 200px;
            margin-top: 1.5rem;
        }
        
        /* Smooth transitions for input states */
        #discount_value, #discount_reason {
            transition: all 0.2s ease;
        }
        
        #discount_value:disabled, #discount_reason:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        /* Prevent form submission on button clicks */
        .discount-option * {
            pointer-events: none;
        }
        
        .discount-option {
            pointer-events: all;
        }
        
        /* Ensure consistent spacing */
        .discount-option .discount-radio-custom {
            flex-shrink: 0;
        }
        
        /* Time Slot Selection Styles */
        .time-slot-radio-custom {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .time-slot-radio:checked + .time-slot-radio-custom {
            border-color: #8B5CF6;
        }

        .time-slot-radio:checked + .time-slot-radio-custom div {
            opacity: 1;
        }
        
        #timeSlotField {
            position: relative;
            z-index: 1;
            pointer-events: auto;
        }

        .time-slot-option {
            cursor: pointer;
            user-select: none;
            padding: 8px;
            border-radius: 6px;
            transition: background-color 0.2s ease;
            pointer-events: auto;
        }
        
        .time-slot-option:hover {
            background-color: #F3F4F6;
        }
        
        .time-slot-checkbox-custom {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .time-slot-checkbox:checked + .time-slot-checkbox-custom {
            background-color: #8B5CF6;
            border-color: #8B5CF6;
        }

        .time-slot-checkbox:checked + .time-slot-checkbox-custom i {
            opacity: 1;
        }
        
        .time-slot-checkbox-option {
            cursor: pointer;
            user-select: none;
            padding: 8px;
            border-radius: 6px;
            transition: background-color 0.2s ease;
            pointer-events: auto;
        }

        .time-slot-checkbox-option * {
            pointer-events: none;
        }
        
        .time-slot-checkbox-option:hover {
            background-color: #F9FAFB;
        }
        
        /* Booking form layout adjustments for floating price widget */
        .booking-form-container {
            max-width: 1200px;
            margin-right: 360px; /* Space for floating price widget (320px + 40px margin) */
            margin-left: 0;
        }
        
        /* Simplified sticky container for suite info only */
        .sticky-price-container {
            position: sticky;
            top: 2rem;
            z-index: 30;
        }
        
        /* Suite info styling */
        .sticky-price-container > div:not(.hidden) {
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        /* Mobile responsiveness */
        @media (max-width: 1023px) {
            .booking-form-container {
                max-width: none;
                margin-right: 0;
                margin-left: 0;
            }
            
            .sticky-price-container {
                position: relative;
                top: auto;
                z-index: auto;
            }
        }
        
        /* Ensure proper spacing on smaller desktop screens */
        @media (min-width: 1024px) and (max-width: 1400px) {
            .booking-form-container {
                margin-right: 340px; /* Slightly less margin for smaller screens */
            }
        }
        
        /* Extra large screens */
        @media (min-width: 1400px) {
            .booking-form-container {
                margin-right: 380px; /* More margin for larger screens */
            }
        }
        </style>
