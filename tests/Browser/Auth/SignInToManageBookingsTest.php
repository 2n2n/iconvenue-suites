<?php

declare(strict_types=1);

namespace Tests\Browser\Auth;

use Laravel\Dusk\Browser;
use Tests\Browser\Pages\LoginPage;
use Tests\DuskTestCase;

/**
 * @feature features/auth/sign-in-to-manage-bookings.feature
 */
class SignInToManageBookingsTest extends DuskTestCase
{
    /** @scenario features/auth/sign-in-to-manage-bookings.feature:6 */
    public function test_admin_signs_in_to_access_the_dashboard(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new LoginPage)
                ->signIn('admin@iconvenue.com', 'admin123');

            $browser->waitForLocation('/admin/dashboard')
                ->assertSee('Dashboard')
                ->assertSee('Total Bookings')
                ->assertSee('Total Venues');
        });
    }

    /** @scenario features/auth/sign-in-to-manage-bookings.feature:13 */
    public function test_guest_submits_wrong_credentials_to_see_an_error(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new LoginPage)
                ->signIn('nobody@iconvenue.com', 'wrong-password');

            $browser->on(new LoginPage)
                ->assertSeeIn('@errors', 'The provided credentials do not match our records.');
        });
    }
}
