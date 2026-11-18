<?php

declare(strict_types=1);

namespace Tests\Browser\Auth;

use App\Models\Role;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\LoginPage;
use Tests\DuskTestCase;

/**
 * @feature features/auth/sign-in-repeatedly-without-lockout.feature
 *
 * The `login` limiter is keyed by lower(email)|ip and lives in the dusk-app server's cache store,
 * which this test process cannot clear. The Staff member gets an email unique to the run, so no
 * counter left by another scenario (or an earlier run) can lock them out.
 */
class SignInRepeatedlyWithoutLockoutTest extends DuskTestCase
{
    /** More sign-ins than the failed-attempt limit allows within a minute. */
    private const SIGN_INS = AppServiceProvider::LOGIN_ATTEMPTS_PER_MINUTE + 2;

    /** @scenario features/auth/sign-in-repeatedly-without-lockout.feature:6 */
    public function test_staff_signs_in_several_times_in_a_row_to_keep_working_without_being_locked_out(): void
    {
        $email = 'staff-'.Str::lower(Str::random(10)).'@example.test';
        $password = 'staff-secret-123';

        User::create([
            'name' => 'Shift Staff',
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => Role::where('name', 'staff')->value('id'),
            'is_active' => true,
        ]);

        $this->browse(function (Browser $browser) use ($email, $password) {
            for ($signIn = 1; $signIn <= self::SIGN_INS; $signIn++) {
                $browser->visit(new LoginPage)
                    ->assertDontSee('Too many login attempts')
                    ->signIn($email, $password);

                $browser->waitForLocation('/admin/dashboard')
                    ->waitForPageToSettle()
                    ->assertSee('Dashboard')
                    ->assertSee('Shift Staff')
                    ->assertDontSee('Too many login attempts');

                // Sign out through the profile menu, as a Staff member handing over the device would.
                $browser->click('@profile-menu')
                    ->waitFor('@logout-button')
                    ->click('@logout-button')
                    ->waitUntilMissing('@profile-menu');
            }
        });
    }
}
