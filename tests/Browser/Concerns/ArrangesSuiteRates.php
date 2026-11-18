<?php

declare(strict_types=1);

namespace Tests\Browser\Concerns;

use App\Models\BookingPlatform;
use App\Models\SuitePlatformRate;
use App\Models\User;
use App\Models\Venue;
use Laravel\Dusk\Browser;

/**
 * "Given" steps shared by the per-platform suite rate scenarios.
 *
 * Agoda and Booking.com ship with migration 2026_11_17_000001, but DatabaseTruncation
 * empties every table between tests, so each scenario puts them back here.
 */
trait ArrangesSuiteRates
{
    protected function agoda(): BookingPlatform
    {
        return BookingPlatform::firstOrCreate(['name' => 'Agoda'], ['is_active' => true, 'sort_order' => 1]);
    }

    protected function bookingDotCom(): BookingPlatform
    {
        return BookingPlatform::firstOrCreate(['name' => 'Booking.com'], ['is_active' => true, 'sort_order' => 2]);
    }

    protected function suite(string $name = 'Deluxe Suite', float $regularRate = 2500.00, string $roomNumber = '101'): Venue
    {
        return Venue::create([
            'name' => $name,
            'room_number' => $roomNumber,
            'type' => 'suite',
            'description' => 'A quiet suite for overnight stays.',
            'capacity' => 2,
            'price_per_day' => $regularRate,
            'amenities' => ['WiFi', 'Air Conditioning'],
            'images' => [],
            'is_active' => true,
        ]);
    }

    protected function venue(string $name = 'Grand Hall', float $fullDayPrice = 20000.00): Venue
    {
        return Venue::create([
            'name' => $name,
            'type' => 'venue',
            'description' => 'An event hall.',
            'capacity' => 200,
            'price_per_day' => $fullDayPrice,
            'price_morning' => 8000,
            'price_afternoon' => 8000,
            'price_evening' => 9000,
            'amenities' => ['Sound System'],
            'images' => [],
            'is_active' => true,
        ]);
    }

    protected function platformRate(Venue $suite, BookingPlatform $platform, float $pricePerNight): SuitePlatformRate
    {
        return SuitePlatformRate::create([
            'venue_id' => $suite->id,
            'booking_platform_id' => $platform->id,
            'price_per_night' => $pricePerNight,
        ]);
    }

    protected function staffUser(): User
    {
        return User::where('email', 'staff@iconvenue.com')->firstOrFail();
    }

    /*
     * Sign-in itself is covered by features/auth/. These scenarios start already signed in
     * (Dusk's loginAs): POST /login is throttled per email, and signing in through the form
     * in every scenario trips "Too many login attempts" within a minute.
     */
    protected function signInAsAdmin(Browser $browser): Browser
    {
        return $this->signIn($browser, 'admin@iconvenue.com');
    }

    protected function signInAsStaff(Browser $browser): Browser
    {
        return $this->signIn($browser, 'staff@iconvenue.com');
    }

    private function signIn(Browser $browser, string $email): Browser
    {
        return $browser->loginAs(User::where('email', $email)->firstOrFail())
            ->visit('/admin/dashboard')
            ->waitForPageToSettle()
            ->assertPathIs('/admin/dashboard');
    }
}
