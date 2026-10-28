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

/** Social sign-in keys edited in the admin panel: the only source (.env is ignored), stored encrypted, secrets kept when left empty. */
class SocialKeysTest extends TestCase
{
    use RefreshDatabase;

    protected const BOT_TOKEN = '123456789:AAFakeTokenForTests_abcdefghijklmn';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_env_keys_are_ignored(): void
    {
        $this->assertNull(config('services.google.client_id'));
        $this->assertNull(config('services.facebook.client_secret'));
        $this->assertNull(config('services.telegram.bot_token'));
        $this->assertSame([], SocialLogin::enabled());
    }

    public function test_saved_keys_are_encrypted_and_applied(): void
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
        $this->assertNull(config('services.google.client_id'));
        $this->assertSame(['facebook', 'telegram'], SocialLogin::enabled());
        $this->assertTrue(SocialKeys::isSet('facebook', 'client_secret'));
        $this->assertFalse(SocialKeys::isSet('google', 'client_secret'));
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

    public function test_clearing_a_provider_unsets_its_keys(): void
    {
        SocialKeys::save(['google' => ['client_id' => 'panel-gid', 'client_secret' => 'panel-gsecret']]);

        Livewire::test(SocialLoginSettings::class)
            ->call('clear', 'google');

        $this->assertArrayNotHasKey('google', SocialKeys::stored());
        $this->assertFalse(SocialKeys::isSet('google', 'client_id'));
    }
}
