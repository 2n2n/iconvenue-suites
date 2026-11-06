<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Status Update</title>
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
        .status-confirmed { background: #D1FAE5; color: #065F46; }
        .status-completed { background: #E9D5FF; color: #581C87; }
        .status-cancelled { background: #FEE2E2; color: #991B1B; }
        .status-pending { background: #FEF3C7; color: #92400E; }
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
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo-container" style="text-align: center; margin-bottom: 20px;">
                <img src="{{ isset($message) ? $message->embed(public_path('images/icon-logo.png')) : asset('images/icon-logo.png') }}" alt="Icon Venue & Suites" style="max-width: 200px; height: auto; margin-bottom: 10px;">
            </div>
            <h1>Booking Status Update</h1>
        </div>

        <p>Dear {{ $booking->client_name }},</p>

        <p>We're writing to inform you about an update to your booking status.</p>

        <div class="booking-details">
            <h3>Booking Details</h3>
            
            <div class="detail-row">
                <span class="detail-label">Booking Reference:</span>
                <span class="detail-value" style="font-family: monospace; font-weight: bold; color: #8B5CF6;">
                    {{ $booking->booking_reference ?? 'IVS-' . date('Y') . '-' . str_pad($booking->id, 4, '0', STR_PAD_LEFT) }}
                </span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Venue:</span>
                <span class="detail-value">{{ $booking->venue->name }}</span>
            </div>
            
            @if($booking->package)
            <div class="detail-row">
                <span class="detail-label">Package:</span>
                <span class="detail-value">
                    <strong>{{ $booking->package->name }}</strong>
                    @if($booking->package->description)
                    <br><small style="color: #6B7280;">{{ $booking->package->description }}</small>
                    @endif
                </span>
            </div>
            @endif
            
            <div class="detail-row">
                <span class="detail-label">Date:</span>
                <span class="detail-value">{{ $booking->booking_date->format('F d, Y') }}</span>
            </div>
            
            @if($booking->time_slot)
            <div class="detail-row">
                <span class="detail-label">Time Slot:</span>
                <span class="detail-value">{{ ucfirst($booking->time_slot) }}</span>
            </div>
            @endif
            
            @if($booking->discount_amount > 0 || $booking->discount_percentage > 0)
            <div class="detail-row">
                <span class="detail-label">Original Amount:</span>
                <span class="detail-value">₱{{ number_format($booking->original_amount ?? $booking->total_amount, 2) }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Discount Applied:</span>
                <span class="detail-value" style="color: #059669;">
                    @if($booking->discount_percentage > 0)
                        {{ $booking->discount_percentage }}% discount
                        @if($booking->discount_reason)
                            ({{ $booking->discount_reason }})
                        @endif
                        <br><small>-₱{{ number_format($booking->discount_amount, 2) }}</small>
                    @elseif($booking->discount_amount > 0)
                        Fixed discount
                        @if($booking->discount_reason)
                            ({{ $booking->discount_reason }})
                        @endif
                        <br><small>-₱{{ number_format($booking->discount_amount, 2) }}</small>
                    @endif
                </span>
            </div>
            @endif
            
            <div class="detail-row">
                <span class="detail-label">Total Amount:</span>
                <span class="detail-value">₱{{ number_format($booking->total_amount, 2) }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Current Status:</span>
                <span class="detail-value">
                    <span class="status-badge status-{{ $booking->status }}">
                        {{ ucfirst($booking->status) }}
                    </span>
                </span>
            </div>
        </div>

        @if($booking->status == 'confirmed')
        <div style="background: #D1FAE5; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <h4 style="color: #065F46; margin: 0 0 10px 0;">🎉 Great News!</h4>
            <p style="color: #065F46; margin: 0;">Your booking has been confirmed! We're excited to host your event. Please make sure to complete your payment if you haven't already.</p>
        </div>
        @elseif($booking->status == 'completed')
        <div style="background: #E9D5FF; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <h4 style="color: #581C87; margin: 0 0 10px 0;">✅ Event Completed</h4>
            <p style="color: #581C87; margin: 0;">Your event has been successfully completed! Thank you for choosing Icon Venue & Suites. We hope you had a wonderful experience.</p>
        </div>
        @elseif($booking->status == 'cancelled')
        <div style="background: #FEE2E2; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <h4 style="color: #991B1B; margin: 0 0 10px 0;">❌ Booking Cancelled</h4>
            <p style="color: #991B1B; margin: 0;">Unfortunately, your booking has been cancelled. If you have any questions or would like to reschedule, please contact us.</p>
        </div>
        @endif

        <div class="contact-info">
            <h4>Need Help?</h4>
            <p>If you have any questions about your booking, please don't hesitate to contact us:</p>
            <p>
                📧 Email: iconvenueandsuites@gmail.com<br>
                📞 Phone: 0933 866 7716<br>
                💬 We're here to help!
            </p>
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