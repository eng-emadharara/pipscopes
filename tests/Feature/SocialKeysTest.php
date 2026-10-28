<?php

namespace Tests\Feature;

use App\Filament\Pages\SocialLoginSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\SocialKeys;
use App\Support\SocialLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Social sign-in keys edited in the admin panel: stored encrypted, override .env, secrets kept when left empty. */
class SocialKeysTest extends TestCase
{
    use RefreshDatabase;

    protected const BOT_TOKEN = '123456789:AAFakeTokenForTests_abcdefghijklmn';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google' => ['client_id' => 'env-gid', 'client_secret' => 'env-gsecret', 'redirect' => '/auth/google/callback'],
            'services.facebook' => ['client_id' => null, 'client_secret' => null, 'redirect' => '/auth/facebook/callback'],
            'services.telegram.bot_token' => null,
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_saved_keys_are_encrypted_and_override_env(): void
    {
        $this->get('/admin/social-login')->assertOk()->assertSee('مفاتيح تسجيل الدخول الاجتماعي');

        Livewire::test(SocialLoginSettings::class)
            ->assertSet('data.google_callback', url('/auth/google/callback'))
            ->set('data.facebook.client_id', 'fid')
            ->set('data.facebook.client_secret', 'fsecret')
            ->set('data.telegram.bot_token', self::BOT_TOKEN)
            ->call('save')
            ->assertHasNoErrors();

        $raw = json_encode(Setting::get(SocialKeys::KEY));
        $this->assertStringNotContainsString('fsecret', $raw);
        $this->assertStringNotContainsString(self::BOT_TOKEN, $raw);

        SocialKeys::apply();

        $this->assertSame('fid', config('services.facebook.client_id'));
        $this->assertSame('fsecret', config('services.facebook.client_secret'));
        $this->assertSame('env-gid', config('services.google.client_id'));
        $this->assertSame(['google', 'facebook', 'telegram'], SocialLogin::enabled());
        $this->assertSame('panel', SocialKeys::source('facebook', 'client_secret'));
        $this->assertSame('env', SocialKeys::source('google', 'client_secret'));
    }

    public function test_empty_secret_keeps_the_saved_one_and_secrets_are_not_sent_back(): void
    {
        SocialKeys::save(['facebook' => ['client_id' => 'fid', 'client_secret' => 'fsecret']]);

        Livewire::test(SocialLoginSettings::class)
            ->assertSet('data.facebook.client_id', 'fid')
            ->assertSet('data.facebook.client_secret', null)
            ->set('data.facebook.client_id', 'fid-2')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['client_id' => 'fid-2', 'client_secret' => 'fsecret'], SocialKeys::stored()['facebook']);
    }

    public function test_invalid_telegram_token_is_rejected(): void
    {
        Livewire::test(SocialLoginSettings::class)
            ->set('data.telegram.bot_token', 'not-a-token')
            ->call('save')
            ->assertHasErrors(['data.telegram.bot_token']);

        $this->assertArrayNotHasKey('telegram', SocialKeys::stored());
    }

    public function test_clearing_a_provider_falls_back_to_env(): void
    {
        SocialKeys::save(['google' => ['client_id' => 'panel-gid', 'client_secret' => 'panel-gsecret']]);

        Livewire::test(SocialLoginSettings::class)
            ->call('clear', 'google');

        $this->assertArrayNotHasKey('google', SocialKeys::stored());
        $this->assertSame('env', SocialKeys::source('google', 'client_id'));
    }
}
