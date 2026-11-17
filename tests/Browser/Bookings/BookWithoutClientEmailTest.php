<?php

declare(strict_types=1);

namespace Tests\Browser\Bookings;

use App\Models\Booking;
use App\Models\Venue;
use Illuminate\Support\Facades\Http;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\ArrangesSuiteRates;
use Tests\Browser\Pages\CreateBookingPage;
use Tests\DuskTestCase;

/**
 * @feature features/bookings/book-without-client-email.feature
 */
class BookWithoutClientEmailTest extends DuskTestCase
{
    use ArrangesSuiteRates;

    private const NO_EMAIL_NOTICE = 'No email — the client won\'t receive email notifications';

    private Venue $deluxe;

    /** Background: Deluxe Suite at ₱2,500 regular rate. Each scenario signs in itself. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->deluxe = $this->suite('Deluxe Suite', 2500.00);
    }

    /** @scenario features/bookings/book-without-client-email.feature:10 */
    public function test_staff_creates_a_booking_without_an_email_to_serve_a_walk_in_guest(): void
    {
        $this->browse(function (Browser $browser) {
            $this->signInAsStaff($browser)
                ->visit(new CreateBookingPage($this->deluxe->id, now()->addWeek()->toDateString()))
                ->assertSee('Client Email (optional)')
                ->assertSee('Optional. Needed for the booking confirmation, payment and reminder emails.')
                ->assertAttributeMissing('@client-email', 'required')
                ->type('@client-name', 'Pedro Walk-in')
                ->type('@client-phone', '09181112222')
                ->createBooking();

            $browser->assertPathBeginsWith('/admin/bookings/')
                ->assertSee('Pedro Walk-in')
                ->assertSeeIn('@no-email-badge', self::NO_EMAIL_NOTICE);
        });

        $booking = Booking::where('client_name', 'Pedro Walk-in')->firstOrFail();
        $this->assertNull($booking->client_email);
        $this->assertNoEmailMentions($booking->booking_reference);
    }

    /** @scenario features/bookings/book-without-client-email.feature:20 */
    public function test_staff_enters_an_invalid_email_to_see_it_rejected(): void
    {
        $this->browse(function (Browser $browser) {
            // The browser accepts this address; the server rejects it (consecutive dots).
            $this->signInAsStaff($browser)
                ->visit(new CreateBookingPage($this->deluxe->id, now()->addWeek()->toDateString()))
                ->enterClient('Pedro Walk-in', 'pedro..walkin@example.com', '09181112222');

            $browser->scrollIntoView('@booking-submit')
                ->clickAndWaitForReload('@booking-submit')
                ->on(new CreateBookingPage($this->deluxe->id, now()->addWeek()->toDateString()))
                ->assertSee('valid email address')
                ->assertInputValue('@client-email', 'pedro..walkin@example.com');
        });

        $this->assertSame(0, Booking::where('client_name', 'Pedro Walk-in')->count());
    }

    /** @scenario features/bookings/book-without-client-email.feature:28 */
    public function test_staff_views_a_booking_without_an_email_to_know_the_client_wont_be_notified(): void
    {
        $ana = $this->booking('Ana Phone-only', '09183334444', null, 7);
        $ben = $this->booking('Ben Emailer', '09189990000', 'ben@example.com', 14);

        $this->browse(function (Browser $browser) use ($ana, $ben) {
            $this->signInAsStaff($browser)
                ->visit("/admin/bookings/{$ana->id}")
                ->waitForPageToSettle()
                ->assertSee('Ana Phone-only')
                ->assertSeeIn('@no-email-badge', self::NO_EMAIL_NOTICE)
                ->visit("/admin/bookings/{$ben->id}")
                ->waitForPageToSettle()
                ->assertSeeIn('@booking-client-email', 'ben@example.com')
                ->assertMissing('@no-email-badge');
        });
    }

