<?php

declare(strict_types=1);

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Collection;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;
use RuntimeException;

/**
 * Base for E2E BDD tests. Run inside the `dusk-app` container:
 *   docker compose exec dusk-app php artisan dusk
 * See docs/testing/bdd.md.
 */
abstract class DuskTestCase extends BaseTestCase
{
    use DatabaseTruncation;

    /** Re-seed roles, Admin/Staff users and contact settings after each truncation. */
    protected bool $seed = true;

    /**
     * Guard: Dusk truncates tables, so it must only ever touch the testing database.
     */
    protected function beforeTruncatingDatabase(): void
    {
        $database = config('database.connections.'.config('database.default').'.database');

        if ($database !== 'iconvenue_testing') {
            throw new RuntimeException(
                "Refusing to run Dusk against database [{$database}]. Run via: docker compose exec dusk-app php artisan dusk"
            );
        }
    }

    /**
     * Every scenario starts as a signed-out Guest: Dusk reuses the browser across
     * tests, so drop the previous scenario's session cookies.
     */
    protected function tearDown(): void
    {
        foreach (static::$browsers as $browser) {
            $browser->driver->manage()->deleteAllCookies();
        }

        parent::tearDown();
    }

    #[BeforeClass]
    public static function prepare(): void
    {
        // Remote Selenium (docker `selenium` service) needs no local ChromeDriver.
        if (! static::runningInSail() && ! env('DUSK_DRIVER_URL')) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}
