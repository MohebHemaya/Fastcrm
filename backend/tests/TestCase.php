<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.connections.'.config('database.default').'.database');
        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException(
                "Testing aborted! Database name '{$database}' does not end with '_test'. Tests must run against a dedicated test database (e.g. driver_system_test)."
            );
        }
    }
}
