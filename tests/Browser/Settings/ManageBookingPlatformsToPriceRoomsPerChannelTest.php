<?php

declare(strict_types=1);

namespace Tests\Browser\Settings;

use App\Models\BookingPlatform;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\ArrangesSuiteRates;
use Tests\Browser\Pages\BookingPlatformsPage;
use Tests\Browser\Pages\CreateBookingPage;
use Tests\Browser\Pages\RoomRatesPage;
use Tests\DuskTestCase;

/**
 * @feature features/settings/manage-booking-platforms-to-price-rooms-per-channel.feature
 */
class ManageBookingPlatformsToPriceRoomsPerChannelTest extends DuskTestCase
{
    use ArrangesSuiteRates;

    /** @scenario features/settings/manage-booking-platforms-to-price-rooms-per-channel.feature:6 */
    public function test_admin_adds_a_booking_platform_to_price_rooms_per_channel(): void
    {
        $agoda = $this->agoda();
        $bookingDotCom = $this->bookingDotCom();

        $this->browse(function (Browser $browser) use ($agoda, $bookingDotCom) {
            $this->signInAsAdmin($browser)
                ->click('@nav-room-rates')
                ->on(new RoomRatesPage)
                ->click('@manage-platforms')
                ->on(new BookingPlatformsPage)
                ->addPlatform('Expedia');

            $expedia = BookingPlatform::where('name', 'Expedia')->firstOrFail();

            $browser->on(new BookingPlatformsPage)
                ->assertInputValue("@platform-row-{$expedia->id} input[name=name]", 'Expedia')
                ->assertSeeIn("@platform-status-{$expedia->id}", 'Active')
                ->assertInputValue("@platform-row-{$agoda->id} input[name=name]", 'Agoda')
                ->assertInputValue("@platform-row-{$bookingDotCom->id} input[name=name]", 'Booking.com');
        });
    }

    /** @scenario features/settings/manage-booking-platforms-to-price-rooms-per-channel.feature:15 */
    public function test_admin_adds_a_platform_that_is_already_listed_to_see_it_rejected(): void
    {
        $this->agoda();

        $this->browse(function (Browser $browser) {
            $this->signInAsAdmin($browser)
                ->visit(new BookingPlatformsPage)
                ->addPlatform('Agoda');

            $browser->on(new BookingPlatformsPage)
                ->assertSee('The name has already been taken.');
        });

        $this->assertSame(1, BookingPlatform::where('name', 'Agoda')->count());
    }

    /** @scenario features/settings/manage-booking-platforms-to-price-rooms-per-channel.feature:23 */
    public function test_admin_deactivates_a_booking_platform_to_stop_staff_from_selecting_it(): void
    {
        $agoda = $this->agoda();
        $bookingDotCom = $this->bookingDotCom();
        $suite = $this->suite('Deluxe Suite');
        $nextWeek = now()->addWeek()->toDateString();

        $this->browse(function (Browser $browser) use ($agoda, $bookingDotCom, $suite, $nextWeek) {
            $this->signInAsAdmin($browser)
                ->visit(new BookingPlatformsPage)
                ->toggle($bookingDotCom->id);

            $browser->on(new BookingPlatformsPage)
                ->assertSeeIn("@platform-status-{$bookingDotCom->id}", 'Inactive')
                ->assertSeeIn("@platform-status-{$agoda->id}", 'Active');

            // A Staff member now starts a booking (selenium runs one browser session at a time)
            $browser->logout();

            $this->signInAsStaff($browser)
                ->visit(new CreateBookingPage($suite->id, $nextWeek))
                ->waitFor('@rate-source')
                ->assertSelectHasOptions('@rate-source', ['', (string) $agoda->id])
                ->assertSelectMissingOption('@rate-source', (string) $bookingDotCom->id)
                ->assertSeeIn('@rate-source', 'Regular rate')
                ->assertSeeIn('@rate-source', 'Agoda — ₱2,500.00 / night (regular)')
                ->assertDontSeeIn('@rate-source', 'Booking.com');
        });
    }
}
