<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\BookingStatusNotification;
use App\Models\Booking;
use App\Models\Venue;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The app is reachable only on the tailnet, so emails must not reference images by app URL:
 * clients would get a broken logo. The logo is embedded inline (CID) instead.
 */
final class EmbeddedLogoTest extends TestCase
{
    public function test_staff_creates_a_booking_to_send_the_client_an_email_with_the_embedded_logo(): void
    {
        config(['mail.default' => 'array', 'app.url' => 'https://iconvenue.example.ts.net']);
        // In-memory models: the mail view only reads attributes and relations.
        $booking = (new Booking)->forceFill([
            'id' => 1,
            'booking_reference' => 'IVS-2026-0001',
            'client_name' => 'Maria Santos',
            'client_email' => 'maria@example.com',
            'booking_date' => now()->addWeek(),
            'status' => 'confirmed',
            'total_amount' => 5000,
        ]);
        $booking->setRelation('venue', (new Venue)->forceFill(['name' => 'Grand Hall']));
        $booking->setRelation('package', null);

        $sent = null;
        Event::listen(MessageSent::class, function (MessageSent $event) use (&$sent): void {
            $sent = $event->sent;
        });

        Mail::to($booking->client_email)->send(new BookingStatusNotification($booking, 'pending'));

        $this->assertInstanceOf(SentMessage::class, $sent);
        $email = $sent->getSymfonySentMessage()->getOriginalMessage();
        $html = (string) $email->getHtmlBody();

        $this->assertStringContainsString('src="cid:', $html);
        $this->assertStringNotContainsString('ts.net/images/icon-logo.png', $html);
        $this->assertNotEmpty($email->getAttachments(), 'the logo should be attached inline');
    }
}
