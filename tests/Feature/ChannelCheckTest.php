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
            ->assertDontSee('<details', false)
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
