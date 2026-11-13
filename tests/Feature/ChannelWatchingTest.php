<?php

namespace Tests\Feature;

use App\Jobs\SendWatchAlert;
use App\Models\ChannelWatch;
use App\Models\Impersonator;
use App\Models\Provider;
use App\Models\Review;
use App\Models\Setting;
use App\Models\TelegramPost;
use App\Models\User;
use App\Services\TelegramPosts;
use App\Support\ChannelWatching;
use App\Support\TelegramAppLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Following a channel through the bot, and the alerts that follow. */
class ChannelWatchingTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $channel;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.bot_token' => '123456:TEST']);
        Setting::put([TelegramAppLogin::SETTING => ['enabled' => true, 'bot_username' => 'PipScopesBot']]);
        $this->channel = Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/goldpro', 'is_active' => true]);
    }

    /** Telegram answering every call; a test that needs other answers fakes its own (an earlier fake would win) */
    protected function fakeBot(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
    }

    protected function update(array $update)
    {
        return $this->postJson(route('telegram.webhook'), $update, ['X-Telegram-Bot-Api-Secret-Token' => TelegramAppLogin::webhookSecret()]);
    }

    protected function message(string $text)
    {
        return $this->update(['message' => ['text' => $text, 'chat' => ['id' => 777, 'type' => 'private'], 'from' => ['id' => 777]]]);
    }

    protected function press(string $data)
    {
        return $this->update(['callback_query' => ['id' => 'cb', 'data' => $data, 'from' => ['id' => 777], 'message' => ['chat' => ['id' => 777], 'message_id' => 5, 'date' => now()->timestamp]]]);
    }

    protected function lastText(): string
    {
        return collect(Http::recorded())->map(fn ($p) => $p[0])->filter(fn (Request $r) => str_ends_with($r->url(), '/sendMessage'))->last()['text'];
    }

    public function test_the_site_link_follows_a_channel_and_the_buttons_stop_and_restart(): void
    {
        $this->fakeBot();
        $this->get(route('providers.show', $this->channel))->assertSee('https://t.me/PipScopesBot?start=watch_'.$this->channel->id, false);

        $this->message('/start watch_'.$this->channel->id)->assertOk();
        $this->assertTrue(ChannelWatch::where('chat_id', 777)->where('provider_id', $this->channel->id)->exists());
        $this->assertStringContainsString(__('ستصلك هنا تنبيهات قناة «:name»:', ['name' => 'Gold Pro']), $this->lastText());

        // Following twice keeps one row
        $this->message('/start watch_'.$this->channel->id);
        $this->assertSame(1, ChannelWatch::count());

        $this->press('w:off:'.$this->channel->id)->assertOk();
        $this->assertSame(0, ChannelWatch::count());

        $this->press('w:on:'.$this->channel->id);
        $this->assertSame(1, ChannelWatch::count());

        // A channel that isn't on the platform can't be followed
        $this->message('/start watch_99999');
        $this->assertSame(__('لم نجد هذه القناة على المنصة.'), $this->lastText());
    }

    public function test_channels_lists_what_the_chat_follows_with_a_stop_button_each(): void
    {
        $this->fakeBot();

        $this->message('/channels');
        $this->assertStringContainsString(__('لا تتابع أي قناة بعد.'), $this->lastText());

        $other = Provider::create(['name' => 'Silver Signals', 'service_type' => 'Signals', 'is_active' => true]);
        ChannelWatch::create(['chat_id' => 777, 'provider_id' => $this->channel->id]);
        ChannelWatch::create(['chat_id' => 777, 'provider_id' => $other->id]);
        ChannelWatch::create(['chat_id' => 888, 'provider_id' => $other->id]);

        $this->message('قنواتي');
        $sent = collect(Http::recorded())->map(fn ($p) => $p[0])->filter(fn (Request $r) => str_ends_with($r->url(), '/sendMessage'))->last();
        $this->assertStringContainsString('Gold Pro', $sent['text']);
        $this->assertStringContainsString('Silver Signals', $sent['text']);
        $this->assertStringContainsString('تتابع قناتين:', $sent['text'], 'two is said as a dual, not "2 قناة"');
        $rows = $sent['reply_markup']['inline_keyboard'];
        $this->assertCount(2, $rows, 'only this chat\'s channels');
        $this->assertSame('w:off:'.$other->id, $rows[0][1]['callback_data']);
        $this->assertSame(route('providers.show', $other), $rows[0][0]['url']);
    }

    public function test_the_channel_page_says_what_following_does(): void
    {
        $this->get(route('providers.show', $this->channel))->assertOk()
            ->assertSee('data-watch-link', false)
            ->assertSee(__('تابع القناة'))
            ->assertSee(__('تنبيه على تيليجرام إذا حذفت صفقات أو انكشف منتحل باسمها'));

        // Without the bot connected there's nothing to follow with
        Setting::put([TelegramAppLogin::SETTING => ['enabled' => false]]);
        $this->get(route('providers.show', $this->channel))->assertDontSee('data-watch-link', false);
    }

    public function test_deleted_posts_reviews_and_impersonators_alert_followers_only(): void
    {
        $this->fakeBot();
        Queue::fake();

        // Nobody follows yet: nothing is queued
        ChannelWatching::deleted($this->channel, 3, 0);
        Queue::assertNothingPushed();

        ChannelWatch::create(['chat_id' => 777, 'provider_id' => $this->channel->id]);

        // A trade seen, then gone from between its neighbours
        $items = fn (array $ids) => array_map(fn ($id) => ['msg_id' => $id, 'posted_at' => now()->subDay()->toIso8601String(), 'views' => 100, 'edited' => false,
            'text' => $id === 2 ? 'BUY GOLD NOW 2814 Tp 2820 Sl 2807' : 'صباح الخير', 'photo' => false], $ids);
        TelegramPosts::record($this->channel, $items([1, 2, 3]));
        Queue::assertNothingPushed();
        TelegramPosts::record($this->channel, $items([1, 3]));
        Queue::assertPushed(SendWatchAlert::class, fn ($job) => str_contains($job->text, 'قناة «Gold Pro» حذفت منشوراً واحداً.')
            && str_contains($job->text, __('من بينها صفقات كانت منشورة بأرقام واضحة: :n.', ['n' => 1])));

        Review::create(['provider_id' => $this->channel->id, 'user_id' => User::factory()->create()->id, 'rating' => 2, 'body' => 'x', 'status' => Review::STATUS_APPROVED]);
        $this->actingAs(User::factory()->create())->post(route('reviews.store', $this->channel), ['rating' => 1, 'body' => 'خسرت معهم كل شيء، لا أنصح بها.', 'show_name' => '0']);
        Queue::assertPushed(SendWatchAlert::class, fn ($job) => str_contains($job->text, '★☆☆☆☆'));

        Impersonator::create(['provider_id' => $this->channel->id, 'platform' => 'telegram', 'handle' => '@goldpro_vip', 'status' => 'pending']);
        Queue::assertNotPushed(SendWatchAlert::class, fn ($job) => str_contains($job->text, '@goldpro_vip'));
        Impersonator::first()->update(['status' => 'confirmed']);
        Queue::assertPushed(SendWatchAlert::class, fn ($job) => str_contains($job->text, '@goldpro_vip'));
    }

    public function test_only_a_real_jump_in_subscribers_is_worth_an_alert(): void
    {
        $this->fakeBot();
        Queue::fake();
        ChannelWatch::create(['chat_id' => 777, 'provider_id' => $this->channel->id]);

        ChannelWatching::subscribers($this->channel, 10000, 11000);   // +10%
        ChannelWatching::subscribers($this->channel, 2000, 3000);     // +50% but only 1 000 people
        Queue::assertNothingPushed();

        ChannelWatching::subscribers($this->channel, 10000, 15000);
        Queue::assertPushed(SendWatchAlert::class, 1);

        ChannelWatching::renamed($this->channel, 'Gold Pro', 'Gold Pro VIP');
        Queue::assertPushed(SendWatchAlert::class, fn ($job) => str_contains($job->text, 'Gold Pro VIP'));
    }

    public function test_the_alert_goes_to_every_follower_and_a_blocked_chat_is_dropped(): void
    {
        ChannelWatch::create(['chat_id' => 777, 'provider_id' => $this->channel->id]);
        ChannelWatch::create(['chat_id' => 888, 'provider_id' => $this->channel->id]);
        Http::fake(fn (Request $r) => $r['chat_id'] === 888
            ? Http::response(['ok' => false, 'description' => 'Forbidden: bot was blocked by the user'], 403)
            : Http::response(['ok' => true, 'result' => []]));

        (new SendWatchAlert($this->channel->id, 'تنبيه'))->handle();

        $this->assertSame([777], ChannelWatch::pluck('chat_id')->all());
        Http::assertSent(fn (Request $r) => $r['chat_id'] === 777 && $r['text'] === 'تنبيه'
            && $r['reply_markup']['inline_keyboard'][1][0]['callback_data'] === 'w:off:'.$this->channel->id);
    }
}
