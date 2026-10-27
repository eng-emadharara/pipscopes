<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Market;
use App\Models\Provider;
use App\Models\Setting;
use App\Models\User;
use App\Support\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FakesTelegram;
use Tests\TestCase;

/** Google Analytics events (App\Support\Analytics), handed to the page in window.__analytics. */
class AnalyticsEventsTest extends TestCase
{
    use FakesTelegram, RefreshDatabase;

    protected Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put([Site::KEY => ['ga_id' => 'G-TEST123']]);

        $category = Category::create(['name' => 'Signals', 'slug' => 'signals']);
        $this->provider = Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/gold_pro', 'is_active' => true]);
        $this->provider->categories()->attach($category->id);
        $this->provider->markets()->attach(Market::where('slug', 'gold')->value('id'));
    }

    /** @return array{events: array, context: array} the page's window.__analytics */
    protected function analytics(\Illuminate\Testing\TestResponse $response): array
    {
        preg_match('/window\.__analytics = (\{.*?\});<\/script>/s', $response->getContent(), $m);
        $this->assertNotEmpty($m, 'the page carries analytics data');

        return json_decode($m[1], true);
    }

    protected function eventNames(\Illuminate\Testing\TestResponse $response): array
    {
        return array_column($this->analytics($response)['events'], 'name');
    }

    public function test_nothing_is_sent_without_an_analytics_id(): void
    {
        Setting::put([Site::KEY => []]);

        $this->get(route('providers.show', $this->provider))->assertDontSee('window.__analytics', false);
    }

    public function test_channel_page_carries_the_channel_properties(): void
    {
        $data = $this->analytics($this->get(route('providers.show', $this->provider)));

        $this->assertSame(['view_channel'], array_column($data['events'], 'name'));
        $this->assertSame('Gold Pro', $data['context']['channel_name']);
        $this->assertSame('signals', $data['context']['channel_category']);
        $this->assertSame('gold', $data['context']['channel_market']);
        $this->assertSame('none', $data['context']['channel_rating']);

        // Tabs send tab_view when clicked
        $this->get(route('providers.show', $this->provider))->assertSee('data-track="tab_view"', false);

        // …and the properties don't leak onto the next page
        $this->assertSame([], $this->analytics($this->get('/'))['context']);
    }

    public function test_search_reports_the_term_and_how_many_results(): void
    {
        $events = $this->analytics($this->get(route('providers.index', ['q' => 'no such channel'])))['events'];

        $this->assertSame([['name' => 'search', 'params' => ['search_term' => 'no such channel', 'results_count' => 0]]], $events);
    }

    public function test_account_check_result_is_sent_with_the_ajax_answer(): void
    {
        $response = $this->get(route('account-check', ['account' => '@gold_pro']), ['X-Requested-With' => 'XMLHttpRequest']);

        $this->assertSame(
            ['name' => 'account_check', 'params' => ['result' => 'official', 'platform' => 'any']],
            json_decode($response->headers->get('X-Analytics-Event'), true),
        );
    }

    public function test_review_start_and_submit(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertSame(['review_start'], $this->eventNames($this->get(route('reviews.create', $this->provider))));

        $this->post(route('reviews.store', $this->provider), [
            'rating' => 4, 'title' => 'Good signals', 'body' => 'A long enough review body for testing.', 'show_name' => '0',
        ])->assertRedirect();

        $events = $this->analytics($this->get(route('providers.show', $this->provider)))['events'];
        $submit = collect($events)->firstWhere('name', 'review_submit');
        $this->assertSame(4, $submit['params']['rating']);
        $this->assertSame('yes', $submit['params']['anonymous']);
        $this->assertSame('new', $submit['params']['review_type']);
        $this->assertSame('Gold Pro', $submit['params']['channel_name']);
    }

    public function test_channel_submit_is_reported_after_the_redirect(): void
    {
        Storage::fake('public');
        $this->fakeTelegram(['new_channel' => ['title' => 'New Channel']]);

        $this->from(route('submissions.create'))->post(route('submissions.store'), [
            'telegram_url' => '@new_channel',
            'category_ids' => [Category::value('id')],
            'market_ids' => [Market::where('slug', 'gold')->value('id')],
        ])->assertSessionHasNoErrors();

        $events = $this->analytics($this->get(route('submissions.create')))['events'];
        $this->assertSame('channel_submit', $events[0]['name']);
        $this->assertSame(['channel_name' => 'New Channel', 'channel_category' => 'signals', 'channel_subscribers' => 12345], $events[0]['params']);
    }

    public function test_telegram_buttons_are_tracked(): void
    {
        Setting::put([Site::KEY => ['ga_id' => 'G-TEST123', 'socials' => ['telegram' => 'https://t.me/pipscopes']]]);

        $this->get('/')
            ->assertSee('data-track="telegram_join" data-track-params=\'{"location":"home_section"}\'', false)
            ->assertSee('data-track="telegram_join" data-track-params=\'{"location":"footer"}\'', false);
    }
}
