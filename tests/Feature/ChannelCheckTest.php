<?php

namespace Tests\Feature;

use App\Models\Impersonator;
use App\Models\Provider;
use App\Models\ProviderSubmission;
use App\Support\ChannelReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** "Check a channel": a report on any public channel from one read, and adding it for monitoring. */
class ChannelCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** Posts 1..$count, a day apart going back from yesterday; $views per post; every $editEvery-th edited */
    protected function posts(int $count, callable $views, int $editEvery = 0): array
    {
        return array_map(fn ($i) => [
            'msg_id' => 100 + $i,
            'posted_at' => now()->subDays($count - $i + 1)->toIso8601String(),
            'views' => $views($i),
            'edited' => $editEvery && $i % $editEvery === 0,
        ], range(1, $count));
    }

    protected function fakeChannel(string $username, int $subscribers, array $posts, bool $preview = true): void
    {
        $page = $preview ? collect($posts)->map(fn ($p) => '<div class="tgme_widget_message" data-post="'.$username.'/'.$p['msg_id'].'">'
            .'<span class="tgme_widget_message_views">'.$p['views'].'</span><span class="tgme_widget_message_meta">'.($p['edited'] ? 'edited &nbsp;' : '')
            .'<a class="tgme_widget_message_date" href="#"><time datetime="'.$p['posted_at'].'"></time></a></span></div>')->join('') : '<div>no preview</div>';

        Http::fake([
            "t.me/s/$username?before=*" => Http::response(''),
            "t.me/s/$username" => Http::response($page),
            "t.me/$username" => Http::response('<meta property="og:title" content="'.$username.'"><meta property="og:description" content="Daily gold signals">'
                .'<div class="tgme_page_title"></div><div class="tgme_page_extra">'.number_format($subscribers, 0, '', ' ').' subscribers</div>'),
            '*' => Http::response(''),
        ]);
    }

    public function test_the_page_opens_empty_and_is_linked_from_the_header(): void
    {
        $this->get(route('channel-check'))->assertOk()
            ->assertSee(__('افحص قناة توصيات قبل أن تشترك'))
            ->assertSee('name="channel"', false);

        $this->get('/')->assertSee(route('channel-check'));
    }

    public function test_a_telegram_link_pasted_into_search_goes_to_the_check(): void
    {
        $this->get(route('providers.index', ['q' => 'https://t.me/goldpro']))
            ->assertRedirect(route('channel-check', ['channel' => 'https://t.me/goldpro']));
        $this->get(route('providers.index', ['q' => 't.me/goldpro']))
            ->assertRedirect(route('channel-check', ['channel' => 't.me/goldpro']));

        // Names and @handles are still searched
        $this->get(route('providers.index', ['q' => 'gold pro']))->assertOk();
        $this->get(route('providers.index', ['q' => '@goldpro']))->assertOk();
    }

    public function test_the_homepage_has_the_check_right_under_the_categories_and_admins_can_switch_it_off(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('data-home-channel-check', false)
            ->assertSee('action="'.route('channel-check').'"', false);

        $this->assertSame('channel_check', \App\Support\HomeSections::layout()[1]['key']);

        \App\Models\Setting::put(['home_layout' => [['key' => 'channel_check', 'enabled' => false]]]);
        $this->get('/')->assertDontSee('data-home-channel-check', false);
    }

    public function test_an_unlisted_channel_gets_the_instant_report_and_the_monitoring_offer(): void
    {
        // 20 000 subscribers, ~300 views a post: 1.5% engagement; 4 of 20 posts edited
        $this->fakeChannel('lowviews', 20000, $this->posts(20, fn ($i) => 280 + $i * 3, editEvery: 5));

        $this->get(route('channel-check', ['channel' => '@lowviews']))->assertOk()
            ->assertSee('data-channel-report="report"', false)
            ->assertSee('data-insight="engagement" data-level="bad"', false)
            ->assertSee('data-insight="activity" data-level="good"', false)
            ->assertSee('data-insight="edited" data-level="warn"', false)
            ->assertSee(__(':n من :total', ['n' => 4, 'total' => 20]))
            ->assertSee('data-report-monitoring', false)
            ->assertSee(__('أضف القناة للمراقبة'))
            ->assertSee('value="https://t.me/lowviews"', false)
            // The channel's own description could read as our finding, so it isn't shown
            ->assertDontSee('Daily gold signals')
            // The next step comes right after the channel, before the numbers (on phones it's not buried)
            ->assertSeeInOrder(['data-report-monitoring', 'data-report-cards'], false);

        // One tap sends it for review, back to the same report
        $this->from(route('channel-check', ['channel' => '@lowviews']))
            ->post(route('submissions.store'), ['telegram_url' => 'https://t.me/lowviews'])
            ->assertSessionHas('success');
        $this->assertSame(1, ProviderSubmission::count());

        $this->get(route('channel-check', ['channel' => 'https://t.me/lowviews']))
            ->assertSee('data-report-pending', false)
            ->assertDontSee(__('أضف القناة للمراقبة'));
    }

    public function test_results_without_trades_and_broker_links_are_reported(): void
    {
        $posts = $this->posts(12, fn ($i) => 1500 + $i * 40);
        $texts = ['150pips✔️ done', '360 PIPS RUNNING', 'مبروك الهدف الاول 150نقطه', 'سجل معي https://one.justmarkets.link/a/7zkl804aw7',
            'للانضمام إلى القناة الخاصة VIP', 'صباح الخير'];
        foreach ($posts as $i => &$post) {
            $post['text'] = $texts[$i % count($texts)];
            $post['photo'] = false;
        }

        $cards = ChannelReport::cards($posts, 10000);

        $this->assertSame('bad', $cards['verifiability']['level']);
        $this->assertSame(__('صفقات :s · ادعاءات :c', ['s' => 0, 'c' => 6]), $cards['verifiability']['value']);
        $this->assertSame(['warn', '33%'], [$cards['promotion']['level'], $cards['promotion']['value']]);
        $this->assertStringContainsString('JustMarkets', $cards['promotion']['note']);
    }

    public function test_a_report_can_be_shared_with_its_own_preview_image(): void
    {
        $this->fakeChannel('lowviews', 20000, $this->posts(20, fn ($i) => 280 + $i * 3));
        $image = route('share-images.check', ['ar', 'lowviews']);

        // Whatever was pasted, the shared link is the channel's one, and the preview is the result
        $this->get(route('channel-check', ['channel' => 'https://t.me/lowviews']))
            ->assertSee('data-report-share', false)
            ->assertSee(rawurlencode(route('channel-check', ['channel' => '@lowviews'])), false)
            ->assertSee('<meta property="og:image" content="'.$image.'">', false);

        $response = $this->get($image)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame([1200, 630], array_slice(getimagesize($response->baseResponse->getFile()->getPathname()), 0, 2));

        $this->get(route('share-images.check', ['ar', 'nosuchchannel']))->assertNotFound();
    }

    public function test_wins_without_any_loss_and_a_young_big_channel_are_pointed_out(): void
    {
        $posts = $this->posts(10, fn ($i) => 2000);
        foreach ($posts as $i => &$post) {
            $post['text'] = $i < 6 ? '150pips✔️ done' : 'صباح الخير';
            $post['photo'] = false;
        }
        unset($post);

        $cards = ChannelReport::cards($posts, 60000, now()->subMonths(3)->toIso8601String());
        $this->assertSame(['warn', __('أرباح :w · خسائر :l', ['w' => 6, 'l' => 0])], [$cards['losses']['level'], $cards['losses']['value']]);
        $this->assertSame('warn', $cards['age']['level']);
        $this->assertStringContainsString('60,000', $cards['age']['note']);

        // Owning up to a loss, and an older channel
        $posts[7]['text'] = 'للأسف ضرب الستوب';
        $cards = ChannelReport::cards($posts, 60000, now()->subYears(2)->toIso8601String());
        $this->assertSame('good', $cards['losses']['level']);
        $this->assertSame(['good', __('قناة قديمة نسبياً')], [$cards['age']['level'], $cards['age']['status']]);
    }

    public function test_reactions_are_read_from_the_page_and_rare_ones_are_pointed_out(): void
    {
        $html = '<div class="tgme_widget_message" data-post="gold/5"><div class="tgme_widget_message_reactions js-message_reactions">'
            .'<span class="tgme_reaction"><i class="emoji"><b>❤</b></i>26</span><span class="tgme_reaction"><i class="emoji"><b>👍</b></i>1.2K</span></div>'
            .'<span class="tgme_widget_message_views">9K</span></div><div class="tgme_widget_message" data-post="gold/6"><span class="tgme_widget_message_views">80</span></div>';
        $this->assertSame([1226, null], array_column(\App\Services\TelegramChannel::postItems($html, 'gold'), 'reactions'));

        // 12 posts of 3 000 views: 2 reactions each is under 1 per 1 000 views
        $posts = $this->posts(12, fn () => 3000);
        $rare = array_map(fn ($p) => $p + ['reactions' => 2], $posts);
        $this->assertSame('bad', ChannelReport::cards($rare, 10000)['reactions']['level']);

        $normal = array_map(fn ($p) => $p + ['reactions' => 40], $posts);
        $this->assertSame(['good', __(':n لكل 1000 مشاهدة', ['n' => 13])], [ChannelReport::cards($normal, 10000)['reactions']['level'], ChannelReport::cards($normal, 10000)['reactions']['value']]);

        // No reactions anywhere: they may be switched off, so nothing is said
        $this->assertArrayNotHasKey('reactions', ChannelReport::cards($posts, 10000));
    }

    public function test_comments_open_or_closed_come_from_the_bot_api_even_with_the_preview_off(): void
    {
        config(['services.telegram.bot_token' => '123456:TEST']);
        Http::fake([
            'api.telegram.org/*' => Http::sequence()
                ->push(['ok' => true, 'result' => ['id' => -100, 'type' => 'channel']])
                ->push(['ok' => true, 'result' => ['id' => -101, 'type' => 'channel', 'linked_chat_id' => -200]]),
            't.me/s/closed*' => Http::response('<div>no preview</div>'),
            't.me/closed' => Http::response('<meta property="og:title" content="closed"><div class="tgme_page_title"></div><div class="tgme_page_extra">50 000 subscribers</div>'),
            't.me/s/open*' => Http::response('<div>no preview</div>'),
            't.me/open' => Http::response('<meta property="og:title" content="open"><div class="tgme_page_title"></div><div class="tgme_page_extra">9 000 subscribers</div>'),
            '*' => Http::response(''),
        ]);

        $this->get(route('channel-check', ['channel' => '@closed']))
            ->assertSee('data-report-no-preview', false)
            ->assertSee('data-insight="comments" data-level="warn"', false)
            ->assertSee(__('لا يمكن التعليق على المنشورات'));

        $this->get(route('channel-check', ['channel' => '@open']))
            ->assertSee('data-insight="comments" data-level="good"', false);

        // Without a bot there's no way to tell, and nothing is shown
        $this->assertNull(\App\Support\TelegramInsights::commentsCard(null));
    }

    public function test_near_identical_views_are_pointed_out_and_normal_ones_are_not(): void
    {
        $steady = ChannelReport::cards($this->posts(15, fn ($i) => 4000 + ($i % 3) * 20), 10000);
        $this->assertSame('warn', $steady['steady_views']['level']);

        $normal = ChannelReport::cards($this->posts(15, fn ($i) => [900, 2400, 1500, 3100, 700][$i % 5]), 10000);
        $this->assertArrayNotHasKey('steady_views', $normal);
        $this->assertSame('good', $normal['engagement']['level']);
    }

    public function test_a_channel_with_the_preview_off_gets_the_basics_and_a_plain_note(): void
    {
        $this->fakeChannel('closed', 50000, [], preview: false);

        $this->get(route('channel-check', ['channel' => 't.me/closed']))->assertOk()
            ->assertSee('data-report-no-preview', false)
            ->assertDontSee('data-report-cards', false)
            ->assertSee('50,000');
    }

    public function test_a_listed_channel_links_to_its_page_and_offers_no_monitoring(): void
    {
        $this->fakeChannel('goldpro', 9000, $this->posts(12, fn ($i) => 1500 + $i * 50));
        $provider = Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/goldpro', 'is_active' => true]);

        $this->get(route('channel-check', ['channel' => '@goldpro']))->assertOk()
            ->assertSee('data-report-listed', false)
            ->assertSee(route('providers.show', $provider))
            ->assertDontSee('data-report-monitoring', false);

        // Followed for 5 days: the deletions are 9 days away, and the page says when to come back
        $provider->forceFill(['telegram_posts_since' => now()->subDays(5), 'telegram_data' => ['preview_enabled' => true]])->saveQuietly();
        $readyAt = now()->subDays(5)->addDays(14)->startOfDay();

        $this->get(route('channel-check', ['channel' => '@goldpro']))
            ->assertSee('data-insight="deleted" data-level="pending"', false)
            ->assertSee('data-report-recheck', false)
            ->assertSee($readyAt->translatedFormat('l j F'));

        // Once the two weeks are up, the count shows instead
        $provider->forceFill(['telegram_posts_since' => now()->subDays(20)])->saveQuietly();
        $this->get(route('channel-check', ['channel' => '@goldpro']))
            ->assertSee('data-insight="deleted" data-level="good"', false)
            ->assertDontSee('data-report-recheck', false);
    }

    public function test_every_indicator_explains_itself_without_a_tap(): void
    {
        $this->fakeChannel('lowviews', 20000, $this->posts(20, fn ($i) => 280 + $i * 3));

        $this->get(route('channel-check', ['channel' => '@lowviews']))
            // Rows laid out in full, not cards that open on a tap
            ->assertSee('<li class="flex gap-3 p-4" data-insight="engagement"', false)
            ->assertDontSee('<details class="group rounded-xl', false)
            ->assertSee(__('تفاعل منخفض جداً مقارنة بعدد المشتركين'))
            ->assertSee('من كل 100 مشترك يشاهد المنشورَ');
    }

    public function test_known_impersonators_get_a_warning_instead(): void
    {
        $provider = Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/goldpro', 'is_active' => true]);
        Impersonator::create(['provider_id' => $provider->id, 'platform' => 'telegram', 'handle' => '@goldpro_vip', 'status' => 'confirmed']);
        Http::fake();

        $this->get(route('channel-check', ['channel' => 'https://t.me/goldpro_vip']))->assertOk()
            ->assertSee('data-channel-report="impersonator"', false)
            ->assertSee('Gold Pro');
        Http::assertNothingSent();
    }

    public function test_wrong_links_get_a_clear_message(): void
    {
        Http::fake();

        $this->get(route('channel-check', ['channel' => 'https://example.com/x']))->assertOk()
            ->assertSee('data-channel-report="invalid"', false);
        $this->get(route('channel-check', ['channel' => 'https://t.me/+AbCdEf']))->assertOk()
            ->assertSee('data-channel-report="private"', false);
    }

    public function test_approving_a_channel_starts_following_its_posts_right_away(): void
    {
        $this->fakeChannel('newone', 8000, $this->posts(12, fn ($i) => 1200));
        $this->post(route('submissions.store'), ['telegram_url' => 'https://t.me/newone']);
        $submission = ProviderSubmission::sole();

        $this->actingAs(\App\Models\User::factory()->create(['is_admin' => true]));
        $category = \App\Models\Category::create(['name' => 'Signals', 'slug' => 'signals']);
        \Livewire\Livewire::test(\App\Filament\Resources\ProviderSubmissionResource\Pages\ListProviderSubmissions::class)
            ->callTableAction('convert', $submission, ['category_ids' => [$category->id], 'service_type' => 'Signals'])
            ->assertHasNoTableActionErrors();

        $provider = Provider::sole();
        $this->assertNotNull($provider->telegram_posts_since);
        $this->assertSame(12, $provider->telegramPosts()->count());
    }
}
