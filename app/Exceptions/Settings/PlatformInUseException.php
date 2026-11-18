<?php

declare(strict_types=1);

namespace App\Exceptions\Settings;

use App\Models\BookingPlatform;
use RuntimeException;

final class PlatformInUseException extends RuntimeException
{
    public static function for(BookingPlatform $platform): self
    {
        return new self("{$platform->name} is used by existing bookings and cannot be deleted. Deactivate it instead.");
    }
}
