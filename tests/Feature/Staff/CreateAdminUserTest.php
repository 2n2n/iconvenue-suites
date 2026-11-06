<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class CreateAdminUserTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD_PROMPT = 'Password (min 12 characters)';

    private const CONFIRM_PROMPT = 'Confirm password';

    public function test_admin_creates_the_first_admin_account_to_bootstrap_production(): void
    {
        $this->artisan('users:create-admin', ['email' => 'Owner@Example.com', '--name' => 'Owner'])
            ->expectsQuestion(self::PASSWORD_PROMPT, 'a-strong-password')
            ->expectsQuestion(self::CONFIRM_PROMPT, 'a-strong-password')
            ->expectsOutput('Admin account [owner@example.com] created.')
            ->assertSuccessful();

        $admin = User::where('email', 'owner@example.com')->firstOrFail();

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->is_active);
        $this->assertSame('Owner', $admin->name);
        $this->assertTrue(Hash::check('a-strong-password', $admin->password));
        $this->assertEqualsCanonicalizing(['admin', 'staff'], Role::pluck('name')->all());
    }

    public function test_admin_retries_an_existing_email_to_keep_the_original_account_safe(): void
    {
        $this->artisan('users:create-admin', ['email' => 'owner@example.com'])
            ->expectsQuestion(self::PASSWORD_PROMPT, 'a-strong-password')
            ->expectsQuestion(self::CONFIRM_PROMPT, 'a-strong-password')
            ->assertSuccessful();

        $originalHash = User::where('email', 'owner@example.com')->value('password');

        $this->artisan('users:create-admin', ['email' => 'OWNER@example.com'])
            ->expectsOutput('An account with the email [owner@example.com] already exists.')
            ->assertFailed();

        $this->assertSame(1, User::count());
        $this->assertSame(1, Role::where('name', 'admin')->count());
        $this->assertSame($originalHash, User::where('email', 'owner@example.com')->value('password'));
    }

    public function test_admin_supplies_the_password_by_env_var_to_bootstrap_from_a_cloud_run_job(): void
    {
        putenv('TEST_ADMIN_BOOTSTRAP_PASSWORD=a-strong-password');

        try {
            $this->artisan('users:create-admin', [
                'email' => 'owner@example.com',
                '--password-env' => 'TEST_ADMIN_BOOTSTRAP_PASSWORD',
            ])
                ->expectsOutput('Admin account [owner@example.com] created.')
                ->assertSuccessful();
        } finally {
            putenv('TEST_ADMIN_BOOTSTRAP_PASSWORD');
        }

        $admin = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue(Hash::check('a-strong-password', $admin->password));
    }

    public function test_admin_names_a_missing_env_var_to_be_told_nothing_was_created(): void
    {
        putenv('TEST_ADMIN_BOOTSTRAP_PASSWORD');

        $this->artisan('users:create-admin', [
            'email' => 'owner@example.com',
            '--password-env' => 'TEST_ADMIN_BOOTSTRAP_PASSWORD',
        ])
            ->expectsOutput('The environment variable [TEST_ADMIN_BOOTSTRAP_PASSWORD] is not set or empty.')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_admin_supplies_a_short_password_by_env_var_to_be_refused(): void
    {
        putenv('TEST_ADMIN_BOOTSTRAP_PASSWORD=short');

        try {
            $this->artisan('users:create-admin', [
                'email' => 'owner@example.com',
                '--password-env' => 'TEST_ADMIN_BOOTSTRAP_PASSWORD',
            ])
                ->expectsOutput('The password must be at least 12 characters.')
                ->assertFailed();
        } finally {
            putenv('TEST_ADMIN_BOOTSTRAP_PASSWORD');
        }

        $this->assertSame(0, User::count());
    }

    public function test_admin_enters_a_short_password_to_be_asked_for_a_stronger_one(): void
    {
        $this->artisan('users:create-admin', ['email' => 'owner@example.com'])
            ->expectsQuestion(self::PASSWORD_PROMPT, 'short')
            ->expectsQuestion(self::CONFIRM_PROMPT, 'short')
            ->expectsOutput('The password must be at least 12 characters.')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
