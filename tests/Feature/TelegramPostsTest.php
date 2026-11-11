<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\TelegramPost;
use App\Services\TelegramChannel;
use App\Services\TelegramPosts;
use App\Services\TelegramRefresh;
use App\Support\TelegramInsights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Following a channel's posts: deletions, edits, the daily summary, and the indicators on its page. */
class TelegramPostsTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $channel;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->channel = Provider::create(['name' => 'Gold', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/gold', 'is_active' => true,
            'telegram_members' => 10000, 'telegram_data' => ['preview_enabled' => true]]);
    }

    /** A preview page shaped like the real one: each post's number, views, date, and "edited" when it was */
    protected function page(array $posts, string $username = 'gold'): string
    {
        return collect($posts)->map(fn ($p) => '<div class="tgme_widget_message_wrap"><div class="tgme_widget_message" data-post="'.$username.'/'.$p['id'].'">'
            .'<span class="tgme_widget_message_views">'.($p['views'] ?? '1.2K').'</span><span class="copyonly"> views</span>'
            .'<span class="tgme_widget_message_meta">'.(($p['edited'] ?? false) ? 'edited &nbsp;' : '')
            .'<a class="tgme_widget_message_date" href="https://t.me/'.$username.'/'.$p['id'].'"><time datetime="'.($p['at'] ?? now()->subHours(30)->toIso8601String()).'" class="time">10:00</time></a></span></div></div>')
            ->join('');
    }

    /** @param  list<int>  $ids */
    protected function items(array $ids, array $extra = []): array
    {
        return array_map(fn ($id) => ($extra[$id] ?? []) + ['msg_id' => $id, 'posted_at' => now()->subHours(30)->toIso8601String(), 'views' => 1000, 'edited' => false], $ids);
    }

    public function test_the_page_is_read_post_by_post(): void
    {
        $items = TelegramChannel::postItems($this->page([
            ['id' => 120, 'views' => '6.14M', 'edited' => true],
            ['id' => 121, 'views' => '850'],
        ]), 'gold');

        $this->assertSame([120, 121], array_column($items, 'msg_id'));
        $this->assertSame([6140000, 850], array_column($items, 'views'));
        $this->assertSame([true, false], array_column($items, 'edited'));
        $this->assertNotNull($items[0]['posted_at']);
    }

    public function test_a_post_that_disappears_between_its_neighbours_counts_as_deleted(): void
    {
        TelegramPosts::record($this->channel, $this->items([100, 101, 102, 103, 104]));
        $this->assertNotNull($this->channel->fresh()->telegram_posts_since);

        // 102 is gone; 100 and 101 slid off the page (older), which is not a deletion
        TelegramPosts::record($this->channel, $this->items([103, 104, 105], [104 => ['edited' => true]]));
        $this->assertSame([], TelegramPost::whereNotNull('deleted_at')->pluck('msg_id')->all(), 'nothing in 103..105 is missing');

        TelegramPosts::record($this->channel, $this->items([101, 103, 104, 105]));
        $this->assertSame([102], TelegramPost::whereNotNull('deleted_at')->pluck('msg_id')->all());

        // Edited stays edited even if a later read doesn't show the mark
        $this->assertTrue(TelegramPost::where('msg_id', 104)->value('edited'));

        // One that shows up again wasn't deleted after all
        TelegramPosts::record($this->channel, $this->items([101, 102, 103]));
        $this->assertSame(0, TelegramPost::whereNotNull('deleted_at')->count());
    }

    public function test_a_refresh_follows_the_posts_on_two_pages_without_storing_them_in_the_channel_data(): void
    {
        Http::fake([
            't.me/s/gold?before=*' => Http::response($this->page([['id' => 90], ['id' => 91]])),
            't.me/s/gold' => Http::response($this->page([['id' => 92], ['id' => 94, 'edited' => true]])),
            't.me/gold' => Http::response('<meta property="og:title" content="Gold"><div class="tgme_page_title"></div><div class="tgme_page_extra">10 000 subscribers</div>'),
            '*' => Http::response(''),
        ]);

        $this->assertSame('channel', TelegramRefresh::refresh($this->channel));

        $this->assertSame([90, 91, 92, 94], TelegramPost::orderBy('msg_id')->pluck('msg_id')->all());
        $this->assertTrue(TelegramPost::where('msg_id', 94)->value('edited'));
        $this->assertArrayNotHasKey('items', $this->channel->fresh()->telegram_data);
    }

    public function test_channels_posting_lately_are_read_every_four_hours(): void
    {
        $quiet = Provider::create(['name' => 'Quiet', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/quiet', 'is_active' => true]);
        foreach ([$this->channel, $quiet] as $provider) {
            $provider->forceFill(['telegram_checked_at' => now()->subHours(5), 'telegram_failures' => 0])->saveQuietly();
        }
        TelegramPosts::record($this->channel, $this->items([1], [1 => ['posted_at' => now()->subDay()->toIso8601String()]]));
        TelegramPosts::record($quiet, $this->items([1], [1 => ['posted_at' => now()->subDays(10)->toIso8601String()]]));

        $this->assertSame([$this->channel->id], TelegramRefresh::due()->pluck('id')->all());
    }

    public function test_each_day_is_summarized_and_posts_past_45_days_go(): void
    {
        $yesterday = now()->subDay()->setTime(12, 0);
        TelegramPost::insert([
            ['provider_id' => $this->channel->id, 'msg_id' => 1, 'posted_at' => $yesterday, 'views' => 10, 'edited' => true, 'first_seen_at' => $yesterday, 'deleted_at' => null],
            ['provider_id' => $this->channel->id, 'msg_id' => 2, 'posted_at' => $yesterday, 'views' => 10, 'edited' => false, 'first_seen_at' => $yesterday, 'deleted_at' => $yesterday],
            ['provider_id' => $this->channel->id, 'msg_id' => 3, 'posted_at' => now()->subDays(50), 'views' => 10, 'edited' => false, 'first_seen_at' => now()->subDays(50), 'deleted_at' => null],
        ]);

        $this->artisan('telegram:posts-daily')->assertSuccessful();

        $day = DB::table('telegram_post_days')->sole();
        $this->assertSame([2, 1, 1], [(int) $day->posts, (int) $day->deleted, (int) $day->edited]);
        $this->assertSame([1, 2], TelegramPost::orderBy('msg_id')->pluck('msg_id')->all());
    }

    protected function follow(int $days, int $posts = 12, int $views = 1500, int $deleted = 0): void
    {
        $this->channel->forceFill(['telegram_posts_since' => now()->subDays($days),
            'telegram_data' => ['preview_enabled' => true, 'last_post_at' => now()->subHours(2)->toIso8601String()]])->saveQuietly();

        foreach (range(1, $posts) as $i) {
            TelegramPost::create(['provider_id' => $this->channel->id, 'msg_id' => $i, 'posted_at' => now()->subDays(2)->subHours($i),
                'views' => $views, 'first_seen_at' => now()->subDays(2), 'deleted_at' => $i <= $deleted ? now()->subDay() : null]);
        }
    }

    public function test_the_indicators_read_the_numbers_plainly(): void
    {
        $this->follow(days: 20, posts: 30, views: 1500, deleted: 12);
        $cards = TelegramInsights::for($this->channel->fresh());

        $this->assertSame(['engagement', 'deleted', 'activity', 'promotion'], array_keys($cards), 'no trades or claims among these posts, so no verifiability');
        $this->assertSame(['good', '15%'], [$cards['engagement']['level'], $cards['engagement']['value']]);
        $this->assertSame(['bad', '12'], [$cards['deleted']['level'], $cards['deleted']['value']]);
        $this->assertSame('good', $cards['activity']['level']);
        $this->assertStringContainsString('11', $cards['activity']['note'], '30 posts over the 20 days followed ≈ 11 a week');
    }

    public function test_deletions_wait_two_weeks_and_thin_data_shows_nothing(): void
    {
        $this->follow(days: 3, posts: 4, views: 100);
        $cards = TelegramInsights::for($this->channel->fresh());

        $this->assertSame('pending', $cards['deleted']['level']);
        $this->assertArrayNotHasKey('engagement', $cards, 'fewer than 10 posts');

        // Preview turned off by the owner: no cards rather than zeros
        $this->channel->forceFill(['telegram_data' => ['preview_enabled' => false]])->saveQuietly();
        $this->assertSame([], TelegramInsights::for($this->channel->fresh()));
    }

    public function test_low_engagement_is_flagged_and_admins_can_hide_an_indicator(): void
    {
        $this->follow(days: 20, posts: 12, views: 150);
        $this->assertSame(['bad', '1.5%'], array_values(array_intersect_key(TelegramInsights::for($this->channel->fresh())['engagement'], array_flip(['level', 'value']))));

        $this->channel->update(['telegram_hidden_insights' => ['engagement', 'deleted', 'promotion']]);
        $this->assertSame(['activity'], array_keys(TelegramInsights::for($this->channel->fresh())));
    }

    public function test_the_admin_ticks_the_indicators_to_show_all_ticked_by_default(): void
    {
        $this->actingAs(\App\Models\User::factory()->create(['is_admin' => true]));
        $this->channel->categories()->attach(\App\Models\Category::create(['name' => 'Signals', 'slug' => 'signals'])->id);
        $page = fn () => \Livewire\Livewire::test(\App\Filament\Resources\ProviderResource\Pages\EditProvider::class, ['record' => $this->channel->getRouteKey()]);

        $page()->assertFormSet(['telegram_hidden_insights' => TelegramInsights::KEYS])
            ->fillForm(['telegram_hidden_insights' => ['activity']])
            ->call('save')->assertHasNoFormErrors();

        // Unticked = hidden
        $this->assertSame(array_values(array_diff(TelegramInsights::KEYS, ['activity'])), $this->channel->fresh()->telegram_hidden_insights);
        $page()->assertFormSet(['telegram_hidden_insights' => ['activity']])
            ->fillForm(['telegram_hidden_insights' => TelegramInsights::KEYS])
            ->call('save')->assertHasNoFormErrors();

        $this->assertNull($this->channel->fresh()->telegram_hidden_insights);
    }

    public function test_followed_posts_are_sorted_and_a_deleted_trade_is_named(): void
    {
        $texts = [
            1 => 'BUY GOLD NOW 2814 Tp 2820 Tp 2830 Sl 2807',
            2 => '150pips✔️ done',
            3 => '360 PIPS RUNNING',
            4 => 'سجل معي https://one.justmarkets.link/a/7zkl804aw7',
            5 => 'صباح الخير',
        ];
        $items = fn (array $ids) => array_map(fn ($id) => ['msg_id' => $id, 'posted_at' => now()->subDays(2)->toIso8601String(), 'views' => 1000,
            'edited' => false, 'text' => $texts[$id], 'photo' => false], $ids);

        TelegramPosts::record($this->channel, $items([1, 2, 3, 4, 5]));
        $this->assertSame(['signal', 'claim', 'claim', 'ad', 'other'], TelegramPost::orderBy('msg_id')->pluck('kind')->all());
        $this->assertSame('JustMarkets', TelegramPost::where('msg_id', 4)->value('brokers'));

        // The trade disappears
        TelegramPost::where('msg_id', 1)->update(['deleted_at' => now()]);
        $this->channel->forceFill(['telegram_posts_since' => now()->subDays(20), 'telegram_data' => ['preview_enabled' => true]])->saveQuietly();

        $cards = TelegramInsights::for($this->channel->fresh());
        $this->assertStringContainsString(__('من بينها صفقات كانت منشورة بأرقام واضحة: :n.', ['n' => 1]), $cards['deleted']['note']);
        // A trade given and then deleted was still given
        $this->assertSame('warn', $cards['verifiability']['level']);
        $this->assertSame(__('صفقات :s · ادعاءات :c', ['s' => 1, 'c' => 2]), $cards['verifiability']['value']);
    }

    public function test_the_cards_show_on_the_channel_page(): void
    {
        $this->follow(days: 20, posts: 12, views: 1500, deleted: 3);
        foreach (range(0, 2) as $i) {
            \App\Models\TelegramSnapshot::create(['provider_id' => $this->channel->id, 'date' => now()->subDays($i)->toDateString(), 'period' => 'd', 'subscribers' => 10000, 'avg_views' => 1500]);
        }
        $this->channel->forceFill(['telegram_checked_at' => now()])->saveQuietly();

        $this->get(route('providers.show', $this->channel))->assertOk()
            ->assertSee('data-telegram-insights', false)
            ->assertSee('data-insight="deleted" data-level="warn"', false)
            ->assertSee(__('منشور محذوف (30 يوماً)'))
            ->assertSee(__('محسوبة تلقائياً من صفحة القناة العامة على تيليجرام، وتتحدّث يومياً.'));
    }
}
