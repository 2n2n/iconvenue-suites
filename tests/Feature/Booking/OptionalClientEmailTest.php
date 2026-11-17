<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Client email is optional on bookings; no email → no client notifications, clients grouped by phone.
 * See docs/decisions/2026-11-17-optional-client-email.md.
 */
final class OptionalClientEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Venue $suite;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        Role::create(['name' => 'admin', 'description' => 'Admin']);
        $staffRole = Role::create(['name' => 'staff', 'description' => 'Staff']);
        $this->staff = User::create(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'secret-password', 'role_id' => $staffRole->id, 'is_active' => true]);

        $this->suite = Venue::create([
            'name' => 'Deluxe Suite', 'room_number' => '101', 'type' => 'suite', 'description' => 'Suite',
            'capacity' => 2, 'price_per_day' => 2500, 'amenities' => [], 'images' => [], 'is_active' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function bookingPayload(array $overrides = []): array
    {
        return array_merge([
            'venue_id' => $this->suite->id,
            'client_name' => 'Walk-in Guest',
            'client_email' => '',
            'client_phone' => '09171234567',
            'booking_date' => now()->addDays(2)->toDateString(),
            'number_of_days' => 1,
            'time_slot_type' => 'full_day',
        ], $overrides);
    }

    private function createBooking(array $overrides = []): Booking
    {
        $this->actingAs($this->staff)
            ->post(route('admin.bookings.store'), $this->bookingPayload($overrides))
            ->assertSessionHasNoErrors();

        return Booking::latest('id')->firstOrFail();
    }

    public function test_staff_creates_a_booking_without_an_email_to_serve_a_walk_in_guest(): void
    {
        $booking = $this->createBooking();

        $this->assertNull($booking->client_email);
        $this->assertFalse($booking->hasClientEmail());
        $this->assertSame('tel-09171234567', $booking->client_key);
        Mail::assertNothingSent();
    }

    public function test_staff_creates_a_booking_with_an_email_to_send_the_confirmation(): void
    {
        $booking = $this->createBooking(['client_email' => 'guest@example.com']);

        $this->assertSame('guest@example.com', $booking->client_email);
        Mail::assertSent(\App\Mail\BookingStatusNotification::class, fn ($mail) => $mail->hasTo('guest@example.com'));
    }

    public function test_staff_creates_a_booking_with_an_invalid_email_to_be_told_to_fix_it(): void
    {
        $this->actingAs($this->staff)
            ->post(route('admin.bookings.store'), $this->bookingPayload(['client_email' => 'not-an-email']))
            ->assertSessionHasErrors('client_email');

        $this->assertSame(0, Booking::count());
    }

    public function test_staff_views_a_booking_without_an_email_to_know_the_client_wont_be_notified(): void
    {
        $booking = $this->createBooking();

        $this->actingAs($this->staff)->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('No email — the client won', false);
    }

    public function test_staff_updates_a_booking_to_remove_the_email_when_the_client_has_none(): void
    {
        $booking = $this->createBooking(['client_email' => 'typo@example.com']);

        $this->actingAs($this->staff)->put(route('admin.bookings.update', $booking), [
            'venue_id' => $this->suite->id,
            'client_name' => 'Walk-in Guest',
            'client_email' => '',
            'client_phone' => '09171234567',
            'booking_date' => $booking->booking_date->toDateString(),
            'number_of_days' => 1,
            'time_slot_type' => 'full_day',
            'status' => 'confirmed',
        ])->assertSessionHasNoErrors();

        $this->assertNull($booking->fresh()->client_email);
    }

    public function test_staff_verifies_a_payment_for_a_booking_without_an_email_to_confirm_it_without_errors(): void
    {
        $booking = $this->createBooking();
        $payment = Payment::create([
            'booking_id' => $booking->id, 'amount' => $booking->total_amount,
            'payment_method' => 'Cash', 'status' => 'pending',
        ]);

        $this->actingAs($this->staff)->post(route('admin.payments.verify', $payment))->assertRedirect();

        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame('confirmed', $booking->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_staff_confirms_a_booking_without_an_email_for_tomorrow_to_have_its_reminder_skipped(): void
    {
        $booking = $this->createBooking(['booking_date' => now()->addDay()->toDateString()]);
        $booking->update(['status' => 'confirmed']);

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        $this->assertNull($booking->fresh()->reminder_sent_at);
        Mail::assertNothingSent();
    }

    public function test_staff_opens_a_client_without_an_email_to_see_their_bookings_by_phone(): void
    {
        $booking = $this->createBooking();
        $this->createBooking(['client_name' => 'Ana', 'client_email' => 'ana@example.com', 'client_phone' => '0918',
            'booking_date' => now()->addDays(20)->toDateString()]);

        $this->actingAs($this->staff)->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('No email')
            ->assertSee(route('admin.clients.show', 'tel-09171234567'), false);

        $this->actingAs($this->staff)->get(route('admin.clients.show', 'tel-09171234567'))
            ->assertOk()
            ->assertSee($booking->booking_reference);

        // Adding an email later moves the client to the email key
        $this->actingAs($this->staff)->put(route('admin.clients.update', 'tel-09171234567'), [
            'client_name' => 'Walk-in Guest', 'client_email' => 'walkin@example.com', 'client_phone' => '09171234567',
        ])->assertRedirect(route('admin.clients.show', 'walkin@example.com'));

        $this->assertSame('walkin@example.com', $booking->fresh()->client_email);
    }

    public function test_staff_opens_a_client_whose_phone_has_spaces_and_a_plus_to_see_their_bookings(): void
    {
        $booking = $this->createBooking(['client_phone' => '+63 918 777 8888']);

        $html = $this->actingAs($this->staff)->get(route('admin.clients.index'))->assertOk()->getContent();
        preg_match('#href="([^"]*/admin/clients/[^"]*)"#', $html, $m);
        $this->assertNotEmpty($m, 'View Bookings link not found');

        $this->actingAs($this->staff)->get(html_entity_decode($m[1]))
            ->assertOk()
            ->assertSee($booking->booking_reference);
    }
}
