<?php

declare(strict_types=1);

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;

class BookingPlatformsPage extends Page
{
    public function url(): string
    {
        return '/admin/booking-platforms';
    }

    public function assert(Browser $browser): void
    {
        $browser->waitForPageToSettle()
            ->assertPathIs($this->url())
            ->assertSee('Add Platform');
    }

    /**
     * @return array<string, string>
     */
    public function elements(): array
    {
        return [
            '@name' => '[dusk="platform-name"]',
            '@add' => '[dusk="platform-add"]',
        ];
    }

    public function addPlatform(Browser $browser, string $name): void
    {
        $browser->type('@name', $name)
            ->clickAndWaitForReload('@add');
    }

    public function toggle(Browser $browser, int $platformId): void
    {
        $browser->clickAndWaitForReload("@platform-toggle-{$platformId}");
    }
}
