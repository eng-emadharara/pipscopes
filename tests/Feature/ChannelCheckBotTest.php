<?php

namespace Tests\Feature;

use App\Models\Impersonator;
use App\Models\Provider;
use App\Models\Setting;
use App\Support\ChannelCheckBot;
use App\Support\TelegramAppLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Checking a channel through the bot: send a link or @name, get the report and its image. */
class ChannelCheckBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['services.telegram.bot_token' => '123456:TEST']);
        Setting::put([TelegramAppLogin::SETTING => ['enabled' => true, 'bot_username' => 'PipScopesBot']]);
    }

    /** Telegram's API, and a channel whose 12 posts are all "150pips done" with ~300 views for 20 000 subscribers */
    protected function fakeTelegram(array $api = []): void
    {
        $page = collect(range(1, 12))->map(fn ($i) => '<div class="tgme_widget_message" data-post="lowviews/'.(100 + $i).'">'
            .'<div class="tgme_widget_message_text js-message_text">150pips✔️ done</div>'
            .'<span class="tgme_widget_message_views">'.(290 + $i).'</span><span class="tgme_widget_message_meta">'
            .'<a class="tgme_widget_message_date" href="#"><time datetime="'.now()->subDays(14 - $i)->toIso8601String().'"></time></a></span></div>')->join('');

        Http::fake($api + [
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
            't.me/s/lowviews?*' => Http::response(''),
            't.me/s/lowviews' => Http::response($page),
            't.me/lowviews' => Http::response('<meta property="og:title" content="Low Views"><div class="tgme_page_title"></div><div class="tgme_page_extra">20 000 subscribers</div>'),
            '*' => Http::response('<meta property="og:title" content="Telegram: Contact @someone">'),
        ]);
    }

    protected function message(string $text, int $chat = 777)
    {
        return $this->postJson(route('telegram.webhook'), ['message' => ['text' => $text, 'chat' => ['id' => $chat, 'type' => 'private'], 'from' => ['id' => $chat]]],
            ['X-Telegram-Bot-Api-Secret-Token' => TelegramAppLogin::webhookSecret()]);
    }

    protected function sent(string $method): \Illuminate\Support\Collection
    {
        return collect(Http::recorded())->map(fn ($pair) => $pair[0])
            ->filter(fn (Request $r) => str_ends_with($r->url(), '/'.$method))->values();
    }

    public function test_a_channel_link_in_any_message_gets_the_report_with_its_image(): void
    {
        $this->fakeTelegram();

        $this->message('شو رأيكم بهالقناة https://t.me/lowviews ؟')->assertOk();

        $this->assertStringContainsString('جاري فحص القناة', $this->sent('sendMessage')->first()['text']);

        $photo = $this->sent('sendPhoto')->sole();
        $this->assertSame(route('share-images.check', ['ar', 'lowviews']), $photo['photo']);
        $this->assertStringContainsString('📊 '.__('تقرير قناة :name', ['name' => 'Low Views']), $photo['caption']);
        $this->assertStringContainsString('🔴 '.__('نسبة التفاعل').': 1.5%', $photo['caption']);
        $this->assertStringContainsString(__('صفقات :s · ادعاءات :c', ['s' => 0, 'c' => 12]), $photo['caption']);
        $this->assertLessThanOrEqual(1024, mb_strlen($photo['caption']));
        $this->assertStringContainsString('utm_medium=bot', $photo['reply_markup']['inline_keyboard'][0][0]['url']);
    }

    public function test_when_telegram_refuses_the_image_the_report_comes_as_text(): void
    {
        $this->fakeTelegram(['api.telegram.org/*/sendPhoto' => Http::response(['ok' => false, 'description' => 'wrong file identifier'], 400)]);

        $this->message('@lowviews')->assertOk();

        $this->assertStringContainsString(__('نسبة التفاعل'), $this->sent('sendMessage')->last()['text']);
    }

    public function test_the_sites_check_from_telegram_link_works_too(): void
    {
        $this->fakeTelegram();
        $this->message('/start check_lowviews')->assertOk();

        $this->assertCount(1, $this->sent('sendPhoto'));
    }

    public function test_listed_channels_link_to_their_page(): void
    {
        $this->fakeTelegram();
        $provider = Provider::create(['name' => 'Low Views', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/lowviews', 'is_active' => true]);

        $this->message('@lowviews');

        $photo = $this->sent('sendPhoto')->sole();
        $this->assertSame(route('providers.show', $provider), $photo['reply_markup']['inline_keyboard'][0][0]['url']);
        $this->assertStringContainsString(__('القناة موجودة على :app', ['app' => config('app.name')]), $photo['caption']);
    }

    public function test_impersonators_wrong_links_and_plain_messages_get_plain_answers(): void
    {
        $this->fakeTelegram();
        $provider = Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/goldpro', 'is_active' => true]);
        Impersonator::create(['provider_id' => $provider->id, 'platform' => 'telegram', 'handle' => '@goldpro_vip', 'status' => 'confirmed']);

        $this->message('t.me/goldpro_vip');
        $this->assertStringContainsString(__('تحذير: هذا حساب منتحل معروف'), $this->sent('sendMessage')->last()['text']);

        $this->message('@nosuchchannel');
        $this->assertStringStartsWith('❕', $this->sent('sendMessage')->last()['text']);

        // Not a channel: how to use it
        $this->message('مرحبا');
        $this->assertSame(ChannelCheckBot::help(), $this->sent('sendMessage')->last()['text']);

        // Start: the welcome with the site under it, then the keyboard (BotMenu)
        $this->message('/start');
        $this->assertSame(ChannelCheckBot::welcome(), $this->sent('sendMessage')->slice(-2, 1)->first()['text']);
        $this->assertTrue($this->sent('sendMessage')->last()['reply_markup']['is_persistent']);
        $this->assertCount(0, $this->sent('sendPhoto'));
    }

    public function test_the_keyboard_buttons_answer_with_the_site(): void
    {
        $this->fakeTelegram();
        $labels = \App\Support\BotMenu::labels();

        // Anything unknown answers with the keyboard, so members from before it get it too
        $this->message('مرحبا');
        $this->assertSame($labels['check'], $this->sent('sendMessage')->last()['reply_markup']['keyboard'][0][0]['text']);

        $this->message($labels['check']);
        $this->assertStringContainsString('t.me/channel', $this->sent('sendMessage')->last()['text']);

        $this->message($labels['scam']);
        $scam = $this->sent('sendMessage')->last();
        $this->assertStringStartsWith(route('scam-help'), $scam['reply_markup']['inline_keyboard'][0][0]['url']);
        $this->assertStringStartsWith(route('scam-reports.create'), $scam['reply_markup']['inline_keyboard'][1][0]['url']);

        $this->message($labels['channels']);
        $this->assertStringContainsString(__('لا تتابع أي قناة بعد.'), $this->sent('sendMessage')->last()['text']);

        $this->message($labels['tools']);
        $this->assertStringStartsWith(route('tools.money-management'), $this->sent('sendMessage')->last()['reply_markup']['inline_keyboard'][0][0]['url']);

        // None of them was taken for a channel to check
        $this->assertCount(0, $this->sent('sendPhoto'));
    }

    public function test_a_worrying_report_offers_to_report_a_scam(): void
    {
        $this->assertTrue(ChannelCheckBot::worrying(['cards' => ['engagement' => ['level' => 'bad']]]));
        $this->assertFalse(ChannelCheckBot::worrying(['cards' => ['engagement' => ['level' => 'good'], 'age' => ['level' => 'warn']]]));
        $this->assertTrue(ChannelCheckBot::worrying(['cards' => [], 'scam_cases' => collect([new \App\Models\ScamCase])]));

        $this->fakeTelegram();
        $this->message('t.me/lowviews');
        $buttons = collect($this->sent('sendPhoto')->last()['reply_markup']['inline_keyboard'])->flatten(1);
        $this->assertTrue($buttons->contains(fn ($b) => str_starts_with($b['url'] ?? '', route('scam-reports.create', ['account' => '@lowviews']))));
    }

    public function test_turning_the_bot_on_sets_its_description(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['url' => '', 'username' => 'PipScopesBot']])]);
        TelegramAppLogin::enable();

        $this->assertStringContainsString('نُصب عليك', $this->sent('setMyDescription')->first()['description']);
        $this->assertLessThanOrEqual(120, mb_strlen($this->sent('setMyShortDescription')->first()['short_description']));
        $this->assertContains('scam', collect($this->sent('setMyCommands')->first()['commands'])->pluck('command')->all());
    }

    public function test_the_check_page_points_to_the_bot(): void
    {
        $this->get(route('channel-check'))->assertSee('data-check-bot-link', false)->assertSee('https://t.me/PipScopesBot', false);

        Setting::put([TelegramAppLogin::SETTING => ['enabled' => false]]);
        $this->get(route('channel-check'))->assertDontSee('data-check-bot-link', false);
    }

    public function test_one_chat_cannot_check_endlessly(): void
    {
        $this->fakeTelegram();

        foreach (range(1, ChannelCheckBot::PER_TEN_MINUTES) as $i) {
            $this->message('@lowviews');
        }
        $this->message('@lowviews');

        $this->assertStringContainsString(__('طلبات كثيرة خلال وقت قصير. حاول مرة أخرى بعد دقائق.'), $this->sent('sendMessage')->last()['text']);
        $this->assertCount(ChannelCheckBot::PER_TEN_MINUTES, $this->sent('sendPhoto'));

        // Another chat isn't affected
        $this->message('@lowviews', chat: 888);
        $this->assertCount(ChannelCheckBot::PER_TEN_MINUTES + 1, $this->sent('sendPhoto'));
    }
}
