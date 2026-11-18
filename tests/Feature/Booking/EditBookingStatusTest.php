<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Editing a booking keeps its status; a pending booking is no longer confirmed by an edit.
 * See docs/decisions/2026-11-18-edit-keeps-pending-status.md.
 */
final class EditBookingStatusTest extends TestCase
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

    private function pendingBooking(): Booking
    {
        $this->actingAs($this->staff)->post(route('admin.bookings.store'), [
            'venue_id' => $this->suite->id,
            'client_name' => 'Juan', 'client_email' => 'juan@example.com', 'client_phone' => '0917',
            'booking_date' => now()->addDays(4)->toDateString(), 'number_of_days' => 1,
            'time_slot_type' => 'full_day',
        ])->assertSessionHasNoErrors();

        $booking = Booking::latest('id')->firstOrFail();
        $this->assertSame('pending', $booking->status);

        return $booking;
    }

    /** @param array<string, mixed> $overrides */
    private function updatePayload(Booking $booking, array $overrides = []): array
    {
        return array_merge([
            'venue_id' => $this->suite->id,
            'client_name' => $booking->client_name,
            'client_email' => $booking->client_email,
            'client_phone' => $booking->client_phone,
            'booking_date' => $booking->booking_date->toDateString(),
            'number_of_days' => 1,
            'time_slot_type' => 'full_day',
            'status' => $booking->status,
            'notes' => 'Late arrival',
        ], $overrides);
    }

    public function test_staff_edits_a_pending_booking_to_keep_it_pending_until_payment_is_verified(): void
    {
        $booking = $this->pendingBooking();

        // The edit form pre-selects the current status, including Pending
        $this->actingAs($this->staff)->get(route('admin.bookings.edit', $booking))
            ->assertOk()
            ->assertSee('<option value="pending" selected>', false);

        $this->actingAs($this->staff)
            ->put(route('admin.bookings.update', $booking), $this->updatePayload($booking))
            ->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame('pending', $booking->status);
        $this->assertSame('Late arrival', $booking->notes);
    }

    public function test_staff_edits_a_confirmed_booking_to_keep_it_confirmed(): void
    {
        $booking = $this->pendingBooking();
        $booking->update(['status' => 'confirmed']);

        $this->actingAs($this->staff)->get(route('admin.bookings.edit', $booking))
            ->assertOk()
            ->assertSee('<option value="confirmed" selected>', false)
            ->assertDontSee('<option value="pending" selected>', false);

        $this->actingAs($this->staff)
            ->put(route('admin.bookings.update', $booking), $this->updatePayload($booking))
            ->assertSessionHasNoErrors();

        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_staff_submits_an_unknown_status_to_be_told_it_is_invalid(): void
    {
        $booking = $this->pendingBooking();

        $this->actingAs($this->staff)
            ->put(route('admin.bookings.update', $booking), $this->updatePayload($booking, ['status' => 'archived']))
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $booking->fresh()->status);
    }
}
