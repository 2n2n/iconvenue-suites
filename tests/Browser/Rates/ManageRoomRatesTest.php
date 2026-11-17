<?php

declare(strict_types=1);

namespace Tests\Browser\Rates;

use App\Models\BookingPlatform;
use App\Models\Venue;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\ArrangesSuiteRates;
use Tests\Browser\Pages\CreateBookingPage;
use Tests\Browser\Pages\RoomRatesPage;
use Tests\DuskTestCase;

/**
 * @feature features/rates/manage-room-rates.feature
 */
class ManageRoomRatesTest extends DuskTestCase
{
    use ArrangesSuiteRates;

    private Venue $deluxe;

    private Venue $family;

    private BookingPlatform $agodaPlatform;

    private BookingPlatform $bookingDotComPlatform;

    /** Background: Deluxe ₱2,500 regular + Agoda ₱2,800; Family ₱3,500 regular, no platform rates. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->agodaPlatform = $this->agoda();
        $this->bookingDotComPlatform = $this->bookingDotCom();
        $this->deluxe = $this->suite('Deluxe Suite', 2500.00, '101');
        $this->family = $this->suite('Family Suite', 3500.00, '102');
        $this->platformRate($this->deluxe, $this->agodaPlatform, 2800.00);
    }

    /** @scenario features/rates/manage-room-rates.feature:11 */
    public function test_admin_edits_rates_for_several_rooms_at_once_to_update_prices_in_one_save(): void
    {
        $deluxeRegular = RoomRatesPage::cell($this->deluxe->id, 'regular');
        $familyBookingDotCom = RoomRatesPage::cell($this->family->id, $this->bookingDotComPlatform->id);

        $this->browse(function (Browser $browser) use ($deluxeRegular, $familyBookingDotCom) {
            $this->signInAsAdmin($browser)
                ->click('@nav-room-rates')
                ->on(new RoomRatesPage)
                ->setCell($this->deluxe->id, 'regular', '2700')
                ->setCell($this->family->id, $this->bookingDotComPlatform->id, '3300');

            $this->assertCellHighlighted($browser, $deluxeRegular);
            $this->assertCellHighlighted($browser, $familyBookingDotCom);

            $browser->on(new RoomRatesPage)
                ->assertSeeIn('@unsaved-count', '2 unsaved changes')
                ->saveChanges();

            $browser->on(new RoomRatesPage)
                ->assertSee('2 rates updated. New bookings will use the new prices.')
                ->assertInputValue($deluxeRegular, '2700')
                ->assertInputValue($familyBookingDotCom, '3300')
                ->assertSeeIn('@unsaved-count', '0 unsaved changes')
                ->assertDisabled('@save-changes');
        });

        $this->assertSame('2700.00', (string) $this->deluxe->fresh()->price_per_day);
        $this->assertDatabaseHas('suite_platform_rates', [
            'venue_id' => $this->family->id,
            'booking_platform_id' => $this->bookingDotComPlatform->id,
            'price_per_night' => 3300.00,
        ]);
    }

    /** @scenario features/rates/manage-room-rates.feature:24 */
    public function test_admin_sets_one_price_for_selected_rooms_to_change_a_platforms_rate_quickly(): void
    {
        $deluxeAgoda = RoomRatesPage::cell($this->deluxe->id, $this->agodaPlatform->id);
        $familyAgoda = RoomRatesPage::cell($this->family->id, $this->agodaPlatform->id);

        $this->browse(function (Browser $browser) use ($deluxeAgoda, $familyAgoda) {
            $this->signInAsAdmin($browser)
                ->visit(new RoomRatesPage)
                ->check("@select-room-{$this->deluxe->id}")
                ->check("@select-room-{$this->family->id}")
                ->applyToSelected($this->agodaPlatform->id, 'set', '3000');

            $browser->assertInputValue($deluxeAgoda, '3000')
                ->assertInputValue($familyAgoda, '3000');
            $this->assertCellHighlighted($browser, $deluxeAgoda);
            $this->assertCellHighlighted($browser, $familyAgoda);

            $browser->on(new RoomRatesPage)
                ->assertSeeIn('@unsaved-count', '2 unsaved changes')
                ->saveChanges();

            $browser->on(new RoomRatesPage)
                ->assertSee('2 rates updated. New bookings will use the new prices.')
                ->assertInputValue($deluxeAgoda, '3000')
                ->assertInputValue($familyAgoda, '3000');
        });

        foreach ([$this->deluxe, $this->family] as $suite) {
            $this->assertDatabaseHas('suite_platform_rates', [
                'venue_id' => $suite->id,
                'booking_platform_id' => $this->agodaPlatform->id,
                'price_per_night' => 3000.00,
            ]);
        }
    }

