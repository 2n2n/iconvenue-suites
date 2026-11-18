<?php

declare(strict_types=1);

namespace Tests\Browser\Reports;

use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Payment;
use App\Models\Venue;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\ArrangesSuiteRates;
use Tests\DuskTestCase;

/**
 * @feature features/reports/view-revenue-by-platform.feature
 */
class ViewRevenueByPlatformTest extends DuskTestCase
{
    use ArrangesSuiteRates;

    /** @scenario features/reports/view-revenue-by-platform.feature:6 */
    public function test_admin_views_revenue_by_platform_to_compare_channels(): void
    {
        $suite = $this->suite('Deluxe Suite', 2500.00);
        $agoda = $this->agoda();
        $bookingDotCom = $this->bookingDotCom();

        $agodaBooking = $this->booking($suite, $agoda, 1800.00, now()->addDays(7), 'partial');
        $this->verifiedPayment($agodaBooking, 1800.00);

        $regularBooking = $this->booking($suite, null, 2500.00, now()->addDays(9), 'partial');
        $this->verifiedPayment($regularBooking, 1000.00);

        $this->booking($suite, $bookingDotCom, 2100.00, now()->addDays(12), 'unpaid', 'cancelled');

        $this->browse(function (Browser $browser) {
            $this->signInAsAdmin($browser)
                ->visit('/admin/reports')
                ->waitForPageToSettle()
                ->assertSeeIn('@report-by-platform', 'By Booking Platform')
                ->assertSeeIn('@report-platform-row-agoda', 'Agoda')
                ->assertSeeIn('@report-platform-row-agoda', '1')
                ->assertSeeIn('@report-platform-row-agoda', '₱1,800.00')
                ->assertSeeIn('@report-platform-row-regular', 'Regular')
                ->assertSeeIn('@report-platform-row-regular', '₱2,500.00')
                ->assertSeeIn('@report-platform-row-regular', '₱1,000.00')
                ->assertMissing('@report-platform-row-bookingcom')
                ->assertDontSeeIn('@report-by-platform', 'Booking.com');

            // Columns: Platform | Bookings | Booked Total | Verified Revenue
            $this->assertSame(
                ['Agoda', '1', '₱1,800.00', '₱1,800.00'],
                $this->rowCells($browser, 'agoda')
            );
            $this->assertSame(
                ['Regular', '1', '₱2,500.00', '₱1,000.00'],
                $this->rowCells($browser, 'regular')
            );
        });
    }

    /** @return list<string> */
    private function rowCells(Browser $browser, string $slug): array
    {
        return $browser->script(
            "return Array.from(document.querySelectorAll('[dusk=\"report-platform-row-{$slug}\"] td')).map(td => td.innerText.trim());"
        )[0];
    }

    private function booking(Venue $suite, ?BookingPlatform $platform, float $total, \DateTimeInterface $date, string $paymentStatus, string $status = 'pending'): Booking
    {
        return Booking::create([
            'venue_id' => $suite->id,
            'staff_id' => $this->staffUser()->id,
            'client_name' => 'Report Client',
            'client_email' => 'report.client@example.com',
            'client_phone' => '09170000000',
            'booking_date' => $date->format('Y-m-d'),
            'end_date' => $date->format('Y-m-d'),
            'number_of_days' => 1,
            'original_amount' => $total,
            'discount_amount' => 0,
            'total_amount' => $total,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'booking_platform_id' => $platform?->id,
            'rate_per_night' => $total,
        ]);
    }

    private function verifiedPayment(Booking $booking, float $amount): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id,
            'verified_by' => $this->staffUser()->id,
            'amount' => $amount,
            'payment_method' => 'cash',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
    }
}
