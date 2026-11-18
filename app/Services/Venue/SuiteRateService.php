<?php

declare(strict_types=1);

namespace App\Services\Venue;

use App\Data\Venue\RateGrid;
use App\Data\Venue\SuiteRateQuote;
use App\Models\BookingPlatform;
use App\Models\SuitePlatformRate;
use App\Models\Venue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Per-platform nightly rates for suites. The suite's price_per_day is the regular rate;
 * a platform without a rate for the suite falls back to it.
 */
final class SuiteRateService
{
    /**
     * @return Collection<int, float> platform id => nightly rate
     */
    public function ratesFor(Venue $suite): Collection
    {
        return $suite->platformRates()
            ->pluck('price_per_night', 'booking_platform_id')
            ->map(fn ($price): float => (float) $price);
    }

    /**
     * @param  array<int|string, mixed>  $ratesByPlatformId  platform id => price; empty value removes the rate
     */
    public function syncRates(Venue $suite, array $ratesByPlatformId): void
    {
        DB::transaction(function () use ($suite, $ratesByPlatformId): void {
            foreach ($ratesByPlatformId as $platformId => $price) {
                $platformId = (int) $platformId;

                if ($price === null || $price === '') {
                    SuitePlatformRate::where('venue_id', $suite->id)
                        ->where('booking_platform_id', $platformId)
                        ->delete();

                    continue;
                }

                SuitePlatformRate::updateOrCreate(
                    ['venue_id' => $suite->id, 'booking_platform_id' => $platformId],
                    ['price_per_night' => round((float) $price, 2)],
                );
            }
        });
    }

    /**
     * Data for the Room Rates grid: active suites, all platforms and every platform rate (2 queries + platforms).
     */
    public function grid(): RateGrid
    {
        $suites = Venue::where('type', 'suite')
            ->where('is_active', true)
            ->orderBy('room_number')
            ->orderBy('name')
            ->get();

        $platforms = BookingPlatform::ordered()->get();

        $rates = [];
        $lastUpdated = [];
        foreach ($suites as $suite) {
            $lastUpdated[$suite->id] = $suite->updated_at;
        }

        SuitePlatformRate::whereIn('venue_id', $suites->modelKeys())
            ->get()
            ->each(function (SuitePlatformRate $rate) use (&$rates, &$lastUpdated): void {
                $rates[$rate->venue_id][$rate->booking_platform_id] = (float) $rate->price_per_night;
                $current = $lastUpdated[$rate->venue_id] ?? null;
                if ($rate->updated_at !== null && ($current === null || $rate->updated_at->greaterThan($current))) {
                    $lastUpdated[$rate->venue_id] = $rate->updated_at;
                }
            });

        return new RateGrid($suites, $platforms, $rates, $lastUpdated);
    }

    /**
     * Save edited grid cells in one transaction. Unchanged cells are skipped.
     * A cell is ['venue_id' => int, 'platform_id' => int|null, 'price' => numeric|null];
     * platform_id null = the suite's regular rate (price required), price null on a platform = remove that rate.
     *
     * @param  array<int, array{venue_id: int|string, platform_id?: int|string|null, price?: mixed}>  $cells
     * @return int number of cells actually changed
     */
    public function applyChanges(array $cells): int
    {
        return DB::transaction(function () use ($cells): int {
            $suites = Venue::where('type', 'suite')
                ->whereIn('id', array_map(fn (array $c): int => (int) $c['venue_id'], $cells))
                ->get()
                ->keyBy('id');

            // Current platform rates for these suites, keyed "suiteId:platformId" (one query, no N+1).
            $existing = SuitePlatformRate::whereIn('venue_id', $suites->modelKeys())
                ->get()
                ->mapWithKeys(fn (SuitePlatformRate $r): array => [
                    "{$r->venue_id}:{$r->booking_platform_id}" => round((float) $r->price_per_night, 2),
                ]);

            $changed = 0;
            foreach ($cells as $cell) {
                $suite = $suites->get((int) $cell['venue_id']);
                if ($suite === null) {
                    continue;
                }

                $platformId = isset($cell['platform_id']) && $cell['platform_id'] !== '' ? (int) $cell['platform_id'] : null;
                $price = isset($cell['price']) && $cell['price'] !== '' ? round((float) $cell['price'], 2) : null;

                if ($platformId === null) {
                    if ($price === null || round((float) $suite->price_per_day, 2) === $price) {
                        continue;
                    }
                    $suite->update(['price_per_day' => $price]);
                    $changed++;

                    continue;
                }

                $current = $existing->get("{$suite->id}:{$platformId}");
                if ($current === $price) {
                    continue;
                }

                $this->syncRates($suite, [$platformId => $price]);
                $suite->touch();
                $changed++;
            }

            return $changed;
        });
    }

    /**
     * Nightly rate for a suite booking. Null platform = regular rate.
     */
    public function quote(Venue $suite, ?int $platformId): SuiteRateQuote
    {
        $regular = (float) $suite->price_per_day;

        if ($platformId === null) {
            return new SuiteRateQuote($regular, null, false);
        }

        $platform = BookingPlatform::find($platformId);
        if ($platform === null) {
            return new SuiteRateQuote($regular, null, false);
        }

        $rate = $suite->platformRates()
            ->where('booking_platform_id', $platform->id)
            ->value('price_per_night');

        return $rate === null
            ? new SuiteRateQuote($regular, $platform, true)
            : new SuiteRateQuote((float) $rate, $platform, false);
    }
}
