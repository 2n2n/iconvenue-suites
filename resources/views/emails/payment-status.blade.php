<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status Update</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .email-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #8B5CF6;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #8B5CF6;
            margin-bottom: 10px;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
            margin: 10px 0;
        }
        .status-verified { background: #D1FAE5; color: #065F46; }
        .status-rejected { background: #FEE2E2; color: #991B1B; }
        .booking-details {
            background: #F9FAFB;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #E5E7EB;
        }
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .detail-label {
            font-weight: bold;
            color: #6B7280;
        }
        .detail-value {
            color: #111827;
        }
        .payment-details {
            background: #F0F9FF;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #3B82F6;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #E5E7EB;
            color: #6B7280;
            font-size: 14px;
        }
        .contact-info {
            margin-top: 20px;
            padding: 15px;
            background: #F3F4F6;
            border-radius: 8px;
        }
        .amount-highlight {
            font-size: 18px;
            font-weight: bold;
            color: #059669;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo-container" style="text-align: center; margin-bottom: 20px;">
                <img src="{{ isset($message) ? $message->embed(public_path('images/icon-logo.png')) : asset('images/icon-logo.png') }}" alt="Icon Venue & Suites" style="max-width: 200px; height: auto; margin-bottom: 10px;">
            </div>
            <h1>Payment Status Update</h1>
        </div>

        <p>Dear {{ $payment->booking->client_name }},</p>

        @if($action == 'verified')
        <p>Great news! Your payment has been verified and confirmed. Your booking is now fully secured.</p>
        @elseif($action == 'rejected')
        <p>We need to discuss your recent payment submission. Please contact us to resolve this matter.</p>
        @endif

        <div class="booking-details">
            <h3>Booking Information</h3>
            
            <div class="detail-row">
                <span class="detail-label">Booking Reference:</span>
                <span class="detail-value" style="font-family: monospace; font-weight: bold; color: #8B5CF6;">
                    {{ $payment->booking->booking_reference ?? 'IVS-' . date('Y') . '-' . str_pad($payment->booking->id, 4, '0', STR_PAD_LEFT) }}
                </span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Venue:</span>
                <span class="detail-value">{{ $payment->booking->venue->name }}</span>
            </div>
            
            @if($payment->booking->package)
            <div class="detail-row">
                <span class="detail-label">Package:</span>
                <span class="detail-value">
                    <strong>{{ $payment->booking->package->name }}</strong>
                    @if($payment->booking->package->description)
                    <br><small style="color: #6B7280;">{{ $payment->booking->package->description }}</small>
                    @endif
                </span>
            </div>
            @endif
            
            <div class="detail-row">
                <span class="detail-label">Date:</span>
                <span class="detail-value">{{ $payment->booking->booking_date->format('F d, Y') }}</span>
            </div>
            
            @if($payment->booking->time_slot)
            <div class="detail-row">
                <span class="detail-label">Time Slot:</span>
                <span class="detail-value">{{ ucfirst($payment->booking->time_slot) }}</span>
            </div>
            @endif
            
            @if($payment->booking->discount_amount > 0 || $payment->booking->discount_percentage > 0)
            <div class="detail-row">
                <span class="detail-label">Original Amount:</span>
                <span class="detail-value">₱{{ number_format($payment->booking->original_amount ?? $payment->booking->total_amount, 2) }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Discount Applied:</span>
                <span class="detail-value" style="color: #059669;">
                    @if($payment->booking->discount_percentage > 0)
                        {{ $payment->booking->discount_percentage }}% discount
                        @if($payment->booking->discount_reason)
                            ({{ $payment->booking->discount_reason }})
                        @endif
                        <br><small>-₱{{ number_format($payment->booking->discount_amount, 2) }}</small>
                    @elseif($payment->booking->discount_amount > 0)
                        Fixed discount
                        @if($payment->booking->discount_reason)
                            ({{ $payment->booking->discount_reason }})
                        @endif
                        <br><small>-₱{{ number_format($payment->booking->discount_amount, 2) }}</small>
                    @endif
                </span>
            </div>
            @endif
            
            <div class="detail-row">
                <span class="detail-label">Total Booking Amount:</span>
                <span class="detail-value amount-highlight">₱{{ number_format($payment->booking->total_amount, 2) }}</span>
            </div>
        </div>

        <div class="payment-details">
            <h3>💳 Payment Details</h3>
            
            <div class="detail-row">
                <span class="detail-label">Payment Amount:</span>
                <span class="detail-value amount-highlight">₱{{ number_format($payment->amount, 2) }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Payment Method:</span>
                <span class="detail-value">{{ ucfirst($payment->payment_method) }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Payment Date:</span>
                <span class="detail-value">{{ $payment->created_at->format('F d, Y') }}</span>
            </div>
            
            @if($payment->reference_number)
            <div class="detail-row">
                <span class="detail-label">Reference Number:</span>
                <span class="detail-value" style="font-family: monospace;">{{ $payment->reference_number }}</span>
            </div>
            @endif
            
            <div class="detail-row">
                <span class="detail-label">Payment Status:</span>
                <span class="detail-value">
                    <span class="status-badge status-{{ $action }}">
                        @if($action == 'verified') Verified ✓
                        @elseif($action == 'rejected') Needs Review
                        @endif
                    </span>
                </span>
            </div>
        </div>

        @if($action == 'verified')
        <div style="background: #D1FAE5; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <h4 style="color: #065F46; margin: 0 0 10px 0;">✅ Payment Verified!</h4>
            <p style="color: #065F46; margin: 0;">Excellent! Your payment has been verified and your booking is now fully confirmed. We look forward to hosting your event!</p>
        </div>
        @elseif($action == 'rejected')
        <div style="background: #FEE2E2; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <h4 style="color: #991B1B; margin: 0 0 10px 0;">⚠️ Payment Review Required</h4>
            <p style="color: #991B1B; margin: 0;">There seems to be an issue with your payment submission. Please contact us immediately to resolve this matter and secure your booking.</p>
        </div>
        @endif

        <!-- Payment Summary -->
        @php
            $totalPaid = $payment->booking->payments->where('status', 'verified')->sum('amount');
            $remainingBalance = max(0, $payment->booking->total_amount - $totalPaid);
            $paymentPercentage = $payment->booking->total_amount > 0 ? ($totalPaid / $payment->booking->total_amount) * 100 : 0;
        @endphp

        <div class="payment-details">
            <h3>💰 Payment Summary</h3>
            
            <div class="detail-row">
                <span class="detail-label">Total Booking Amount:</span>
                <span class="detail-value">₱{{ number_format($payment->booking->total_amount, 2) }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Total Verified Payments:</span>
                <span class="detail-value" style="color: #059669;">₱{{ number_format($totalPaid, 2) }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Remaining Balance:</span>
                <span class="detail-value" style="color: {{ $remainingBalance > 0 ? '#DC2626' : '#059669' }}; font-weight: bold;">
                    ₱{{ number_format($remainingBalance, 2) }}
                    @if($remainingBalance <= 0) (Fully Paid) @endif
                </span>
            </div>
            
            @if($remainingBalance > 0)
            <div class="detail-row">
                <span class="detail-label">Payment Progress:</span>
                <span class="detail-value">
                    <div style="background: #E5E7EB; border-radius: 10px; height: 8px; margin: 5px 0;">
                        <div style="background: #059669; height: 8px; border-radius: 10px; width: {{ min(100, $paymentPercentage) }}%;"></div>
                    </div>
                    <small style="color: #6B7280;">{{ number_format($paymentPercentage, 1) }}% completed</small>
                </span>
            </div>
            @endif
        </div>

        @if($remainingBalance > 0)
        <!-- Outstanding Balance Discussion -->
        <div style="background: #FEF3C7; border-left: 4px solid #F59E0B; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h4 style="color: #92400E; margin: 0 0 15px 0;">💳 Outstanding Balance Discussion</h4>
            
            @if($paymentPercentage < 25)
            <p style="color: #92400E; margin: 0 0 10px 0;">
                <strong>Initial Payment Received:</strong> Thank you for your payment of ₱{{ number_format($payment->amount, 2) }}! 
                You still have a significant balance of <strong>₱{{ number_format($remainingBalance, 2) }}</strong> remaining.
            </p>
            <p style="color: #92400E; margin: 0 0 10px 0;">
                <strong>Next Steps:</strong> To secure your booking, we recommend making additional payments as soon as possible. 
                You can pay in installments or settle the full remaining amount.
            </p>
            @elseif($paymentPercentage < 50)
            <p style="color: #92400E; margin: 0 0 10px 0;">
                <strong>Good Progress:</strong> You've paid {{ number_format($paymentPercentage, 1) }}% of your booking! 
                Your remaining balance is <strong>₱{{ number_format($remainingBalance, 2) }}</strong>.
            </p>
            <p style="color: #92400E; margin: 0 0 10px 0;">
                <strong>Recommendation:</strong> You're making good progress! Consider scheduling your next payment to stay on track 
                for your event on {{ $payment->booking->booking_date->format('F d, Y') }}.
            </p>
            @elseif($paymentPercentage < 75)
            <p style="color: #92400E; margin: 0 0 10px 0;">
                <strong>Great Progress:</strong> You're more than halfway there! Only <strong>₱{{ number_format($remainingBalance, 2) }}</strong> 
                remaining to complete your booking payment.
            </p>
            <p style="color: #92400E; margin: 0 0 10px 0;">
                <strong>Almost There:</strong> With {{ number_format($paymentPercentage, 1) }}% already paid, you're very close to completing 
                your payment. We recommend settling the remaining balance soon to avoid any last-minute concerns.
            </p>
            @else
            <p style="color: #92400E; margin: 0 0 10px 0;">
                <strong>Final Stretch:</strong> Excellent! You've paid {{ number_format($paymentPercentage, 1) }}% of your booking. 
                Only <strong>₱{{ number_format($remainingBalance, 2) }}</strong> left to go!
            </p>
            <p style="color: #92400E; margin: 0 0 10px 0;">
                <strong>Final Payment:</strong> You're so close to completing your payment! This small remaining balance can be settled 
                anytime before your event date.
            </p>
            @endif
            
            <div style="background: #FEF9C3; padding: 15px; border-radius: 6px; margin-top: 15px;">
                <p style="color: #92400E; margin: 0; font-size: 14px;">
                    <strong>💡 Payment Options:</strong><br>
                    • Pay online through our secure payment portal<br>
                    • Bank transfer to our account<br>
                    • Cash payment at our office<br>
                    • Installment payments (contact us to arrange)
                </p>
            </div>
            
            @php
                $daysUntilEvent = (int) \Carbon\Carbon::today()->diffInDays($payment->booking->booking_date, false);
            @endphp
            @if($daysUntilEvent >= 0 && $daysUntilEvent <= 7)
            <div style="background: #FEE2E2; padding: 15px; border-radius: 6px; margin-top: 10px; border: 1px solid #FECACA;">
                <p style="color: #991B1B; margin: 0; font-size: 14px; font-weight: bold;">
                    ⚠️ <strong>Event Date Approaching:</strong> Your event is in {{ $daysUntilEvent }} day(s). 
                    Please settle the remaining balance as soon as possible to ensure everything is ready for your special day!
                </p>
            </div>
            @elseif($daysUntilEvent < 0)
            <div style="background: #FEE2E2; padding: 15px; border-radius: 6px; margin-top: 10px; border: 1px solid #FECACA;">
                <p style="color: #991B1B; margin: 0; font-size: 14px; font-weight: bold;">
                    🚨 <strong>Event Date Passed:</strong> Your event date was {{ $payment->booking->booking_date->format('F d, Y') }}. 
                    Please contact us immediately to discuss the outstanding balance of ₱{{ number_format($remainingBalance, 2) }}.
                </p>
            </div>
            @endif
        </div>
        @else
        <!-- Fully Paid Celebration -->
        <div style="background: #D1FAE5; border-left: 4px solid #10B981; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h4 style="color: #065F46; margin: 0 0 10px 0;">🎉 Payment Complete!</h4>
            <p style="color: #065F46; margin: 0;">
                Congratulations! Your booking is now <strong>fully paid</strong>. We're all set for your event on 
                {{ $payment->booking->booking_date->format('F d, Y') }}. Thank you for choosing Icon Venue & Suites!
            </p>
        </div>
        @endif

        <div class="contact-info">
            <h4>Need Help with Your Payment?</h4>
            <p>If you have any questions about your payment, remaining balance, or need assistance with payment options, please don't hesitate to contact us:</p>
            <p>
                📧 <strong>Email:</strong> iconvenueandsuites@gmail.com<br>
                📞 <strong>Phone:</strong> 0933 866 7716<br>
                💬 <strong>Payment Support:</strong> We're here to help with flexible payment arrangements!
            </p>
            
            @if($remainingBalance > 0)
            <div style="background: #F0F9FF; padding: 15px; border-radius: 6px; margin-top: 15px; border: 1px solid #BAE6FD;">
                <p style="color: #0369A1; margin: 0; font-size: 14px;">
                    <strong>💡 Quick Payment Tips:</strong><br>
                    • Reference your booking ID: <strong>{{ $payment->booking->booking_reference ?? 'IVS-' . date('Y') . '-' . str_pad($payment->booking->id, 4, '0', STR_PAD_LEFT) }}</strong><br>
                    • Mention your name: <strong>{{ $payment->booking->client_name }}</strong><br>
                    • Outstanding amount: <strong>₱{{ number_format($remainingBalance, 2) }}</strong>
                </p>
            </div>
            @endif
        </div>

        <div class="footer">
            <div style="text-align: center; margin-bottom: 15px;">
                <img src="{{ isset($message) ? $message->embed(public_path('images/icon-logo.png')) : asset('images/icon-logo.png') }}" alt="Icon Venue & Suites" style="max-width: 120px; height: auto; opacity: 0.7;">
            </div>
            <p>Thank you for choosing Icon Venue & Suites!</p>
            <p>&copy; {{ date('Y') }} Icon Venue & Suites. All rights reserved.</p>
        </div>
    </div>
</body>
</html>