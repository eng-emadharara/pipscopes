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

        // actingAs() skips a real sign-in; tests of the admin sign-in limit turn it on themselves
        config(['trust.admin_session_days' => 0, 'trust.admin_email_2fa' => false]);
    }
}
