<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\Review;
use App\Models\TelegramSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The channel page's tabs: rating analysis, Telegram performance, reviews. */
class ProviderTabsTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = Provider::create(['name' => 'Gold', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/gold', 'is_active' => true]);
        Review::create([
            'provider_id' => $this->provider->id, 'user_id' => User::factory()->create()->id, 'rating' => 4,
            'title' => 'Solid signals', 'body' => 'A long enough review body for testing.', 'status' => Review::STATUS_APPROVED,
        ]);
    }

    /** The panel that is shown (the others carry the "hidden" class) */
    protected function activePanel(array $query = []): string
    {
        $html = $this->get(route('providers.show', [$this->provider, ...$query]))->assertOk()->getContent();
        preg_match_all('/data-panel="(\w+)"/', $html, $all);
        preg_match_all('/class="[^"]*\bhidden\b[^"]*" data-panel="(\w+)"|data-panel="(\w+)"[^>]*class="[^"]*\bhidden\b/', $html, $hidden);
        $shown = array_values(array_diff($all[1], array_filter([...$hidden[1], ...$hidden[2]])));

        $this->assertCount(1, $shown, 'exactly one panel is visible');

        return $shown[0];
    }

    public function test_reviews_open_first_and_links_pick_their_tab(): void
    {
        $this->assertSame('reviews', $this->activePanel());
        $this->assertSame('analysis', $this->activePanel(['tab' => 'analysis']));
        // Filtering or sorting reviews stays on the reviews tab
        $this->assertSame('reviews', $this->activePanel(['stars' => 4]));
        $this->assertSame('reviews', $this->activePanel(['tab' => 'nonsense']));
    }

    public function test_telegram_tab_appears_only_with_data(): void
    {
        $this->get(route('providers.show', $this->provider))->assertDontSee('data-tab="telegram"', false);
        $this->assertSame('reviews', $this->activePanel(['tab' => 'telegram']));

        foreach ([1, 0] as $daysAgo) {
            TelegramSnapshot::create(['provider_id' => $this->provider->id, 'date' => now()->subDays($daysAgo)->toDateString(), 'period' => 'd', 'subscribers' => 1000 + $daysAgo, 'avg_views' => 100]);
        }

        $this->get(route('providers.show', $this->provider))->assertSee('data-tab="telegram"', false);
        $this->assertSame('telegram', $this->activePanel(['tab' => 'telegram']));
    }

    public function test_reviews_tab_shows_the_count_and_each_review_has_a_show_more_toggle(): void
    {
        $this->get(route('providers.show', [$this->provider, 'tab' => 'reviews']))
            ->assertSee('A long enough review body for testing.')
            ->assertSee('data-clamp-toggle', false);
    }
}
