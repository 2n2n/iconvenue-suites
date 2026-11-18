<?php

declare(strict_types=1);

namespace Tests\Feature\Venue;

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
 * Room Rates grid: every suite's regular + per-platform rates edited on one screen.
 * See docs/decisions/2026-11-17-room-rates-workspace.md.
 */
final class RoomRatesGridTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private Venue $deluxe;

    private Venue $family;

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

        $this->deluxe = $this->makeSuite('Deluxe Suite', '101', 2500);
        $this->family = $this->makeSuite('Family Suite', '201', 3200);

        $this->agoda = BookingPlatform::where('name', 'Agoda')->firstOrFail();
        $this->bookingCom = BookingPlatform::where('name', 'Booking.com')->firstOrFail();

        app(SuiteRateService::class)->syncRates($this->deluxe, [$this->agoda->id => 2800]);
    }

    private function makeSuite(string $name, string $room, float $price): Venue
    {
        return Venue::create([
            'name' => $name, 'room_number' => $room, 'type' => 'suite', 'description' => 'Suite',
            'capacity' => 2, 'price_per_day' => $price, 'amenities' => [], 'images' => [], 'is_active' => true,
        ]);
    }

    /** @param array<int, array<string, mixed>> $cells */
    private function saveGrid(array $cells): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->put(route('admin.rates.update'), ['cells' => $cells]);
    }

    public function test_admin_edits_rates_for_several_rooms_at_once_to_update_prices_in_one_save(): void
    {
        $this->saveGrid([
            ['venue_id' => $this->deluxe->id, 'platform_id' => $this->agoda->id, 'price' => '2520'],
            ['venue_id' => $this->family->id, 'platform_id' => $this->agoda->id, 'price' => '3000'],
            ['venue_id' => $this->family->id, 'platform_id' => '', 'price' => '3400'],
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.rates.index'))
            ->assertSessionHas('success', '3 rates updated. New bookings will use the new prices.');

        $service = app(SuiteRateService::class);
        $this->assertSame(2520.0, $service->ratesFor($this->deluxe)[$this->agoda->id]);
        $this->assertSame(3000.0, $service->ratesFor($this->family)[$this->agoda->id]);
        $this->assertSame('3400.00', (string) $this->family->fresh()->price_per_day);
    }

    public function test_admin_saves_unchanged_rates_to_leave_them_untouched(): void
    {
        $this->saveGrid([
            ['venue_id' => $this->deluxe->id, 'platform_id' => $this->agoda->id, 'price' => '2800.00'],
            ['venue_id' => $this->deluxe->id, 'platform_id' => '', 'price' => '2500'],
        ])->assertSessionHas('success', 'No rates changed.');
    }

    public function test_admin_clears_a_platform_rate_to_charge_the_regular_rate_on_that_channel(): void
    {
        $this->saveGrid([
            ['venue_id' => $this->deluxe->id, 'platform_id' => $this->agoda->id, 'price' => ''],
        ])->assertSessionHasNoErrors();

        $quote = app(SuiteRateService::class)->quote($this->deluxe->fresh(), $this->agoda->id);
        $this->assertSame(2500.0, $quote->ratePerNight);
        $this->assertTrue($quote->usedFallback);
    }

    public function test_admin_clears_a_regular_rate_to_be_told_it_is_required(): void
    {
        $this->saveGrid([
            ['venue_id' => $this->deluxe->id, 'platform_id' => $this->agoda->id, 'price' => '2000'],
            ['venue_id' => $this->deluxe->id, 'platform_id' => '', 'price' => ''],
        ])->assertSessionHasErrors('cells.1.price');

        // Nothing saved: the whole change set is rejected
        $this->assertSame(2800.0, app(SuiteRateService::class)->ratesFor($this->deluxe)[$this->agoda->id]);
    }

    public function test_admin_sets_a_rate_on_a_venue_to_be_told_rates_are_for_suites_only(): void
    {
        $venue = Venue::create([
            'name' => 'Grand Hall', 'type' => 'venue', 'description' => 'Hall', 'capacity' => 100,
            'price_per_day' => 10000, 'amenities' => [], 'images' => [], 'is_active' => true,
        ]);

        $this->saveGrid([
            ['venue_id' => $venue->id, 'platform_id' => $this->agoda->id, 'price' => '9000'],
        ])->assertSessionHasErrors('cells.0.venue_id');
    }

    public function test_staff_is_denied_the_room_rates_grid_to_keep_pricing_admin_controlled(): void
    {
        $this->actingAs($this->staff)->get(route('admin.rates.index'))->assertForbidden();
        $this->actingAs($this->staff)->put(route('admin.rates.update'), [
            'cells' => [['venue_id' => $this->deluxe->id, 'platform_id' => '', 'price' => '1']],
        ])->assertForbidden();

        $this->assertSame('2500.00', (string) $this->deluxe->fresh()->price_per_day);
    }

    public function test_admin_changes_rates_after_a_booking_to_keep_that_booking_s_price(): void
    {
        $this->actingAs($this->staff)->post(route('admin.bookings.store'), [
            'venue_id' => $this->deluxe->id,
            'client_name' => 'Juan', 'client_email' => 'juan@example.com', 'client_phone' => '0917',
            'booking_date' => now()->addDays(3)->toDateString(), 'number_of_days' => 2,
            'time_slot_type' => 'full_day', 'booking_platform_id' => $this->agoda->id,
        ])->assertSessionHasNoErrors();
        $booking = Booking::latest('id')->firstOrFail();

        $this->saveGrid([
            ['venue_id' => $this->deluxe->id, 'platform_id' => $this->agoda->id, 'price' => '2000'],
        ])->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame('2800.00', (string) $booking->rate_per_night);
        $this->assertSame('5600.00', (string) $booking->total_amount);
    }

    public function test_admin_spots_rooms_without_a_channel_price_to_fill_in_missing_rates(): void
    {
        $this->actingAs($this->admin)->get(route('admin.rates.index'))
            ->assertOk()
            ->assertSee('Deluxe Suite')
            ->assertSee('Family Suite')
            // Family Suite has no Agoda/Booking.com rate: empty cell with the regular price as placeholder
            ->assertSee('dusk="rate-'.$this->family->id.'-'.$this->agoda->id.'"', false)
            ->assertSee('placeholder="3200"', false);
    }

    public function test_admin_opens_room_rates_from_a_suite_to_land_on_that_room(): void
    {
        $this->actingAs($this->admin)->get(route('admin.suites.rates.edit', $this->family))
            ->assertRedirect(route('admin.rates.index').'#suite-'.$this->family->id);
    }
}
