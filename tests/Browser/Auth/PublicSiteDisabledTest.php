<?php

declare(strict_types=1);

namespace Tests\Browser\Auth;

use App\Models\User;
use App\Models\Venue;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\LoginPage;
use Tests\DuskTestCase;

/**
 * @feature features/auth/public-site-disabled.feature
 *
 * The public website is turned off (docs/decisions/2026-11-18-disable-public-website.md):
 * "/" redirects to the sign-in page and the old public URLs return 404.
 */
class PublicSiteDisabledTest extends DuskTestCase
{
    /** @scenario features/auth/public-site-disabled.feature:6 */
    public function test_guest_opens_the_website_to_land_on_the_staff_sign_in_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->waitForLocation('/login')
                ->waitForPageToSettle()
                ->on(new LoginPage)
                ->assertPresent('@email')
                ->assertPresent('@password')
                ->assertPresent('@submit')
                ->assertDontSee('Back to Website')
                ->assertDontSee('BACK TO WEBSITE');
        });
    }

    /** @scenario features/auth/public-site-disabled.feature:13 */
    public function test_guest_opens_an_old_venue_page_to_find_there_is_no_public_booking_path(): void
    {
        $venue = Venue::create([
            'name' => 'Grand Hall',
            'type' => 'venue',
            'description' => 'An event hall.',
            'capacity' => 200,
            'price_per_day' => 20000,
            'price_morning' => 8000,
            'price_afternoon' => 8000,
            'price_evening' => 9000,
            'amenities' => ['Sound System'],
            'images' => [],
            'is_active' => true,
        ]);

        $this->browse(function (Browser $browser) use ($venue) {
            foreach (["/venue/{$venue->id}", '/venues', '/suites', '/contact'] as $oldPage) {
                $browser->visit($oldPage)
                    ->waitForPageToSettle()
                    ->assertPathIs($oldPage)
                    ->assertSee('404')
                    ->assertSee('NOT FOUND') // default Laravel 404 renders the message uppercase
                    ->assertDontSee('Grand Hall')
                    ->assertDontSee('WhatsApp')
                    ->assertDontSee('Check Availability')
                    // Former public booking controls (venue-details.blade.php / contact.blade.php).
                    ->assertMissing('#contactForBooking')
                    ->assertMissing('#checkAvailability')
                    ->assertMissing('#whatsappContact')
                    ->assertMissing('#availabilityModal');
            }
        });
    }

    /** @scenario features/auth/public-site-disabled.feature:22 */
    public function test_staff_signs_out_to_return_to_the_sign_in_page(): void
    {
        $staff = User::where('email', 'staff@iconvenue.com')->firstOrFail();

        $this->browse(function (Browser $browser) use ($staff) {
            $browser->loginAs($staff)
                ->visit('/admin/dashboard')
                ->waitForPageToSettle()
                ->assertSee('Dashboard');

            $browser->click('@profile-menu')
                ->waitFor('@logout-button')
                ->click('@logout-button')
                ->waitForLocation('/login')
                ->waitForPageToSettle()
                ->on(new LoginPage);

            $browser->visit('/')
                ->waitForLocation('/login')
                ->waitForPageToSettle()
                ->on(new LoginPage)
                ->assertMissing('@profile-menu');
        });
    }
}
