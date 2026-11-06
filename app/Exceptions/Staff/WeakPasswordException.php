<?php

declare(strict_types=1);

namespace App\Exceptions\Staff;

use RuntimeException;

final class WeakPasswordException extends RuntimeException
{
    public static function tooShort(int $minLength): self
    {
        return new self("The password must be at least {$minLength} characters.");
    }
}
