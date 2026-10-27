<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Market;
use App\Models\Provider;
use App\Models\ProviderSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FakesTelegram;
use Tests\TestCase;

class MarketsAndPaginationTest extends TestCase
{
    use FakesTelegram, RefreshDatabase;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::create(['name' => 'Signals', 'slug' => 'signals']);
    }

    /** @param  list<string>  $marketSlugs */
    protected function provider(string $name, array $marketSlugs = []): Provider
    {
        $provider = Provider::create(['name' => $name, 'service_type' => 'Signals', 'is_active' => true]);
        $provider->categories()->attach($this->category->id);
        $provider->markets()->attach(Market::whereIn('slug', $marketSlugs)->pluck('id'));
        $provider->refreshSearchIndex();

        return $provider;
    }

    public function test_default_markets_exist(): void
    {
        $this->assertSame(
            ['forex', 'gold', 'crypto', 'stocks', 'indices', 'commodities'],
            Market::orderBy('sort_order')->pluck('slug')->all(),
        );
    }

    public function test_filter_by_markets_matches_any_selected_market(): void
    {
        $this->provider('Gold Only', ['gold']);
        $this->provider('Crypto Only', ['crypto']);
        $this->provider('Forex Only', ['forex']);

        $this->get(route('providers.index', ['markets' => ['gold', 'crypto']]))
            ->assertSee('Gold Only')
            ->assertSee('Crypto Only')
            ->assertDontSee('Forex Only');
    }

    public function test_search_finds_providers_by_market_name_in_both_languages(): void
    {
        $this->provider('Gold Only', ['gold']);
        $this->provider('Forex Only', ['forex']);

        $this->assertSame(['Gold Only'], Provider::search('ذهب')->pluck('name')->all());
        $this->assertSame(['Gold Only'], Provider::search('Gold')->pluck('name')->all());
    }

    public function test_submission_requires_markets_and_carries_them_to_the_provider(): void
    {
        Storage::fake('public');
        $this->fakeTelegram(['new_channel' => []]);

        $payload = [
            'submitter_name' => 'Tester', 'submitter_contact' => '@tester', 'relation' => 'owner',
            'category_ids' => [$this->category->id], 'name' => 'New Channel', 'service_type' => 'Gold signals',
            'telegram_url' => 'https://t.me/new_channel',
        ];

        $this->post(route('submissions.store'), $payload)->assertSessionHasErrors('market_ids');

        $gold = Market::where('slug', 'gold')->value('id');
        $oil = Market::where('slug', 'commodities')->value('id');
        $this->post(route('submissions.store'), $payload + ['market_ids' => [$gold, $oil]])->assertSessionHasNoErrors();

        $provider = ProviderSubmission::first()->convertToProvider();
        $this->assertEqualsCanonicalizing(['gold', 'commodities'], $provider->markets->pluck('slug')->all());
    }

    public function test_browse_page_paginates_with_selectable_page_size(): void
    {
        foreach (range(1, 15) as $n) {
            $this->provider(sprintf('Channel %02d', $n));
        }

        $this->get(route('providers.index'))
            ->assertOk()
            ->assertSee('rel="next"', false)
            ->assertSee(__('عرض :from–:to من :total', ['from' => 1, 'to' => 12, 'total' => 15]));

        $this->get(route('providers.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('rel="prev"', false);

        // 24 per page fits everything on one page
        $this->get(route('providers.index', ['per_page' => 24]))
            ->assertOk()
            ->assertDontSee('rel="next"', false);

        // Unsupported sizes fall back to the default
        $this->get(route('providers.index', ['per_page' => 1000]))
            ->assertSee('rel="next"', false);
    }

    public function test_page_past_the_end_redirects_to_the_last_page(): void
    {
        foreach (range(1, 15) as $n) {
            $this->provider("Channel $n");
        }

        $this->get(route('providers.index', ['page' => 9]))
            ->assertRedirect(route('providers.index', ['page' => 2]));
    }
}
