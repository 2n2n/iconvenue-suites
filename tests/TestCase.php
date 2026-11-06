<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Guard: RefreshDatabase runs migrate:fresh. Only ever allow that on in-memory SQLite,
     * so a misconfigured environment (e.g. Docker env vars overriding phpunit.xml) can never
     * wipe the dev database again. See docs/testing/bdd.md.
     */
    protected function beforeRefreshingDatabase()
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Refusing to refresh database [{$connection}:{$database}]. PHPUnit tests must run on in-memory SQLite (check phpunit.xml)."
            );
        }
    }
}
