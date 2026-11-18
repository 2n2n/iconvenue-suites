<?php

declare(strict_types=1);

namespace App\Data\Venue;

use App\Models\BookingPlatform;
use App\Models\Venue;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Everything the Room Rates grid shows: suites (rows), platforms (columns) and the platform rates.
 */
final readonly class RateGrid
{
    /**
     * @param  Collection<int, Venue>  $suites
     * @param  Collection<int, BookingPlatform>  $platforms
     * @param  array<int, array<int, float>>  $rates  suite id => [platform id => nightly rate]
     * @param  array<int, CarbonInterface|null>  $lastUpdated  suite id => newest change to its rates
     */
    public function __construct(
        public Collection $suites,
        public Collection $platforms,
        public array $rates,
        public array $lastUpdated,
    ) {}

    public function rate(int $suiteId, int $platformId): ?float
    {
        return $this->rates[$suiteId][$platformId] ?? null;
    }

    public function lastUpdated(int $suiteId): ?CarbonInterface
    {
        return $this->lastUpdated[$suiteId] ?? null;
    }
}
