<?php

declare(strict_types=1);

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;

/**
 * A client's page in the admin panel: details, booking history and (admin only) the Edit Client form.
 * The key is the client's email, or "tel-<phone>" when the client has no email.
 */
class ClientPage extends Page
{
    public function __construct(private readonly string $key) {}

    public function url(): string
    {
        return '/admin/clients/'.$this->key;
    }

    public function assert(Browser $browser): void
    {
        $browser->waitForPageToSettle()
            ->assertPathIs($this->url())
            ->assertPresent('@client-name');
    }

    /**
     * @return array<string, string>
     */
    public function elements(): array
    {
        return [
            '@client-name' => '[dusk="client-name"]',
            '@email-line' => '[dusk="client-email-line"]',
            '@phone-line' => '[dusk="client-phone-line"]',
            '@edit' => '[dusk="client-edit"]',
            '@edit-modal' => '[dusk="client-edit-modal"]',
            '@edit-name' => '[dusk="client-edit-name"]',
            '@edit-email' => '[dusk="client-edit-email"]',
            '@edit-phone' => '[dusk="client-edit-phone"]',
            '@edit-save' => '[dusk="client-edit-save"]',
        ];
    }

    public function openEditForm(Browser $browser): void
    {
        $browser->click('@edit')
            ->waitFor('@edit-name');
    }

    public function changeDetails(Browser $browser, string $name, ?string $email, string $phone): void
    {
        $browser->clear('@edit-name')->type('@edit-name', $name)
            ->clear('@edit-email')->type('@edit-email', (string) $email)
            ->clear('@edit-phone')->type('@edit-phone', $phone);
    }

    public function saveChanges(Browser $browser): void
    {
        $browser->clickAndWaitForReload('@edit-save')
            ->waitForPageToSettle();
    }
}
