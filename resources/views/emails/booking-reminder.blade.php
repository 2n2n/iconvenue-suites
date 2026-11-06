<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Reminder - Tomorrow!</title>
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
        .reminder-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: bold;
            font-size: 16px;
            margin: 15px 0;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
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
        .highlight-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
        }
        .checklist {
            background: #F0F9FF;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #3B82F6;
        }
        .checklist ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .checklist li {
            margin: 8px 0;
            color: #1E40AF;
        }
        .contact-info {
            background: #F3F4F6;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #E5E7EB;
            color: #6B7280;
            font-size: 14px;
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
            <h1>Your Event is Tomorrow!</h1>
            <div class="reminder-badge">
                📅 24-Hour Reminder
            </div>
        </div>

        <p>Dear {{ $booking->client_name }},</p>

        <p>We're excited to remind you that your special event is happening <strong>tomorrow</strong>! We wanted to reach out to ensure everything is ready for your big day.</p>

        <div class="highlight-box">
            <h3 style="margin: 0 0 10px 0;">🎉 Your Event Details</h3>
            <p style="margin: 0; font-size: 18px;">
                <strong>{{ $booking->venue->name }}</strong><br>
                {{ $booking->booking_date->format('l, F d, Y') }}
                @if($booking->time_slot)
                <br>{{ ucfirst($booking->time_slot) }} Session
                @endif
            </p>
        </div>

        <div class="booking-details">
            <h3>📋 Booking Summary</h3>
            
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
                <span class="detail-label">Date & Time:</span>
                <span class="detail-value">
                    <strong>{{ $booking->booking_date->format('F d, Y') }}</strong>
                    @if($booking->time_slot)
                    <br>{{ ucfirst($booking->time_slot) }} 
                    @if($booking->time_slot == 'morning') (8AM - 12PM)
                    @elseif($booking->time_slot == 'afternoon') (1PM - 5PM)
                    @elseif($booking->time_slot == 'evening') (6PM - 10PM)
                    @endif
                    @else
                    <br>Full Day Event
                    @endif
                </span>
            </div>
            
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
            
            <div class="detail-row">
                <span class="detail-label">Total Amount:</span>
                <span class="detail-value amount-highlight">₱{{ number_format($booking->total_amount, 2) }}</span>
            </div>
            @else
            <div class="detail-row">
                <span class="detail-label">Total Amount:</span>
                <span class="detail-value amount-highlight">₱{{ number_format($booking->total_amount, 2) }}</span>
            </div>
            @endif
            
            <div class="detail-row">
                <span class="detail-label">Booking Status:</span>
                <span class="detail-value">
                    <span style="color: #059669; font-weight: bold;">✓ {{ ucfirst($booking->status) }}</span>
                </span>
            </div>
        </div>

        @php
            $totalPaid = $booking->payments->where('status', 'verified')->sum('amount');
            $remainingBalance = max(0, $booking->total_amount - $totalPaid);
        @endphp

        @if($remainingBalance > 0)
        <div style="background: #FEF3C7; border-left: 4px solid #F59E0B; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h4 style="color: #92400E; margin: 0 0 10px 0;">💳 Payment Reminder</h4>
            <p style="color: #92400E; margin: 0 0 10px 0;">
                <strong>Outstanding Balance:</strong> ₱{{ number_format($remainingBalance, 2) }}<br>
                <strong>Total Paid:</strong> ₱{{ number_format($totalPaid, 2) }} of ₱{{ number_format($booking->total_amount, 2) }}
            </p>
            <p style="color: #92400E; margin: 0;">
                Please settle any remaining balance before your event tomorrow to ensure a smooth experience.
            </p>
        </div>
        @else
        <div style="background: #D1FAE5; border-left: 4px solid #10B981; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h4 style="color: #065F46; margin: 0 0 10px 0;">✅ Payment Complete</h4>
            <p style="color: #065F46; margin: 0;">
                Great news! Your booking is fully paid (₱{{ number_format($booking->total_amount, 2) }}). You're all set for tomorrow!
            </p>
        </div>
        @endif

        <div class="checklist">
            <h4 style="color: #1E40AF; margin: 0 0 15px 0;">📝 Pre-Event Checklist</h4>
            <ul>
                <li><strong>Arrival Time:</strong> 
                    @if($booking->venue->type == 'suite')
                        Please arrive at 2:00 PM for check-in
                    @elseif($booking->time_slot == 'morning')
                        Please arrive by 7:45 AM for setup
                    @elseif($booking->time_slot == 'afternoon')
                        Please arrive by 12:45 PM for setup
                    @elseif($booking->time_slot == 'evening')
                        Please arrive by 5:45 PM for setup
                    @else
                        Please arrive by 7:45 AM for full day setup
                    @endif
                </li>
                <li><strong>Contact Person:</strong> Have our contact number ready: 0933 866 7716</li>
                <li><strong>Special Requests:</strong> Confirm any last-minute changes or special arrangements</li>
                @if($remainingBalance > 0)
                <li><strong>Payment:</strong> Settle remaining balance of ₱{{ number_format($remainingBalance, 2) }}</li>
                @endif
                <li><strong>Guest Count:</strong> Confirm final number of attendees</li>
                <li><strong>Setup Requirements:</strong> Review any specific setup needs with our team</li>
            </ul>
        </div>

        @if($booking->venue->type == 'suite')
        <div style="background: #EBF8FF; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #BAE6FD;">
            <h4 style="color: #1E40AF; margin: 0 0 10px 0;">🏨 Suite Information</h4>
            <p style="color: #1E40AF; margin: 0;">
                <strong>Check-in:</strong> 2:00 PM tomorrow<br>
                <strong>Check-out:</strong> 12:00 PM (day after)<br>
                <strong>Duration:</strong> 22-hour stay<br>
                Please bring a valid ID for check-in.
            </p>
        </div>
        @endif

        <div class="contact-info">
            <h4>Need Assistance?</h4>
            <p>If you have any questions or need to make last-minute changes, please contact us immediately:</p>
            <p>
                📧 <strong>Email:</strong> iconvenueandsuites@gmail.com<br>
                📞 <strong>Phone:</strong> 0933 866 7716<br>
                📱 <strong>Emergency Contact:</strong> Available 24/7 for urgent matters
            </p>
        </div>

        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin: 20px 0; text-align: center;">
            <h3 style="margin: 0 0 10px 0;">🎊 We Can't Wait to Host You!</h3>
            <p style="margin: 0;">
                Our team is excited and ready to make your event tomorrow absolutely memorable. 
                Thank you for choosing Icon Venue & Suites!
            </p>
        </div>

        <div class="footer">
            <div style="text-align: center; margin-bottom: 15px;">
                <img src="{{ isset($message) ? $message->embed(public_path('images/icon-logo.png')) : asset('images/icon-logo.png') }}" alt="Icon Venue & Suites" style="max-width: 120px; height: auto; opacity: 0.7;">
            </div>
            <p>See you tomorrow!</p>
            <p>&copy; {{ date('Y') }} Icon Venue & Suites. All rights reserved.</p>
        </div>
    </div>
</body>
</html>