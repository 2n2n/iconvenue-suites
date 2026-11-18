<?php

namespace App\Console\Commands;

use App\Mail\BookingReminderNotification;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders';

    protected $description = 'Send reminder emails to clients 24 hours before their booking';

    public function handle()
    {
        // Get tomorrow's date
        $tomorrow = Carbon::tomorrow();

        // Find all confirmed bookings for tomorrow that haven't been reminded yet
        $bookings = Booking::with(['venue', 'package', 'payments'])
            ->where('booking_date', $tomorrow->toDateString())
            ->where('status', 'confirmed')
            ->whereNull('reminder_sent_at') // Haven't sent reminder yet
            // Client email is optional; bookings without one get no reminder
            ->whereNotNull('client_email')
            ->where('client_email', '!=', '')
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No bookings found for tomorrow that need reminders.');

            return;
        }

        $this->info("Found {$bookings->count()} booking(s) for tomorrow ({$tomorrow->format('F d, Y')})");

        $successCount = 0;
        $failureCount = 0;

        foreach ($bookings as $booking) {
            try {
                $this->info("Sending reminder to: {$booking->client_name} ({$booking->client_email})");

                // Send the reminder email
                Mail::to($booking->client_email)->send(new BookingReminderNotification($booking));

                // Mark as reminded (we'll create a simple field for this)
                $booking->update(['reminder_sent_at' => now()]);

                $successCount++;
                $this->info('✅ Reminder sent successfully!');

            } catch (\Exception $e) {
                $failureCount++;
                $this->error("❌ Failed to send reminder to {$booking->client_email}: ".$e->getMessage());
                \Log::error("Failed to send booking reminder for booking {$booking->id}: ".$e->getMessage());
            }
        }

        $this->info("\n📊 Summary:");
        $this->info("✅ Successfully sent: {$successCount}");
        if ($failureCount > 0) {
            $this->error("❌ Failed to send: {$failureCount}");
        }

        $this->info('Booking reminder task completed!');
    }
}
