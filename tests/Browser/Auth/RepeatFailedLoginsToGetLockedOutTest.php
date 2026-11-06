<?php

declare(strict_types=1);

namespace Tests\Browser\Auth;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\LoginPage;
use Tests\DuskTestCase;

/**
 * @feature features/auth/repeat-failed-logins-to-get-locked-out.feature
 *
 * The `login` limiter is keyed by lower(email)|ip and lives in the dusk-app server's cache store,
 * which this test process cannot clear. Every scenario therefore uses emails unique to the run,
 * so lockouts never leak into other scenarios or other auth tests (they use seeded/fixed emails).
 */
class RepeatFailedLoginsToGetLockedOutTest extends DuskTestCase
{
    private const CREDENTIALS_ERROR = 'The provided credentials do not match our records.';

    private const LOCKOUT_ERROR = 'Too many login attempts. Please try again in';

    /** @scenario features/auth/repeat-failed-logins-to-get-locked-out.feature:6 */
    public function test_guest_repeats_failed_logins_to_get_temporarily_locked_out(): void
    {
        $email = $this->uniqueUnknownEmail('locked');

        $this->browse(function (Browser $browser) use ($email) {
            $browser->visit(new LoginPage);

            for ($attempt = 1; $attempt <= AppServiceProvider::LOGIN_ATTEMPTS_PER_MINUTE; $attempt++) {
                $browser->submitSignIn($email, 'wrong-password')
                    ->on(new LoginPage)
                    ->assertSeeIn('@errors', self::CREDENTIALS_ERROR)
                    ->assertDontSeeIn('@errors', 'Too many login attempts');
            }

            $browser->submitSignIn($email, 'wrong-password')
                ->on(new LoginPage)
                ->assertSeeIn('@errors', self::LOCKOUT_ERROR)
                ->assertInputValue('@email', $email);
        });
    }

    /** @scenario features/auth/repeat-failed-logins-to-get-locked-out.feature:14 */
    public function test_guest_logs_in_with_another_email_to_stay_unaffected_by_someone_elses_lockout(): void
    {
        $lockedEmail = $this->uniqueUnknownEmail('locked');
        $otherEmail = $this->uniqueUnknownEmail('other');

        $this->browse(function (Browser $browser) use ($lockedEmail, $otherEmail) {
            $browser->visit(new LoginPage);

            for ($attempt = 1; $attempt <= AppServiceProvider::LOGIN_ATTEMPTS_PER_MINUTE + 1; $attempt++) {
                $browser->submitSignIn($lockedEmail, 'wrong-password')->on(new LoginPage);
            }
            $browser->assertSeeIn('@errors', self::LOCKOUT_ERROR);

            $browser->submitSignIn($otherEmail, 'wrong-password')
                ->on(new LoginPage)
                ->assertSeeIn('@errors', self::CREDENTIALS_ERROR)
                ->assertDontSeeIn('@errors', 'Too many login attempts');
        });
    }

    private function uniqueUnknownEmail(string $prefix): string
    {
        return $prefix.'-'.Str::lower(Str::random(10)).'@example.test';
    }
}