    /** @scenario features/rates/manage-room-rates.feature:35 */
    public function test_admin_raises_a_platform_rate_without_selecting_a_room_to_see_a_reminder(): void
    {
        $this->browse(function (Browser $browser) {
            $this->signInAsAdmin($browser)
                ->visit(new RoomRatesPage)
                ->applyToSelected($this->agodaPlatform->id, 'pct_up', '10');

            $browser->waitFor('@bulk-message')
                ->assertSeeIn('@bulk-message', 'Select at least one room first.')
                ->assertSeeIn('@unsaved-count', '0 unsaved changes')
                ->assertInputValue(RoomRatesPage::cell($this->deluxe->id, $this->agodaPlatform->id), '2800');
        });
    }

    /** @scenario features/rates/manage-room-rates.feature:42 */
    public function test_admin_discards_unsaved_rate_edits_to_keep_the_current_prices(): void
    {
        $deluxeAgoda = RoomRatesPage::cell($this->deluxe->id, $this->agodaPlatform->id);
        $familyRegular = RoomRatesPage::cell($this->family->id, 'regular');

        $this->browse(function (Browser $browser) use ($deluxeAgoda, $familyRegular) {
            $this->signInAsAdmin($browser)
                ->visit(new RoomRatesPage)
                ->setCell($this->deluxe->id, $this->agodaPlatform->id, '1')
                ->clearCell($this->family->id, 'regular')
                ->assertSeeIn('@unsaved-count', '2 unsaved changes')
                ->discardChanges();

            $browser->assertInputValue($deluxeAgoda, '2800')
                ->assertInputValue($familyRegular, '3500')
                ->assertSeeIn('@unsaved-count', '0 unsaved changes')
                ->assertDisabled('@save-changes')
                ->assertDisabled('@discard-changes');

            $browser->visit(new RoomRatesPage)
                ->assertInputValue($deluxeAgoda, '2800')
                ->assertInputValue($familyRegular, '3500');
        });

        $this->assertSame('3500.00', (string) $this->family->fresh()->price_per_day);
        $this->assertDatabaseHas('suite_platform_rates', [
            'venue_id' => $this->deluxe->id,
            'booking_platform_id' => $this->agodaPlatform->id,
            'price_per_night' => 2800.00,
        ]);
    }

    /** @scenario features/rates/manage-room-rates.feature:55 */
    public function test_admin_spots_rooms_without_a_channel_price_to_fill_in_missing_rates(): void
    {
        $deluxeAgoda = RoomRatesPage::cell($this->deluxe->id, $this->agodaPlatform->id);
        $familyAgoda = RoomRatesPage::cell($this->family->id, $this->agodaPlatform->id);

        $this->browse(function (Browser $browser) use ($deluxeAgoda, $familyAgoda) {
            $this->signInAsAdmin($browser)
                ->visit(new RoomRatesPage)
                ->assertInputValue($deluxeAgoda, '2800')
                ->assertInputValue($familyAgoda, '')
                ->assertAttribute($familyAgoda, 'placeholder', '3500')
                ->setCell($this->family->id, $this->agodaPlatform->id, '3200')
                ->saveChanges();

            $browser->on(new RoomRatesPage)
                ->assertSee('1 rate updated. New bookings will use the new prices.')
                ->assertInputValue($familyAgoda, '3200');
        });

        $this->assertDatabaseHas('suite_platform_rates', [
            'venue_id' => $this->family->id,
            'booking_platform_id' => $this->agodaPlatform->id,
            'price_per_night' => 3200.00,
        ]);
    }

    /** @scenario features/rates/manage-room-rates.feature:64 */
    public function test_staff_sees_each_rates_price_when_booking_to_choose_the_right_channel(): void
    {
        $this->browse(function (Browser $browser) {
            $this->signInAsStaff($browser)
                ->visit(new CreateBookingPage($this->deluxe->id, now()->addWeek()->toDateString()))
                ->waitFor('@rate-source');

            $options = $browser->script(
                'return Array.from(document.querySelector(\'[dusk="rate-source"]\').options).map(o => o.text.trim());'
            )[0];

            $this->assertSame([
                'Regular rate — ₱2,500.00 / night',
                'Agoda — ₱2,800.00 / night',
                'Booking.com — ₱2,500.00 / night (regular)',
            ], $options);
        });
    }

    private function assertCellHighlighted(Browser $browser, string $cell): void
    {
        $classes = (string) $browser->attribute($cell, 'class');

        $this->assertStringContainsString('bg-yellow-50', $classes, "{$cell} is not highlighted as edited.");
        $this->assertStringContainsString('border-yellow-400', $classes, "{$cell} is not highlighted as edited.");
    }
}
