<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_repeats_failed_logins_to_get_temporarily_locked_out(): void
    {
        $credentials = ['email' => 'owner@example.com', 'password' => 'wrong-password'];

        for ($i = 0; $i < AppServiceProvider::LOGIN_ATTEMPTS_PER_MINUTE; $i++) {
            $this->from('/login')->post('/login', $credentials)
                ->assertRedirect('/login')
                ->assertSessionHasErrors(['email' => 'The provided credentials do not match our records.']);
        }

        $response = $this->from('/login')->post('/login', $credentials);

        $response->assertRedirect('/login');
        $response->assertHeader('Retry-After');
        $this->assertStringStartsWith(
            'Too many login attempts.',
            session('errors')->first('email'),
        );
    }

    public function test_guest_logs_in_with_another_email_to_stay_unaffected_by_someone_elses_lockout(): void
    {
        for ($i = 0; $i <= AppServiceProvider::LOGIN_ATTEMPTS_PER_MINUTE; $i++) {
            $this->from('/login')->post('/login', ['email' => 'attacker-target@example.com', 'password' => 'x']);
        }

        $this->from('/login')->post('/login', ['email' => 'colleague@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'The provided credentials do not match our records.']);
    }

    private function staffUser(): User
    {
        $role = Role::create(['name' => 'staff', 'description' => 'Staff']);

        return User::create(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'secret-password', 'role_id' => $role->id, 'is_active' => true]);
    }

    public function test_staff_signs_in_several_times_in_a_row_to_keep_working_without_being_locked_out(): void
    {
        $this->staffUser();
        $credentials = ['email' => 'staff@example.com', 'password' => 'secret-password'];

        for ($i = 0; $i < AppServiceProvider::LOGIN_ATTEMPTS_PER_MINUTE * 2; $i++) {
            $this->from('/login')->post('/login', $credentials)
                ->assertRedirect(route('admin.dashboard'))
                ->assertSessionHasNoErrors();
            $this->assertAuthenticated();

            auth()->logout();
        }
    }

    public function test_staff_is_still_locked_out_after_repeated_failures_to_keep_passwords_safe_from_guessing(): void
    {
        $this->staffUser();

        for ($i = 0; $i < AppServiceProvider::LOGIN_ATTEMPTS_PER_MINUTE; $i++) {
            $this->from('/login')->post('/login', ['email' => 'staff@example.com', 'password' => 'wrong-password']);
        }

        // Even the right password is refused until the minute passes
        $this->from('/login')->post('/login', ['email' => 'staff@example.com', 'password' => 'secret-password'])
            ->assertRedirect('/login');
        $this->assertGuest();
        $this->assertStringStartsWith('Too many login attempts.', session('errors')->first('email'));
    }
}