    /** @scenario features/bookings/book-without-client-email.feature:37 */
    public function test_staff_updates_a_booking_to_remove_the_email_when_the_client_has_none(): void
    {
        $carla = $this->booking('Carla Typo', '09185556666', 'carla@wrong.example', 7);

        $this->browse(function (Browser $browser) use ($carla) {
            $this->signInAsStaff($browser)
                ->visit("/admin/bookings/{$carla->id}/edit")
                ->waitForPageToSettle()
                ->assertInputValue('@client-email', 'carla@wrong.example')
                ->assertAttributeMissing('@client-email', 'required')
                ->clear('@client-email')
                ->scrollIntoView('@booking-update')
                ->clickAndWaitForReload('@booking-update')
                ->waitForPageToSettle()
                ->assertPathIs("/admin/bookings/{$carla->id}")
                ->assertSeeIn('@no-email-badge', self::NO_EMAIL_NOTICE)
                ->assertSee('09185556666');
        });

        $carla->refresh();
        $this->assertNull($carla->client_email);
        $this->assertSame('09185556666', $carla->client_phone);
    }

    /** @scenario features/bookings/book-without-client-email.feature:45 */
    public function test_staff_opens_a_client_without_an_email_to_see_their_bookings_by_phone(): void
    {
        $dino = $this->booking('Dino Walk-in', '09187778888', null, 7);

        $this->browse(function (Browser $browser) use ($dino) {
            $this->signInAsStaff($browser)
                ->visit('/admin/clients')
                ->waitForPageToSettle()
                ->assertSee('Dino Walk-in')
                ->assertSeeIn('@client-no-email', 'No email')
                ->click('@client-view-bookings')
                ->waitForPageToSettle()
                ->assertPathIs('/admin/clients/tel-09187778888')
                ->assertSee('Dino Walk-in')
                ->assertSeeIn('@client-email-line', 'No email')
                ->assertSeeIn('@client-phone-line', '09187778888')
                ->assertSee($dino->booking_reference)
                ->assertMissing('@client-edit');
        });
    }

    /** @scenario features/bookings/book-without-client-email.feature:53 */
    public function test_admin_adds_an_email_to_a_phone_only_client_to_start_sending_notifications(): void
    {
        $dino = $this->booking('Dino Walk-in', '09187778888', null, 7);

        $this->browse(function (Browser $browser) use ($dino) {
            $this->signInAsAdmin($browser)
                ->visit('/admin/clients')
                ->waitForPageToSettle()
                ->click('@client-view-bookings')
                ->waitForPageToSettle()
                ->assertPathIs('/admin/clients/tel-09187778888')
                ->click('@client-edit')
                ->waitFor('@client-edit-email')
                ->assertInputValue('@client-edit-email', '')
                ->type('@client-edit-email', 'dino@example.com')
                ->clickAndWaitForReload('@client-edit-save')
                ->waitForPageToSettle()
                ->assertSee('Client information updated successfully.')
                ->assertSeeIn('@client-email-line', 'dino@example.com')
                ->visit("/admin/bookings/{$dino->id}")
                ->waitForPageToSettle()
                ->assertSeeIn('@booking-client-email', 'dino@example.com')
                ->assertMissing('@no-email-badge');
        });

        $this->assertSame('dino@example.com', $dino->fresh()->client_email);
    }

    /** A pending suite booking made by staff, $daysAhead from today. */
    private function booking(string $name, string $phone, ?string $email, int $daysAhead): Booking
    {
        $date = now()->addDays($daysAhead)->toDateString();

        return Booking::create([
            'venue_id' => $this->deluxe->id,
            'staff_id' => $this->staffUser()->id,
            'client_name' => $name,
            'client_email' => $email,
            'client_phone' => $phone,
            'booking_date' => $date,
            'end_date' => $date,
            'number_of_days' => 1,
            'original_amount' => 2500,
            'discount_amount' => 0,
            'total_amount' => 2500,
            'rate_per_night' => 2500,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
    }

    /** No email mentioning the booking reached Mailpit (the app sends mail synchronously). */
    private function assertNoEmailMentions(string $bookingReference): void
    {
        $response = Http::get('http://mailpit:8025/api/v1/search', ['query' => $bookingReference]);

        $this->assertTrue($response->successful(), 'Mailpit API is not reachable from the Dusk stack.');
        $this->assertSame(0, (int) $response->json('messages_count'), "An email mentioning {$bookingReference} was sent.");
    }
}
