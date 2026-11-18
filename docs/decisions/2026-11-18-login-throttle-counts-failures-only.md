# 2026-11-18 — The login lockout counts failed sign-ins only

**Status:** Implemented

## Context
`POST /login` uses the `login` rate limiter (`AppServiceProvider::configureLoginRateLimit`): 5 attempts per minute,
keyed by email + IP. Introduced with [tailnet-only access](2026-11-06-tailnet-only-access.md) to slow password
guessing.

The limiter counted **every** attempt, including successful ones. Signing in, out and in again (a shared front-desk
device, switching between the admin and staff accounts, or the Dusk suite) locked the account out on the 6th sign-in
within a minute with "Too many login attempts", even though the password was right every time. Behind the tailnet
gateway all requests share one IP, so in production this is a per-email limit. Found during QA of the Room Rates work.

## What we discussed
- **Count only failed attempts** with Laravel's `Limit::after()`: a hit is recorded only when the request ends with
  nobody signed in (wrong password, inactive account or unauthorized role). **Chosen** (user asked to fix it): the
  framework's own tool, a one-callback change, and the guessing protection stays exactly as strict.
- **Raise the limit.** Rejected: it weakens the protection against guessing and only moves the problem.
- **Clear the counter on success** (`RateLimiter::clear`) in `AuthController`. Rejected: the middleware hashes its
  key, so the controller would duplicate the key logic; `after()` keeps all throttling in one place.

## Agreed decision
- `RateLimiter::for('login')` adds `->after(fn () => ! Auth::check())`.
- Unchanged: 5 failed attempts per minute per email + IP, the "Too many login attempts" message with the wait time,
  and once locked out, even the right password is refused until the minute passes.

## BDD scenarios
- Staff signs in several times in a row to keep working without being locked out (new)
- Guest repeats failed logins to get temporarily locked out (existing, must still pass)

## Scope / Out of scope
**In scope:** which attempts count toward the login lockout.

**Out of scope:** the limit value and window, and the cancel-via-edit bypass.

## Files affected
- `app/Providers/AppServiceProvider.php`
- `resources/views/layouts/admin.blade.php` (Dusk attributes only)
- `tests/Feature/Auth/LoginRateLimitTest.php`
- `tests/Browser/features/auth/sign-in-repeatedly-without-lockout.feature`, `tests/Browser/Auth/SignInRepeatedlyWithoutLockoutTest.php`

## Open questions
None.

## Deviations from plan
None. The Dusk test signs out through the admin profile menu, so `dusk="profile-menu"` and `dusk="logout-button"`
were added to `layouts/admin`. PHPUnit also pins that the right password is still refused during a lockout.
