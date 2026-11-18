<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use App\Services\Venue\SuiteRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Suite pricing by rate source (Regular / booking platform).
 * See docs/decisions/2026-11-17-suite-platform-rates.md.
 */
final class SuitePlatformRatePricingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private Venue $suite;

    private BookingPlatform $agoda;

    private BookingPlatform $bookingCom;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $adminRole = Role::create(['name' => 'admin', 'description' => 'Admin']);
        $staffRole = Role::create(['name' => 'staff', 'description' => 'Staff']);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret-password', 'role_id' => $adminRole->id, 'is_active' => true]);
        $this->staff = User::create(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'secret-password', 'role_id' => $staffRole->id, 'is_active' => true]);

        $this->suite = Venue::create([
            'name' => 'Deluxe Suite', 'room_number' => '101', 'type' => 'suite', 'description' => 'Suite',
            'capacity' => 2, 'price_per_day' => 2500, 'amenities' => [], 'images' => [], 'is_active' => true,
        ]);

        // The migration seeds Agoda and Booking.com (production never runs db:seed).
        $this->agoda = BookingPlatform::where('name', 'Agoda')->firstOrFail();
        $this->bookingCom = BookingPlatform::where('name', 'Booking.com')->firstOrFail();

        app(SuiteRateService::class)->syncRates($this->suite, [$this->agoda->id => 2800]);
    }

    /** @param array<string, mixed> $overrides */
    private function bookSuite(array $overrides = []): Booking
    {
        $this->actingAs($this->staff)
            ->post(route('admin.bookings.store'), array_merge([
                'venue_id' => $this->suite->id,
                'client_name' => 'Juan Dela Cruz',
                'client_email' => 'juan@example.com',
                'client_phone' => '09170000000',
                'booking_date' => now()->addDays(3)->toDateString(),
                'number_of_days' => 2,
                'time_slot_type' => 'full_day',
            ], $overrides))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return Booking::latest('id')->firstOrFail();
    }

    public function test_admin_finds_default_platforms_after_a_deploy_to_price_rooms_without_seeding(): void
    {
        $this->assertSame(['Agoda', 'Booking.com'], BookingPlatform::ordered()->pluck('name')->all());
    }

    public function test_staff_books_a_suite_at_the_regular_rate_to_charge_walk_in_pricing(): void
    {
        $booking = $this->bookSuite();

        $this->assertNull($booking->booking_platform_id);
        $this->assertSame('2500.00', (string) $booking->rate_per_night);
        $this->assertSame('5000.00', (string) $booking->total_amount);
        $this->assertSame('Regular', $booking->rate_source_label);
    }

    public function test_staff_books_a_suite_at_a_platform_rate_to_charge_the_channel_price(): void
    {
        $booking = $this->bookSuite(['booking_platform_id' => $this->agoda->id, 'platform_reference' => 'AG-123456']);

        $this->assertSame($this->agoda->id, $booking->booking_platform_id);
        $this->assertSame('2800.00', (string) $booking->rate_per_night);
        $this->assertSame('5600.00', (string) $booking->total_amount);
        $this->assertSame('AG-123456', $booking->platform_reference);
    }

    public function test_staff_books_a_suite_on_a_platform_without_a_room_rate_to_fall_back_to_the_regular_rate(): void
    {
        $booking = $this->bookSuite(['booking_platform_id' => $this->bookingCom->id]);

        $this->assertSame($this->bookingCom->id, $booking->booking_platform_id);
        $this->assertSame('2500.00', (string) $booking->rate_per_night);
        $this->assertSame('5000.00', (string) $booking->total_amount);
    }

    public function test_staff_applies_a_discount_to_a_platform_rate_booking_to_reduce_the_total(): void
    {
        $booking = $this->bookSuite([
            'booking_platform_id' => $this->agoda->id,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'discount_reason' => 'Loyal guest',
        ]);

        $this->assertSame('5600.00', (string) $booking->original_amount);
        $this->assertSame('560.00', (string) $booking->discount_amount);
        $this->assertSame('5040.00', (string) $booking->total_amount);
    }

    public function test_staff_books_with_an_inactive_platform_to_be_stopped_by_validation(): void
    {
        $this->agoda->update(['is_active' => false]);

        $this->actingAs($this->staff)
            ->post(route('admin.bookings.store'), [
                'venue_id' => $this->suite->id,
                'client_name' => 'Juan', 'client_email' => 'juan@example.com', 'client_phone' => '0917',
                'booking_date' => now()->addDays(3)->toDateString(), 'number_of_days' => 1,
                'time_slot_type' => 'full_day', 'booking_platform_id' => $this->agoda->id,
            ])
            ->assertSessionHasErrors('booking_platform_id');

        $this->assertSame(0, Booking::count());
    }

    public function test_staff_books_a_venue_with_a_platform_to_keep_regular_venue_pricing(): void
    {
        $venue = Venue::create([
            'name' => 'Grand Hall', 'type' => 'venue', 'description' => 'Hall', 'capacity' => 100,
            'price_per_day' => 10000, 'price_morning' => 4000, 'price_afternoon' => 4000, 'price_evening' => 5000,
            'amenities' => [], 'images' => [], 'is_active' => true,
        ]);

        $this->actingAs($this->staff)
            ->post(route('admin.bookings.store'), [
                'venue_id' => $venue->id,
                'client_name' => 'Maria', 'client_email' => 'maria@example.com', 'client_phone' => '0917',
                'booking_date' => now()->addDays(5)->toDateString(),
                'time_slot_type' => 'full_day',
                'booking_platform_id' => $this->agoda->id, 'platform_reference' => 'AG-1',
            ])
            ->assertSessionHasNoErrors();

        $booking = Booking::latest('id')->firstOrFail();
        $this->assertNull($booking->booking_platform_id);
        $this->assertNull($booking->rate_per_night);
        $this->assertNull($booking->platform_reference);
        $this->assertSame('10000.00', (string) $booking->total_amount);
    }

    public function test_staff_edits_a_platform_booking_after_a_rate_change_to_keep_the_booked_rate(): void
    {
        $booking = $this->bookSuite(['booking_platform_id' => $this->agoda->id]);
        app(SuiteRateService::class)->syncRates($this->suite, [$this->agoda->id => 3500]);

        $this->actingAs($this->staff)
            ->put(route('admin.bookings.update', $booking), [
                'venue_id' => $this->suite->id,
                'client_name' => 'Juan Dela Cruz', 'client_email' => 'juan@example.com', 'client_phone' => '0917',
                'booking_date' => $booking->booking_date->toDateString(),
                'number_of_days' => 3,
                'time_slot_type' => 'full_day',
                'status' => 'confirmed',
                'booking_platform_id' => $this->agoda->id,
            ])
            ->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame('2800.00', (string) $booking->rate_per_night);
        $this->assertSame('8400.00', (string) $booking->total_amount);
    }

    public function test_staff_switches_the_rate_source_on_edit_to_requote_at_the_current_rate(): void
    {
        $booking = $this->bookSuite();

        $this->actingAs($this->staff)
            ->put(route('admin.bookings.update', $booking), [
                'venue_id' => $this->suite->id,
                'client_name' => 'Juan Dela Cruz', 'client_email' => 'juan@example.com', 'client_phone' => '0917',
                'booking_date' => $booking->booking_date->toDateString(),
                'number_of_days' => 2,
                'time_slot_type' => 'full_day',
                'status' => 'confirmed',
                'booking_platform_id' => $this->agoda->id,
            ])
            ->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame($this->agoda->id, $booking->booking_platform_id);
        $this->assertSame('5600.00', (string) $booking->total_amount);
    }

    public function test_admin_sets_platform_rates_for_a_suite_to_match_each_channels_price(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.rates.update'), [
                'cells' => [
                    ['venue_id' => $this->suite->id, 'platform_id' => $this->agoda->id, 'price' => ''],
                    ['venue_id' => $this->suite->id, 'platform_id' => $this->bookingCom->id, 'price' => '2950.50'],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.rates.index'));

        $rates = app(SuiteRateService::class)->ratesFor($this->suite);
        $this->assertFalse($rates->has($this->agoda->id));
        $this->assertSame(2950.5, $rates[$this->bookingCom->id]);
    }

    public function test_staff_is_denied_access_to_platform_rates_to_keep_pricing_admin_controlled(): void
    {
        $this->actingAs($this->staff)->get(route('admin.rates.index'))->assertForbidden();
        $this->actingAs($this->staff)->put(route('admin.rates.update'), [
            'cells' => [['venue_id' => $this->suite->id, 'platform_id' => $this->agoda->id, 'price' => '1']],
        ])->assertForbidden();
        $this->actingAs($this->staff)->get(route('admin.suites.rates.edit', $this->suite))->assertForbidden();
        $this->actingAs($this->staff)->get(route('admin.booking-platforms.index'))->assertForbidden();
        $this->actingAs($this->staff)->post(route('admin.booking-platforms.store'), ['name' => 'Expedia'])->assertForbidden();
    }

    public function test_admin_deletes_a_platform_used_by_bookings_to_be_told_to_deactivate_it(): void
    {
        $this->bookSuite(['booking_platform_id' => $this->agoda->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.booking-platforms.destroy', $this->agoda))
            ->assertSessionHas('error');

        $this->assertNotNull($this->agoda->fresh());
    }

    public function test_admin_opens_platform_rate_pages_to_review_pricing(): void
    {
        $this->actingAs($this->admin)->get(route('admin.booking-platforms.index'))->assertOk()->assertSee('Agoda');
        $this->actingAs($this->admin)->get(route('admin.rates.index'))->assertOk()->assertSee('data-original="2800"', false);
        $this->actingAs($this->admin)->get(route('admin.suites.rates.edit', $this->suite))
            ->assertRedirect(route('admin.rates.index').'#suite-'.$this->suite->id);

        $booking = $this->bookSuite(['booking_platform_id' => $this->agoda->id, 'platform_reference' => 'AG-9']);
        $this->actingAs($this->admin)->get(route('admin.bookings.show', $booking))->assertOk()->assertSee('Agoda')->assertSee('AG-9');
        $this->actingAs($this->admin)->get(route('admin.bookings.edit', $booking))->assertOk()->assertSee('booking_platform_id', false);
        $this->actingAs($this->admin)->get(route('admin.reports.index'))->assertOk()->assertSee('By Booking Platform');
        $this->actingAs($this->admin)->get(route('admin.bookings.create', [
            'venue_id' => $this->suite->id, 'booking_date' => now()->addDays(10)->toDateString(),
        ]))->assertOk()->assertSee('data-platform-rates', false);
    }
}
