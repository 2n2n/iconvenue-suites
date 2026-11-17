<?php

declare(strict_types=1);

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;

/**
 * Room Rates grid: one row per active suite, columns Regular + each booking platform.
 * Cells are addressed as "regular" or by platform id.
 */
class RoomRatesPage extends Page
{
    public function url(): string
    {
        return '/admin/rates';
    }

    public function assert(Browser $browser): void
    {
        $browser->waitForPageToSettle()
            ->assertPathIs($this->url())
            ->assertPresent('@manage-platforms');
    }

    public static function cell(int $suiteId, int|string $column): string
    {
        return "@rate-{$suiteId}-{$column}";
    }

    /** Replace a cell's value the way a user does (select all, type), so the grid sees the edit. */
    public function setCell(Browser $browser, int $suiteId, int|string $column, string $price): void
    {
        $cell = self::cell($suiteId, $column);
        $browser->click($cell)
            ->keys($cell, ['{control}', 'a'], '{backspace}');

        if ($price !== '') {
            $browser->keys($cell, $price);
        }
    }

    public function clearCell(Browser $browser, int $suiteId, int|string $column): void
    {
        $this->setCell($browser, $suiteId, $column, '');
    }

    /** "For selected rooms": fills cells only; nothing is saved until Save changes. */
    public function applyToSelected(Browser $browser, int|string $column, string $action, string $value): void
    {
        $browser->select('@bulk-column', $column === 'regular' ? '' : (string) $column)
            ->select('@bulk-action', $action)
            ->type('@bulk-value', $value)
            ->click('@bulk-apply');
    }

    public function saveChanges(Browser $browser): void
    {
        $browser->scrollIntoView('@save-changes')
            ->clickAndWaitForReload('@save-changes')
            ->waitForPageToSettle();
    }

    public function discardChanges(Browser $browser): void
    {
        $browser->scrollIntoView('@discard-changes')
            ->click('@discard-changes');
    }
}
