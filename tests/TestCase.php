<?php

namespace Tests;

use App\Support\SignupGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No real DNS lookups in tests: every domain has a mail server unless a test says otherwise
        SignupGuard::resolveMxUsing(fn () => true);
        SignupGuard::flush();
    }
}
