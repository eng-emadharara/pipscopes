<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    protected const BOT_TOKEN = '123456789:AAFakeTokenForTests_abcdefghijklmn';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config([
            'services.google' => ['client_id' => 'gid', 'client_secret' => 'gsecret', 'redirect' => '/auth/google/callback'],
            'services.facebook' => ['client_id' => 'fid', 'client_secret' => 'fsecret', 'redirect' => '/auth/facebook/callback'],
            'services.telegram.bot_token' => self::BOT_TOKEN,
        ]);
    }

    /** Each extra payload is what the provider returns on the next sign-in. */
    protected function fakeProvider(string $provider, array ...$signIns): void
    {
        $driver = \Mockery::mock();
        $driver->shouldReceive('fields')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn(...array_map($this->socialiteUser(...), $signIns));

        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }

    protected function socialiteUser(array $data): SocialiteUser
    {
        return (new SocialiteUser)->setRaw($data['raw'] ?? [])->map([
            'id' => $data['id'],
            'name' => $data['name'] ?? 'Sara Ali',
            'email' => $data['email'] ?? null,
            'avatar' => $data['avatar'] ?? 'https://example.com/a.png',
            'nickname' => null,
        ])->setToken($data['token'] ?? 'access-token')->setRefreshToken($data['refresh_token'] ?? null)->setExpiresIn($data['expires_in'] ?? 3599);
    }

    /** Signs a payload the way Telegram does. */
    protected function telegramPayload(array $fields): array
    {
        $fields += ['auth_date' => time()];
        ksort($fields);
        $check = collect($fields)->map(fn ($v, $k) => "$k=$v")->implode("\n");

        return $fields + ['hash' => hash_hmac('sha256', $check, hash('sha256', self::BOT_TOKEN, true))];
    }

    public function test_all_methods_show_and_unconfigured_ones_are_disabled(): void
    {
        $this->get('/login')->assertSee('/auth/google/redirect')->assertSee('data-bot-id', false)
            ->assertDontSee(__('قريباً'));

        config(['services.google.client_id' => null, 'services.telegram.bot_token' => null]);

        $this->get('/login')->assertSee('Google')->assertSee('Telegram')
            ->assertDontSee('/auth/google/redirect')->assertDontSee('data-bot-id', false)->assertSee(__('قريباً'));
        $this->get('/auth/google/redirect')->assertNotFound();
    }

    public function test_hidden_methods_are_left_off_the_sign_in_page(): void
    {
        $this->get('/login')->assertDontSee('/auth/facebook/redirect')->assertDontSee('social-btn-facebook', false);
    }

    public function test_google_sign_in_creates_a_verified_account(): void
    {
        $this->fakeProvider('google', ['id' => 'g-1', 'email' => 'Sara@Example.com', 'raw' => ['email_verified' => true]]);

        $this->get('/auth/google/callback?code=x')->assertRedirect(route('home'));

        $user = User::where('email', 'sara@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNull($user->password);
        $this->assertSame('https://example.com/a.png', $user->avatar_url);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-1']);
    }

    public function test_full_provider_payload_and_tokens_are_stored(): void
    {
        $raw = ['sub' => 'g-1', 'email_verified' => true, 'given_name' => 'Sara', 'family_name' => 'Ali', 'locale' => 'ar'];
        $this->fakeProvider('google',
            ['id' => 'g-1', 'email' => 'sara@example.com', 'raw' => $raw, 'refresh_token' => 'refresh-1'],
            ['id' => 'g-1', 'email' => 'sara@example.com', 'raw' => $raw, 'token' => 'access-2'],
        );
        $this->get('/auth/google/callback?code=x');
        auth()->logout();

        // A later sign-in without a refresh token keeps the one already stored
        $this->get('/auth/google/callback?code=x');

        $account = SocialAccount::firstOrFail();
        $this->assertSame($raw, $account->raw_data);
        $this->assertSame('Sara Ali', $account->name);
        $this->assertSame('access-2', $account->token);
        $this->assertSame('refresh-1', $account->refresh_token);
        $this->assertNotNull($account->token_expires_at);
        $this->assertNotSame('access-2', $account->getRawOriginal('token'), 'tokens are encrypted at rest');
    }

    public function test_returning_identity_signs_into_the_same_account(): void
    {
        $this->fakeProvider('google', ['id' => 'g-1', 'email' => 'sara@example.com', 'raw' => ['email_verified' => true]]);
        $this->get('/auth/google/callback?code=x');
        auth()->logout();

        $this->get('/auth/google/callback?code=x')->assertRedirect(route('home'));

        $this->assertSame(1, User::count());
        $this->assertSame(1, SocialAccount::count());
    }

    public function test_verified_email_links_to_existing_account(): void
    {
        $existing = User::factory()->create(['email' => 'sara@example.com']);
        $this->fakeProvider('facebook', ['id' => 'fb-1', 'email' => 'sara@example.com']);

        $this->get('/auth/facebook/callback?code=x');

        $this->assertAuthenticatedAs($existing);
        $this->assertNotNull($existing->fresh()->password, 'a verified account keeps its password');
        $this->assertSame(1, User::count());
    }

    public function test_linking_to_an_unverified_account_removes_the_squatters_password(): void
    {
        $squatted = User::factory()->unverified()->create(['email' => 'sara@example.com']);
        $this->fakeProvider('google', ['id' => 'g-1', 'email' => 'sara@example.com', 'raw' => ['email_verified' => true]]);

        $this->get('/auth/google/callback?code=x');

        $squatted->refresh();
        $this->assertAuthenticatedAs($squatted);
        $this->assertNull($squatted->password);
        $this->assertTrue($squatted->hasVerifiedEmail());
    }

    public function test_unverified_provider_email_never_links_to_an_existing_account(): void
    {
        User::factory()->create(['email' => 'sara@example.com']);
        $this->fakeProvider('google', ['id' => 'g-1', 'email' => 'sara@example.com', 'raw' => ['email_verified' => false]]);

        $this->get('/auth/google/callback?code=x')->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_cancelled_sign_in_returns_to_login(): void
    {
        $this->get('/auth/google/callback?error=access_denied')->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_telegram_sign_in_with_valid_signature(): void
    {
        $payload = $this->telegramPayload(['id' => '777', 'first_name' => 'Omar', 'username' => 'omar_fx']);

        // Telegram vouches for the identity, so no email step
        $this->post('/auth/telegram/callback', $payload)->assertRedirect(route('home'));

        $user = User::firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Omar', $user->name);
        $this->assertNull($user->email);
        $this->assertTrue($user->isVerifiedMember());
        $this->assertDatabaseHas('social_accounts', ['provider' => 'telegram', 'provider_id' => '777', 'nickname' => 'omar_fx']);
        $this->assertSame(['first_name' => 'Omar', 'id' => '777', 'username' => 'omar_fx'], array_diff_key(SocialAccount::firstOrFail()->raw_data, ['auth_date' => 1]));

        $this->get(route('home'))->assertDontSee(__('أضف بريدك الإلكتروني لتتمكن من كتابة التقييمات.'));
    }

    public function test_telegram_rejects_tampered_and_expired_payloads(): void
    {
        $tampered = ['id' => '999'] + $this->telegramPayload(['id' => '777', 'first_name' => 'Omar']);
        $this->post('/auth/telegram/callback', $tampered)->assertRedirect(route('login'));

        $expired = $this->telegramPayload(['id' => '777', 'first_name' => 'Omar', 'auth_date' => time() - 2 * 86400]);
        $this->post('/auth/telegram/callback', $expired)->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_member_without_email_adds_one_and_gets_a_code(): void
    {
        Mail::fake();
        $this->post('/auth/telegram/callback', $this->telegramPayload(['id' => '777', 'first_name' => 'Omar']));

        $this->get('/verify-email')->assertSee(__('أضف بريدك الإلكتروني'));
        $this->post('/verify-email/email', ['email' => 'Omar@Example.com'])->assertRedirect(route('verification.notice'));

        $this->assertSame('omar@example.com', User::first()->email);
        Mail::assertSent(VerificationCodeMail::class, fn ($mail) => $mail->hasTo('omar@example.com'));
    }

    public function test_cannot_add_an_email_used_by_another_account(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $this->post('/auth/telegram/callback', $this->telegramPayload(['id' => '777', 'first_name' => 'Omar']));

        $this->post('/verify-email/email', ['email' => 'taken@example.com'])->assertSessionHasErrors('email');
    }

    public function test_password_login_on_a_social_only_account_names_the_provider(): void
    {
        $this->fakeProvider('google', ['id' => 'g-1', 'email' => 'sara@example.com', 'raw' => ['email_verified' => true]]);
        $this->get('/auth/google/callback?code=x');
        auth()->logout();

        $this->post('/login', ['email' => 'sara@example.com', 'password' => 'whatever'])
            ->assertSessionHasErrors(['email' => __('هذا الحساب يسجّل الدخول عبر :provider. استخدم الزر بالأعلى.', ['provider' => 'Google'])]);
    }
}
