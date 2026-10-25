<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The admin panel theme is a Vite asset; tests shouldn't need a front-end build.
        $this->withoutVite();
    }
}
