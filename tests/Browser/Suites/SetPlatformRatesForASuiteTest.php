<?php

declare(strict_types=1);

namespace Tests\Browser\Suites;

use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\ArrangesSuiteRates;
use Tests\Browser\Pages\RoomRatesPage;
use Tests\DuskTestCase;

/**
 * @feature features/suites/set-platform-rates-for-a-suite.feature
 */
class SetPlatformRatesForASuiteTest extends DuskTestCase
{
    use ArrangesSuiteRates;

    /** @scenario features/suites/set-platform-rates-for-a-suite.feature:6 */
    public function test_admin_sets_platform_rates_for_a_suite_to_match_each_channels_price(): void
    {
        $suite = $this->suite('Deluxe Suite', 2500.00);
        $agoda = $this->agoda();
        $bookingDotCom = $this->bookingDotCom();

        $this->browse(function (Browser $browser) use ($suite, $agoda, $bookingDotCom) {
            $this->signInAsAdmin($browser)
                ->visit('/admin/suites')
                ->waitForPageToSettle()
                ->assertSee('Deluxe Suite')
                ->click("@suite-rates-{$suite->id}")
                ->on(new RoomRatesPage)
                ->assertFragmentIs("suite-{$suite->id}")
                ->assertAttribute("@rates-row-{$suite->id}", 'data-highlighted', '1')
                ->assertInputValue(RoomRatesPage::cell($suite->id, 'regular'), '2500')
                ->setCell($suite->id, $agoda->id, '1800')
                ->setCell($suite->id, $bookingDotCom->id, '2100')
                ->assertSeeIn('@unsaved-count', '2 unsaved changes')
                ->saveChanges();

            $browser->on(new RoomRatesPage)
                ->assertSee('2 rates updated. New bookings will use the new prices.')
                ->assertInputValue(RoomRatesPage::cell($suite->id, $agoda->id), '1800')
                ->assertInputValue(RoomRatesPage::cell($suite->id, $bookingDotCom->id), '2100');
        });

        $this->assertDatabaseHas('suite_platform_rates', [
            'venue_id' => $suite->id, 'booking_platform_id' => $agoda->id, 'price_per_night' => 1800.00,
        ]);
        $this->assertDatabaseHas('suite_platform_rates', [
            'venue_id' => $suite->id, 'booking_platform_id' => $bookingDotCom->id, 'price_per_night' => 2100.00,
        ]);
    }

    /** @scenario features/suites/set-platform-rates-for-a-suite.feature:22 */
    public function test_admin_clears_a_platform_rate_to_charge_the_regular_rate_on_that_channel(): void
    {
        $suite = $this->suite('Deluxe Suite', 2500.00);
        $agoda = $this->agoda();
        $this->platformRate($suite, $agoda, 1800.00);

        $this->browse(function (Browser $browser) use ($suite, $agoda) {
            $this->signInAsAdmin($browser)
                ->visit(new RoomRatesPage)
                ->assertInputValue(RoomRatesPage::cell($suite->id, $agoda->id), '1800')
                ->clearCell($suite->id, $agoda->id)
                ->saveChanges();

            $browser->on(new RoomRatesPage)
                ->assertInputValue(RoomRatesPage::cell($suite->id, $agoda->id), '')
                ->assertAttribute(RoomRatesPage::cell($suite->id, $agoda->id), 'placeholder', '2500');
        });

        $this->assertDatabaseMissing('suite_platform_rates', [
            'venue_id' => $suite->id, 'booking_platform_id' => $agoda->id,
        ]);
    }

    /** @scenario features/suites/set-platform-rates-for-a-suite.feature:31 */
    public function test_staff_is_denied_access_to_platform_rates_to_keep_pricing_admin_controlled(): void
    {
        $suite = $this->suite('Deluxe Suite');
        $agoda = $this->agoda();

        $this->browse(function (Browser $browser) use ($suite, $agoda) {
            $this->signInAsStaff($browser)
                ->assertMissing('@nav-room-rates')
                ->assertDontSee('Room Rates')
                ->visit('/admin/rates')
                ->assertSee('403')
                ->assertMissing(RoomRatesPage::cell($suite->id, 'regular'))
                ->assertMissing(RoomRatesPage::cell($suite->id, $agoda->id))
                ->assertMissing('@save-changes')
                ->visit("/admin/suites/{$suite->id}/rates")
                ->assertSee('403')
                ->assertMissing(RoomRatesPage::cell($suite->id, 'regular'))
                ->visit('/admin/booking-platforms')
                ->assertSee('403')
                ->assertMissing('@platform-add');
        });
    }
}
