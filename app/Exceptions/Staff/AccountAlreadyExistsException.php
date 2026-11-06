<?php

declare(strict_types=1);

namespace App\Exceptions\Staff;

use RuntimeException;

final class AccountAlreadyExistsException extends RuntimeException
{
    public static function forEmail(string $email): self
    {
        return new self("An account with the email [{$email}] already exists.");
    }
}
