<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venue\UpdateRoomRatesRequest;
use App\Models\Venue;
use App\Services\Venue\SuiteRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Room Rates: one grid to edit every suite's regular rate and per-platform rates.
 */
class RoomRateController extends Controller
{
    public function __construct(private readonly SuiteRateService $rates) {}

    public function index(): View
    {
        return view('admin.rates.index', ['grid' => $this->rates->grid()]);
    }

    public function update(UpdateRoomRatesRequest $request): RedirectResponse
    {
        $changed = $this->rates->applyChanges($request->cells());

        $message = $changed === 0
            ? 'No rates changed.'
            : "{$changed} rate".($changed === 1 ? '' : 's').' updated. New bookings will use the new prices.';

        return redirect()->route('admin.rates.index')->with('success', $message);
    }

    /** Old per-suite Rates links now open the grid at that room. */
    public function legacySuiteRates(Venue $venue): RedirectResponse
    {
        return redirect()->to(route('admin.rates.index').'#suite-'.$venue->id);
    }
}
