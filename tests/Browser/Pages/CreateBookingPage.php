<?php

declare(strict_types=1);

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;

/**
 * The staff booking form, opened for one venue/suite and date (as from the calendar).
 */
class CreateBookingPage extends Page
{
    public function __construct(
        private readonly int $venueId,
        private readonly string $bookingDate,
    ) {}

    public function url(): string
    {
        return "/admin/bookings/create?venue_id={$this->venueId}&booking_date={$this->bookingDate}";
    }

    public function assert(Browser $browser): void
    {
        $browser->waitForPageToSettle()
            ->assertPathIs('/admin/bookings/create')
            ->assertPresent('@booking-submit');
    }

    public function enterClient(Browser $browser, string $name, string $email, string $phone): void
    {
        $browser->type('@client-name', $name)
            ->type('@client-email', $email)
            ->type('@client-phone', $phone);
    }

    public function setNights(Browser $browser, int $nights): void
    {
        $browser->waitFor('@number-of-days')
            ->clear('@number-of-days')
            ->type('@number-of-days', (string) $nights);
    }

    /** Choose the rate source by its name ("Regular rate", "Agoda", ...); option text also shows the price. */
    public function chooseRate(Browser $browser, string $label): void
    {
        $browser->waitFor('@rate-source');
        $value = $browser->script(
            'const s = document.querySelector(\'[dusk="rate-source"]\');'
            .' const o = Array.from(s.options).find(o => (o.dataset.name || o.text).trim() === '.json_encode($label).');'
            .' return o ? o.value : null;'
        )[0];

        Assert::assertNotNull($value, "No \"{$label}\" rate option is offered.");
        $browser->select('@rate-source', $value);
    }

    public function giveDiscount(Browser $browser, string $type, string $value, string $reason): void
    {
        $browser->click("@discount-type-{$type}")
            ->waitUntilEnabled('@discount-value')
            ->type('@discount-value', $value)
            ->type('@discount-reason', $reason);
    }

    public function createBooking(Browser $browser): void
    {
        $browser->scrollIntoView('@booking-submit')
            ->click('@booking-submit')
            ->waitUntilMissing('@booking-submit', 15)
            ->waitForPageToSettle();
    }
}
