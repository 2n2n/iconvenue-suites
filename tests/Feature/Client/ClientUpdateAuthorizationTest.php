<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Only admins can update client details; staff can still view clients.
 * See docs/decisions/2026-11-18-admin-only-client-update.md.
 */
final class ClientUpdateAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'admin', 'description' => 'Admin']);
        $staffRole = Role::create(['name' => 'staff', 'description' => 'Staff']);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret-password', 'role_id' => $adminRole->id, 'is_active' => true]);
        $this->staff = User::create(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'secret-password', 'role_id' => $staffRole->id, 'is_active' => true]);

        $suite = Venue::create([
            'name' => 'Deluxe Suite', 'room_number' => '101', 'type' => 'suite', 'description' => 'Suite',
            'capacity' => 2, 'price_per_day' => 2500, 'amenities' => [], 'images' => [], 'is_active' => true,
        ]);

        $this->booking = Booking::create([
            'venue_id' => $suite->id,
            'client_name' => 'Juan Dela Cruz', 'client_email' => 'juan@example.com', 'client_phone' => '09171234567',
            'booking_date' => now()->addDays(3)->toDateString(), 'end_date' => now()->addDays(3)->toDateString(),
            'number_of_days' => 1, 'total_amount' => 2500, 'status' => 'pending', 'payment_status' => 'unpaid',
            'staff_id' => $this->admin->id,
        ]);
    }

    /** @return array<string, string> */
    private function payload(): array
    {
        return ['client_name' => 'Hacked Name', 'client_email' => 'other@example.com', 'client_phone' => '0000'];
    }

    public function test_staff_is_refused_when_updating_a_client_directly_to_keep_client_records_admin_controlled(): void
    {
        $this->actingAs($this->staff)
            ->put(route('admin.clients.update', 'juan@example.com'), $this->payload())
            ->assertForbidden();

        $this->booking->refresh();
        $this->assertSame('Juan Dela Cruz', $this->booking->client_name);
        $this->assertSame('juan@example.com', $this->booking->client_email);
        $this->assertSame('09171234567', $this->booking->client_phone);
    }

    public function test_staff_views_a_client_to_see_their_bookings(): void
    {
        $this->actingAs($this->staff)
            ->get(route('admin.clients.show', 'juan@example.com'))
            ->assertOk()
            ->assertSee('Juan Dela Cruz');
    }

    public function test_admin_updates_a_clients_details_to_keep_their_contact_information_correct(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.clients.update', 'juan@example.com'), [
                'client_name' => 'Juan D. Cruz', 'client_email' => 'juan.cruz@example.com', 'client_phone' => '09179999999',
            ])
            ->assertRedirect(route('admin.clients.show', 'juan.cruz@example.com'));

        $this->booking->refresh();
        $this->assertSame('Juan D. Cruz', $this->booking->client_name);
        $this->assertSame('juan.cruz@example.com', $this->booking->client_email);
    }
}
