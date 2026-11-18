<?php

declare(strict_types=1);

namespace Tests\Browser\Bookings;

use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Venue;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\ArrangesSuiteRates;
use Tests\Browser\Pages\CreateBookingPage;
use Tests\DuskTestCase;

/**
 * @feature features/bookings/book-a-suite-at-a-channel-rate.feature
 */
class BookASuiteAtAChannelRateTest extends DuskTestCase
{
    use ArrangesSuiteRates;

    private Venue $deluxe;

    private BookingPlatform $agodaPlatform;

    private BookingPlatform $bookingDotComPlatform;

    private string $nextWeek;

    /** Background: Deluxe Suite ₱2,500 regular, Agoda ₱1,800, no Booking.com rate. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->deluxe = $this->suite('Deluxe Suite', 2500.00);
        $this->agodaPlatform = $this->agoda();
        $this->bookingDotComPlatform = $this->bookingDotCom();
        $this->platformRate($this->deluxe, $this->agodaPlatform, 1800.00);
        $this->nextWeek = now()->addWeek()->toDateString();
    }

    /** @scenario features/bookings/book-a-suite-at-a-channel-rate.feature:12 */
    public function test_staff_books_a_suite_at_a_platform_rate_to_charge_the_channel_price(): void
    {
        $this->browse(function (Browser $browser) {
            $this->startBooking($browser)
                ->setNights(2)
                ->chooseRate('Agoda')
                ->createBooking();

            $browser->assertSeeIn('@booking-rate', 'Agoda — ₱1,800.00 / night')
                ->assertSeeIn('@booking-total', '₱3,600.00');
        });

        $this->assertDatabaseHas('bookings', [
            'venue_id' => $this->deluxe->id,
            'booking_platform_id' => $this->agodaPlatform->id,
            'rate_per_night' => 1800.00,
            'number_of_days' => 2,
            'total_amount' => 3600.00,
        ]);
    }

    /** @scenario features/bookings/book-a-suite-at-a-channel-rate.feature:21 */
    public function test_staff_books_a_suite_at_the_regular_rate_to_charge_walk_in_pricing(): void
    {
        $this->browse(function (Browser $browser) {
            $this->startBooking($browser)
                ->waitFor('@rate-source')
                ->assertSelected('@rate-source', '')
                ->assertMissing('@platform-reference')
                ->createBooking();

            $browser->assertSeeIn('@booking-rate', 'Regular — ₱2,500.00 / night')
                ->assertSeeIn('@booking-total', '₱2,500.00');
        });

        $this->assertDatabaseHas('bookings', [
            'venue_id' => $this->deluxe->id,
            'booking_platform_id' => null,
            'total_amount' => 2500.00,
        ]);
    }

    /** @scenario features/bookings/book-a-suite-at-a-channel-rate.feature:30 */
    public function test_staff_books_a_suite_on_a_platform_without_a_room_rate_to_fall_back_to_the_regular_rate(): void
    {
        $this->browse(function (Browser $browser) {
            $this->startBooking($browser)
                ->chooseRate('Booking.com');

            $browser->waitFor('@rate-fallback-note')
                ->assertSeeIn('@rate-fallback-note', 'No Booking.com rate set for this room — regular rate applied.');

            $browser->on(new CreateBookingPage($this->deluxe->id, $this->nextWeek))
                ->createBooking();

            $browser->assertSeeIn('@booking-rate', 'Booking.com — ₱2,500.00 / night')
                ->assertSeeIn('@booking-total', '₱2,500.00');
        });

        $this->assertDatabaseHas('bookings', [
            'venue_id' => $this->deluxe->id,
            'booking_platform_id' => $this->bookingDotComPlatform->id,
            'rate_per_night' => 2500.00,
            'total_amount' => 2500.00,
        ]);
    }

    /** @scenario features/bookings/book-a-suite-at-a-channel-rate.feature:39 */
    public function test_staff_applies_a_discount_to_a_platform_rate_booking_to_reduce_the_total(): void
    {
        $this->browse(function (Browser $browser) {
            $this->startBooking($browser)
                ->setNights(2)
                ->chooseRate('Agoda')
                ->giveDiscount('percentage', '10', 'Loyalty');

            $browser->waitFor('@discount-preview-final')
                ->assertSeeIn('@discount-preview-final', '₱3,240.00');

            $browser->on(new CreateBookingPage($this->deluxe->id, $this->nextWeek))
                ->createBooking();

            $browser->assertSeeIn('@booking-original-amount', '₱3,600.00')
                ->assertSeeIn('@booking-discount', '₱360.00')
                ->assertSeeIn('@booking-total', '₱3,240.00');
        });

        $this->assertDatabaseHas('bookings', [
            'venue_id' => $this->deluxe->id,
            'booking_platform_id' => $this->agodaPlatform->id,
            'original_amount' => 3600.00,
            'discount_amount' => 360.00,
            'total_amount' => 3240.00,
        ]);
    }

    /** @scenario features/bookings/book-a-suite-at-a-channel-rate.feature:51 */
    public function test_staff_records_the_platform_reference_to_reconcile_with_the_channel(): void
    {
        $this->browse(function (Browser $browser) {
            $this->startBooking($browser)
                ->chooseRate('Agoda');

            $browser->waitFor('@platform-reference')
                ->type('@platform-reference', 'AGD-778812');

            $browser->on(new CreateBookingPage($this->deluxe->id, $this->nextWeek))
                ->createBooking();

            $browser->assertSeeIn('@booking-platform-reference', 'AGD-778812');
        });

        $this->assertDatabaseHas('bookings', [
            'booking_platform_id' => $this->agodaPlatform->id,
            'platform_reference' => 'AGD-778812',
        ]);
    }

    /** @scenario features/bookings/book-a-suite-at-a-channel-rate.feature:60 */
    public function test_staff_books_a_venue_to_keep_event_pricing_free_of_platform_rates(): void
    {
        $hall = $this->venue('Grand Hall', 20000.00);

        $this->browse(function (Browser $browser) use ($hall) {
            $this->signInAsStaff($browser)
                ->visit(new CreateBookingPage($hall->id, $this->nextWeek))
                ->assertMissing('@rate-source')
                ->enterClient('Maria Santos', 'maria.santos@example.com', '09171234567')
                ->createBooking();

            $browser->assertSeeIn('@booking-total', '₱20,000.00')
                ->assertMissing('@booking-rate');
        });

        $booking = Booking::where('venue_id', $hall->id)->firstOrFail();
        $this->assertNull($booking->booking_platform_id);
        $this->assertNull($booking->rate_per_night);
    }

    /** I start a booking for "Deluxe Suite" next week and enter the client's details. */
    private function startBooking(Browser $browser): Browser
    {
        return $this->signInAsStaff($browser)
            ->visit(new CreateBookingPage($this->deluxe->id, $this->nextWeek))
            ->waitFor('@rate-source')
            ->enterClient('Juan dela Cruz', 'juan.delacruz@example.com', '09171234567');
    }
}
