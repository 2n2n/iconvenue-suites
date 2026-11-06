<?php

declare(strict_types=1);

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;

class LoginPage extends Page
{
    public function url(): string
    {
        return '/login';
    }

    public function assert(Browser $browser): void
    {
        $browser->assertPathIs($this->url())
            ->assertPresent('@login-form');
    }

    /**
     * @return array<string, string>
     */
    public function elements(): array
    {
        return [
            '@email' => '[dusk="login-email"]',
            '@password' => '[dusk="login-password"]',
            '@submit' => '[dusk="login-submit"]',
            '@errors' => '[dusk="login-errors"]',
        ];
    }

    public function signIn(Browser $browser, string $email, string $password): void
    {
        $browser->type('@email', $email)
            ->type('@password', $password)
            ->click('@submit');
    }
}
