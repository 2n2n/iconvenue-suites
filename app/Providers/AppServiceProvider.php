<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Max login attempts per email + IP per minute (the admin login is public on Cloud Run).
     */
    public const LOGIN_ATTEMPTS_PER_MINUTE = 5;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        $this->configureLoginRateLimit();
    }

    /**
     * `throttle:login` on POST /login. Keyed by email + IP so one attacker can't lock out every
     * staff member behind the same office IP, and answers with the login form's own error instead of a bare 429.
     * Behind the tailnet gateway every request arrives from the gateway's IP, so in production this is
     * effectively a per-email limit (docs/decisions/2026-11-06-tailnet-only-access.md).
     */
    private function configureLoginRateLimit(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(self::LOGIN_ATTEMPTS_PER_MINUTE)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    $seconds = (int) ($headers['Retry-After'] ?? 60);

                    return back()
                        ->withErrors(['email' => "Too many login attempts. Please try again in {$seconds} seconds."])
                        ->withInput($request->only('email'))
                        ->withHeaders($headers);
                });
        });
    }
}
