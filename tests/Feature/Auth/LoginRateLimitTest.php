<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

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
}
