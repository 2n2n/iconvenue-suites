<?php

declare(strict_types=1);

namespace Tests\Browser\Bookings;

use App\Models\Booking;
use App\Models\Venue;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\ArrangesSuiteRates;
use Tests\Browser\Pages\CreateBookingPage;
use Tests\DuskTestCase;

/**
 * @feature features/bookings/edit-booking-keeps-status.feature
 */
class EditBookingKeepsStatusTest extends DuskTestCase
{
    use ArrangesSuiteRates;

    private Venue $deluxe;

    /** Background: Deluxe Suite at ₱2,500 regular rate. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->deluxe = $this->suite('Deluxe Suite', 2500.00);
    }

    /** @scenario features/bookings/edit-booking-keeps-status.feature:10 */
    public function test_staff_edits_a_pending_booking_to_keep_it_pending_until_payment_is_verified(): void
    {
        $this->browse(function (Browser $browser) {
            // Given I created a booking (through the form, so it starts Pending)
            $this->signInAsStaff($browser)
                ->visit(new CreateBookingPage($this->deluxe->id, now()->addWeek()->toDateString()))
                ->enterClient('Elena Pending', 'elena@example.com', '09181230000')
                ->createBooking();

            $browser->assertSeeIn('@booking-status-badge', 'Pending');
            $booking = Booking::where('client_name', 'Elena Pending')->firstOrFail();

            $this->editNotes($browser, $booking, 'Pending', 'pending', 'Guest arrives late, around 9 PM');

            $browser->assertSeeIn('@booking-status-badge', 'Pending');
        });

        $booking = Booking::where('client_name', 'Elena Pending')->firstOrFail();
        $this->assertSame('pending', $booking->status);
        $this->assertSame('Guest arrives late, around 9 PM', $booking->notes);
    }

    /** @scenario features/bookings/edit-booking-keeps-status.feature:20 */
    public function test_staff_edits_a_confirmed_booking_to_keep_it_confirmed(): void
    {
        $date = now()->addWeek()->toDateString();
        $felix = Booking::create([
            'venue_id' => $this->deluxe->id,
            'staff_id' => $this->staffUser()->id,
            'client_name' => 'Felix Confirmed',
            'client_email' => 'felix@example.com',
            'client_phone' => '09184560000',
            'booking_date' => $date,
            'end_date' => $date,
            'number_of_days' => 1,
            'original_amount' => 2500,
            'discount_amount' => 0,
            'total_amount' => 2500,
            'rate_per_night' => 2500,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $this->browse(function (Browser $browser) use ($felix) {
            $this->signInAsStaff($browser);
            $this->editNotes($browser, $felix, 'Confirmed', 'confirmed', 'Extra towels requested');

            $browser->assertSeeIn('@booking-status-badge', 'Confirmed');
        });

        $felix->refresh();
        $this->assertSame('confirmed', $felix->status);
        $this->assertSame('Extra towels requested', $felix->notes);
    }

    /** I edit the booking, see its status pre-selected, change only the notes and update. */
    private function editNotes(Browser $browser, Booking $booking, string $statusLabel, string $statusValue, string $notes): void
    {
        $browser->visit("/admin/bookings/{$booking->id}/edit")
            ->waitForPageToSettle()
            ->assertSelected('@booking-status', $statusValue)
            ->assertSeeIn('@booking-status option:checked', $statusLabel)
            ->clear('@booking-notes-input')
            ->type('@booking-notes-input', $notes)
            ->scrollIntoView('@booking-update')
            ->clickAndWaitForReload('@booking-update')
            ->waitForPageToSettle()
            ->assertPathIs("/admin/bookings/{$booking->id}")
            ->assertSeeIn('@booking-notes', $notes);
    }
}
