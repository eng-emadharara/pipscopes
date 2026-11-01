<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\SocialKeys;
use App\Support\TelegramAppLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Signing in through the Telegram app instead of the popup that asks for a phone number. */
class TelegramAppLoginTest extends TestCase
{
    use RefreshDatabase;

    protected const TOKEN = 'abcdefghijABCDEFGHIJ01234567890123456789';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.bot_token' => '123456:TEST']);
    }

    protected function turnOn(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
        Setting::put([TelegramAppLogin::SETTING => ['enabled' => true, 'bot_username' => 'PipScopesBot']]);
    }

    protected function webhook(array $update, ?string $secret = null)
    {
        return $this->postJson(route('telegram.webhook'), $update, ['X-Telegram-Bot-Api-Secret-Token' => $secret ?? TelegramAppLogin::webhookSecret()]);
    }

    protected function press(string $action = 'ok', string $token = self::TOKEN, ?int $sentAt = null, int $telegramId = 777)
    {
        return $this->webhook(['callback_query' => [
            'id' => 'cb1', 'data' => "tgl:$action:$token",
            'from' => ['id' => $telegramId, 'first_name' => 'Sara', 'last_name' => 'Ali', 'username' => 'sara_a', 'language_code' => 'ar'],
            'message' => ['message_id' => 5, 'date' => $sentAt ?? now()->timestamp, 'chat' => ['id' => $telegramId]],
        ]]);
    }

    protected function askStatus()
    {
        return $this->withSession([TelegramAppLogin::SESSION_KEY => self::TOKEN])->getJson(route('social.telegram.app-status'));
    }

    public function test_the_popup_stays_until_switched_on(): void
    {
        $this->get('/login')->assertOk()->assertSee('data-bot-id', false)->assertDontSee('data-telegram-app', false);
        $this->getJson(route('social.telegram.app-status'))->assertNotFound();
        $this->webhook(['message' => []])->assertForbidden();

        $this->turnOn();
        $this->get('/login')->assertSee('data-telegram-app', false)->assertSee('https://t.me/PipScopesBot?start=login_', false);
    }

    public function test_start_asks_for_confirmation_and_nothing_signs_in_until_then(): void
    {
        $this->turnOn();

        $this->webhook(['message' => ['text' => '/start login_'.self::TOKEN, 'chat' => ['id' => 777, 'type' => 'private'], 'from' => ['id' => 777]]])->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && $request['reply_markup']['inline_keyboard'][0][0]['callback_data'] === 'tgl:ok:'.self::TOKEN);

        $this->askStatus()->assertJson(['status' => 'pending']);
        $this->assertGuest();
    }

    public function test_confirming_signs_in_the_browser_that_asked_once(): void
    {
        $this->turnOn();
        $this->press()->assertOk();

        $this->askStatus()->assertJson(['status' => 'done'])->assertJsonStructure(['redirect']);
        $this->assertAuthenticated();

        $account = SocialAccount::where('provider', 'telegram')->where('provider_id', '777')->firstOrFail();
        $this->assertSame('Sara Ali', $account->user->name);
        $this->assertSame(0, DB::table('telegram_app_logins')->count(), 'a confirmation works once');
    }

    public function test_another_browser_cannot_use_the_confirmation(): void
    {
        $this->turnOn();
        $this->press();

        $this->withSession([TelegramAppLogin::SESSION_KEY => 'someone-elses-token'])->getJson(route('social.telegram.app-status'))
            ->assertJson(['status' => 'pending']);
        $this->getJson(route('social.telegram.app-status'))->assertJson(['status' => 'pending']);
        $this->assertGuest();
    }

    public function test_cancelled_and_expired_requests_do_not_sign_in(): void
    {
        $this->turnOn();

        $this->press('no');
        $this->askStatus()->assertJson(['status' => 'pending']);

        $this->press(sentAt: now()->subMinutes(TelegramAppLogin::VALID_MINUTES + 1)->timestamp);
        $this->askStatus()->assertJson(['status' => 'pending']);

        // Confirmed, but the browser came back too late
        $this->press();
        $this->travel(TelegramAppLogin::VALID_MINUTES + 1)->minutes();
        $this->askStatus()->assertJson(['status' => 'pending']);
        $this->assertGuest();
    }

    public function test_only_telegram_can_call_the_webhook(): void
    {
        $this->turnOn();

        $this->press()->assertOk();
        DB::table('telegram_app_logins')->delete();

        $this->webhook(['callback_query' => ['id' => 'x', 'data' => 'tgl:ok:'.self::TOKEN, 'from' => ['id' => 1]]], 'wrong-secret')->assertForbidden();
        $this->assertSame(0, DB::table('telegram_app_logins')->count());
    }

    public function test_a_signed_in_member_links_through_the_app_and_the_bot_can_write_again(): void
    {
        $this->turnOn();
        $member = User::factory()->create(['telegram_blocked_at' => now()]);

        $this->actingAs($member)->get(route('profile.edit'))->assertSee('data-telegram-app', false);

        $this->press();
        $this->actingAs($member)->askStatus()->assertJson(['status' => 'done']);

        $this->assertSame('777', $member->socialAccounts()->where('provider', 'telegram')->value('provider_id'));
        $this->assertNull($member->fresh()->telegram_blocked_at);
    }

    public function test_a_telegram_account_of_another_member_is_refused(): void
    {
        $this->turnOn();
        User::factory()->create()->socialAccounts()->create(['provider' => 'telegram', 'provider_id' => '777']);
        $member = User::factory()->create();

        $this->press();
        $this->actingAs($member)->askStatus()->assertJson(['status' => 'done'])->assertSessionHas('error');

        $this->assertFalse($member->socialAccounts()->exists());
    }

    /** The address on the bot's button after the last confirmation */
    protected function buttonUrl(): ?string
    {
        $edits = Http::recorded(fn ($request) => str_contains($request->url(), 'editMessageText'));

        return data_get($edits->last()[0]->data(), 'reply_markup.inline_keyboard.0.0.url');
    }

    public function test_the_button_signs_in_once_and_lands_on_the_page_they_came_from(): void
    {
        $this->turnOn();
        $page = url('/p/some-channel');

        // The page was waiting, heading back to a channel
        $this->withSession([TelegramAppLogin::SESSION_KEY => self::TOKEN, 'url.intended' => $page])
            ->getJson(route('social.telegram.app-status'))->assertJson(['status' => 'pending']);

        $this->press()->assertOk();
        $url = $this->buttonUrl();
        $this->assertNotNull($url);
        $this->assertStringContainsString('signature=', $url);

        // Another browser: the button's link has its own session
        $this->flushSession();
        $this->get($url)->assertRedirect($page);
        $this->assertSame('777', SocialAccount::where('provider', 'telegram')->value('provider_id'));
        $this->assertAuthenticated();

        // Works only once
        auth()->logout();
        $this->flushSession();
        $this->get($url)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_button_link_expires_and_cannot_be_forged(): void
    {
        $this->turnOn();
        $this->press();
        $url = $this->buttonUrl();

        $this->get($url.'x')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->travel(TelegramAppLogin::VALID_MINUTES + 1)->minutes();
        $this->get($url)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_when_linking_the_button_only_opens_the_page(): void
    {
        $this->turnOn();
        $member = User::factory()->create();
        $page = route('profile.edit');

        $this->actingAs($member)->withSession([TelegramAppLogin::SESSION_KEY => self::TOKEN])
            ->withHeader('referer', $page)->getJson(route('social.telegram.app-status'))->assertJson(['status' => 'pending']);

        $this->press();

        $this->assertSame($page, $this->buttonUrl());
        $this->assertFalse(SocialAccount::where('provider', 'telegram')->exists());
    }

    public function test_a_waiting_page_outside_the_site_is_ignored(): void
    {
        $this->turnOn();
        $member = User::factory()->create();

        $this->actingAs($member)->withSession([TelegramAppLogin::SESSION_KEY => self::TOKEN])
            ->withHeader('referer', 'https://evil.example/phish')->getJson(route('social.telegram.app-status'));
        $this->press();

        $this->assertSame(route('home'), $this->buttonUrl());
    }

    public function test_a_refused_button_still_answers_the_member(): void
    {
        Setting::put([TelegramAppLogin::SETTING => ['enabled' => true, 'bot_username' => 'PipScopesBot']]);
        Http::fake(fn ($request) => isset($request['reply_markup'])
            ? Http::response(['ok' => false, 'description' => 'Bad Request: BUTTON_URL_INVALID'], 400)
            : Http::response(['ok' => true, 'result' => []]));

        $this->press()->assertOk();

        $this->assertSame(1, DB::table('telegram_app_logins')->count());
        Http::assertSent(fn ($request) => str_contains($request->url(), 'editMessageText') && ! isset($request['reply_markup']));
    }

    public function test_the_admin_switch_connects_the_bot_and_goes_back(): void
    {
        Http::fake(['api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'PipScopesBot']]),
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['url' => '']])]);
        SocialKeys::save(['telegram' => ['bot_token' => '123456:TEST']]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        \Livewire\Livewire::test(\App\Filament\Pages\SocialLoginSettings::class)->set('data.telegram_app', true)->call('save')->assertHasNoErrors();

        $this->assertTrue(TelegramAppLogin::isOn());
        Http::assertSent(fn ($request) => str_contains($request->url(), 'setWebhook')
            && $request['url'] === route('telegram.webhook') && $request['secret_token'] === TelegramAppLogin::webhookSecret());

        \Livewire\Livewire::test(\App\Filament\Pages\SocialLoginSettings::class)->assertSet('data.telegram_app', true)->set('data.telegram_app', false)->call('save');

        $this->assertFalse(TelegramAppLogin::isOn());
        Http::assertSent(fn ($request) => str_contains($request->url(), 'deleteWebhook'));
        auth()->logout();
        $this->get('/login')->assertDontSee('data-telegram-app', false)->assertSee('data-bot-id', false);
    }

    public function test_a_bot_used_by_another_service_is_not_taken_over(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['url' => 'https://other-service.example/hook']])]);

        try {
            TelegramAppLogin::enable();
            $this->fail('Should refuse');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('other-service.example', $e->getMessage());
        }

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'setWebhook'));
        $this->assertFalse(TelegramAppLogin::isOn());
    }
}
