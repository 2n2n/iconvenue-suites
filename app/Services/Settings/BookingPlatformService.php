<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Exceptions\Settings\PlatformInUseException;
use App\Models\BookingPlatform;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin-managed list of third-party booking platforms (Agoda, Booking.com, …).
 */
final class BookingPlatformService
{
    /** @return Collection<int, BookingPlatform> */
    public function all(): Collection
    {
        return BookingPlatform::ordered()->withCount(['bookings', 'rates'])->get();
    }

    /** @return Collection<int, BookingPlatform> */
    public function active(): Collection
    {
        return BookingPlatform::active()->ordered()->get();
    }

    public function create(string $name, int $sortOrder = 0): BookingPlatform
    {
        return BookingPlatform::create([
            'name' => trim($name),
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    public function update(BookingPlatform $platform, string $name, int $sortOrder): BookingPlatform
    {
        $platform->update(['name' => trim($name), 'sort_order' => $sortOrder]);

        return $platform;
    }

    public function toggle(BookingPlatform $platform): BookingPlatform
    {
        $platform->update(['is_active' => ! $platform->is_active]);

        return $platform;
    }

    /**
     * @throws PlatformInUseException when bookings reference the platform
     */
    public function delete(BookingPlatform $platform): void
    {
        if ($platform->bookings()->withTrashed()->exists()) {
            throw PlatformInUseException::for($platform);
        }

        DB::transaction(function () use ($platform): void {
            $platform->rates()->delete();
            $platform->delete();
        });
    }
}
