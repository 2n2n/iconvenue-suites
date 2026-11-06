<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Exceptions\Staff\AccountAlreadyExistsException;
use App\Exceptions\Staff\WeakPasswordException;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates admin accounts outside the web UI, e.g. the first admin of a fresh
 * production database (which is never seeded). See docs/deployment.md.
 */
final class AdminAccountService
{
    public const MIN_PASSWORD_LENGTH = 12;

    public function accountExists(string $email): bool
    {
        return User::where('email', $this->normaliseEmail($email))->exists();
    }

    /**
     * @throws AccountAlreadyExistsException
     * @throws WeakPasswordException
     */
    public function createAdmin(string $name, string $email, string $password): User
    {
        $email = $this->normaliseEmail($email);

        if ($this->accountExists($email)) {
            throw AccountAlreadyExistsException::forEmail($email);
        }

        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw WeakPasswordException::tooShort(self::MIN_PASSWORD_LENGTH);
        }

        return DB::transaction(function () use ($name, $email, $password): User {
            $adminRole = $this->ensureRoles();

            return User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password, // hashed by the User model's `hashed` cast
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]);
        });
    }

    /**
     * The app needs both roles to exist (staff accounts are created later in the admin UI).
     */
    private function ensureRoles(): Role
    {
        Role::firstOrCreate(['name' => 'staff'], ['description' => 'Staff member who manages bookings']);

        return Role::firstOrCreate(['name' => 'admin'], ['description' => 'Administrator with full system access']);
    }

    private function normaliseEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
