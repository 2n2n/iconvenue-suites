<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\Settings\PlatformInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreBookingPlatformRequest;
use App\Http\Requests\Settings\UpdateBookingPlatformRequest;
use App\Models\BookingPlatform;
use App\Services\Settings\BookingPlatformService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookingPlatformController extends Controller
{
    public function __construct(private readonly BookingPlatformService $platforms) {}

    public function index(): View
    {
        return view('admin.booking-platforms.index', ['platforms' => $this->platforms->all()]);
    }

    public function store(StoreBookingPlatformRequest $request): RedirectResponse
    {
        $platform = $this->platforms->create($request->validated('name'), (int) $request->validated('sort_order', 0));

        return redirect()->route('admin.booking-platforms.index')->with('success', "{$platform->name} added.");
    }

    public function update(UpdateBookingPlatformRequest $request, BookingPlatform $platform): RedirectResponse
    {
        $this->platforms->update($platform, $request->validated('name'), (int) $request->validated('sort_order', 0));

        return redirect()->route('admin.booking-platforms.index')->with('success', "{$platform->name} updated.");
    }

    public function toggle(BookingPlatform $platform): RedirectResponse
    {
        $this->platforms->toggle($platform);
        $state = $platform->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.booking-platforms.index')->with('success', "{$platform->name} {$state}.");
    }

    public function destroy(BookingPlatform $platform): RedirectResponse
    {
        try {
            $this->platforms->delete($platform);
        } catch (PlatformInUseException $e) {
            return redirect()->route('admin.booking-platforms.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.booking-platforms.index')->with('success', "{$platform->name} deleted.");
    }
}
