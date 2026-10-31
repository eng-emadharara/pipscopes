<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\Setting;
use App\Models\User;
use App\Support\LoginThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Security headers and the per-account limit on wrong passwords. */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_send_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy')
            ->assertHeader('Permissions-Policy');
        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
        // Only in production over HTTPS
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_member_account_is_locked_after_many_wrong_passwords_from_any_ip(): void
    {
        User::factory()->create(['email' => 'sara@gmail.com', 'password' => 'secret-pass-1']);

        foreach (range(1, LoginThrottle::MAX_ATTEMPTS) as $i) {
            // A different IP each time, so the route throttle never applies
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.$i"])
                ->post('/login', ['email' => 'sara@gmail.com', 'password' => 'wrong'.$i]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->post('/login', ['email' => 'SARA@gmail.com', 'password' => 'secret-pass-1'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_successful_member_sign_in_resets_the_count(): void
    {
        User::factory()->create(['email' => 'sara@gmail.com', 'password' => 'secret-pass-1']);

        foreach (range(1, LoginThrottle::MAX_ATTEMPTS - 1) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.$i"])
                ->post('/login', ['email' => 'sara@gmail.com', 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => 'sara@gmail.com', 'password' => 'secret-pass-1']);
        $this->assertAuthenticated();

        $this->assertSame(0, LoginThrottle::wait('web', 'sara@gmail.com'));
    }

    public function test_owner_account_is_locked_after_many_wrong_passwords(): void
    {
        Setting::put(['owner_portal_enabled' => true]);
        $provider = Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/gold', 'is_active' => true]);
        $provider->owners()->create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'Secret-12345']);

        foreach (range(1, LoginThrottle::MAX_ATTEMPTS) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.$i"])
                ->post('/owner/login', ['email' => 'owner@example.com', 'password' => 'wrong']);
        }

        $this->post('/owner/login', ['email' => 'owner@example.com', 'password' => 'Secret-12345'])->assertSessionHasErrors('email');
        $this->assertGuest('owner');
    }
}
