<?php

declare(strict_types=1);

namespace App\Data\Venue;

use App\Models\BookingPlatform;

/**
 * The nightly rate a suite booking is priced at, and where it came from.
 */
final readonly class SuiteRateQuote
{
    public function __construct(
        public float $ratePerNight,
        public ?BookingPlatform $platform,
        public bool $usedFallback,
    ) {}

    public function platformId(): ?int
    {
        return $this->platform?->id;
    }

    public function total(int $nights): float
    {
        return round($this->ratePerNight * $nights, 2);
    }
}
