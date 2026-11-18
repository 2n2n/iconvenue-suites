<?php

declare(strict_types=1);

namespace Tests\Browser\Clients;

use App\Models\Booking;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\ArrangesSuiteRates;
use Tests\Browser\Pages\ClientPage;
use Tests\DuskTestCase;

/**
 * @feature features/clients/update-client-details.feature
 */
class UpdateClientDetailsTest extends DuskTestCase
{
    use ArrangesSuiteRates;

    private Booking $booking;

    /** Background: Maria Santos has a pending Deluxe Suite booking next week. */
    protected function setUp(): void
    {
        parent::setUp();

        $suite = $this->suite('Deluxe Suite', 2500.00);
        $date = now()->addWeek()->toDateString();

        $this->booking = Booking::create([
            'venue_id' => $suite->id,
            'staff_id' => $this->staffUser()->id,
            'client_name' => 'Maria Santos',
            'client_email' => 'maria@example.com',
            'client_phone' => '09171234567',
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

    /** @scenario features/clients/update-client-details.feature:10 */
    public function test_admin_updates_a_clients_details_to_keep_their_contact_information_correct(): void
    {
        $this->browse(function (Browser $browser) {
            $this->signInAsAdmin($browser)
                ->visit(new ClientPage('maria@example.com'))
                ->assertSeeIn('@client-name', 'Maria Santos')
                ->openEditForm()
                ->assertInputValue('@edit-name', 'Maria Santos')
                ->assertInputValue('@edit-email', 'maria@example.com')
                ->assertInputValue('@edit-phone', '09171234567')
                ->changeDetails('Maria Santos-Reyes', 'maria.reyes@example.com', '09179876543')
                ->saveChanges();

            $browser->on(new ClientPage('maria.reyes@example.com'))
                ->assertSee('Client information updated successfully.')
                ->assertSeeIn('@client-name', 'Maria Santos-Reyes')
                ->assertSeeIn('@email-line', 'maria.reyes@example.com')
                ->assertSeeIn('@phone-line', '09179876543')
                ->assertSee($this->booking->booking_reference);
        });

        $this->booking->refresh();
        $this->assertSame('Maria Santos-Reyes', $this->booking->client_name);
        $this->assertSame('maria.reyes@example.com', $this->booking->client_email);
        $this->assertSame('09179876543', $this->booking->client_phone);
    }

    /** @scenario features/clients/update-client-details.feature:23 */
    public function test_staff_views_a_client_without_an_edit_option_to_keep_client_records_admin_controlled(): void
    {
        $this->browse(function (Browser $browser) {
            $this->signInAsStaff($browser)
                ->visit(new ClientPage('maria@example.com'))
                ->assertSeeIn('@client-name', 'Maria Santos')
                ->assertSeeIn('@email-line', 'maria@example.com')
                ->assertSeeIn('@phone-line', '09171234567')
                ->assertSee('Booking History')
                ->assertSee($this->booking->booking_reference)
                ->assertDontSee('Edit Client')
                ->assertMissing('@edit')
                ->assertMissing('@edit-modal')
                ->assertMissing('@edit-save');
        });

        $this->assertSame('Maria Santos', $this->booking->fresh()->client_name);
    }
}
