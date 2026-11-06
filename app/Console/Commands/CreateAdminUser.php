<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\Staff\AccountAlreadyExistsException;
use App\Exceptions\Staff\WeakPasswordException;
use App\Services\Staff\AdminAccountService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

final class CreateAdminUser extends Command
{
    protected $signature = 'users:create-admin
        {email : Login email for the new admin}
        {--name=Administrator : Display name}
        {--password-env= : Read the password from this environment variable instead of prompting (for Cloud Run Jobs, which have no TTY)}';

    protected $description = 'Create an admin account (and the admin/staff roles if missing). Used to bootstrap production.';

    public function handle(AdminAccountService $accounts): int
    {
        $email = (string) $this->argument('email');
        $name = (string) $this->option('name');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name],
            ['email' => ['required', 'email', 'max:255'], 'name' => ['required', 'string', 'max:255']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        if ($accounts->accountExists($email)) {
            $this->error(AccountAlreadyExistsException::forEmail(mb_strtolower(trim($email)))->getMessage());

            return self::FAILURE;
        }

        $password = $this->readPassword();

        if ($password === null) {
            return self::FAILURE;
        }

        try {
            $user = $accounts->createAdmin($name, $email, $password);
        } catch (AccountAlreadyExistsException|WeakPasswordException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Admin account [{$user->email}] created.");

        return self::SUCCESS;
    }

    /**
     * The password comes from a named env var (non-interactive) or from a hidden, confirmed prompt.
     * Returns null after printing the reason when no usable password was given.
     */
    private function readPassword(): ?string
    {
        $envName = $this->option('password-env');

        if (is_string($envName) && $envName !== '') {
            $password = getenv($envName);

            if (! is_string($password) || $password === '') {
                $this->error("The environment variable [{$envName}] is not set or empty.");

                return null;
            }

            return $password;
        }

        $password = (string) $this->secret('Password (min '.AdminAccountService::MIN_PASSWORD_LENGTH.' characters)');
        $confirmation = (string) $this->secret('Confirm password');

        if ($password !== $confirmation) {
            $this->error('The passwords do not match.');

            return null;
        }

        return $password;
    }
}
