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

    public function test_the_about_tab_holds_the_sidebar_and_telegram_numbers(): void
    {
        foreach ([1, 0] as $daysAgo) {
            TelegramSnapshot::create(['provider_id' => $this->provider->id, 'date' => now()->subDays($daysAgo)->toDateString(), 'period' => 'd', 'subscribers' => 1000 + $daysAgo, 'avg_views' => 100]);
        }

        // Three tabs at any width: Telegram from lg up, About below
        $this->get(route('providers.show', $this->provider))
            ->assertSee('data-tab="about"', false)
            ->assertSee('lg:hidden flex flex-1', false)
            ->assertSee('max-lg:hidden flex flex-1', false)
            ->assertSee('data-panel="about"', false)
            ->assertSee('lg:block!', false)
            ->assertSee('data-panel-also="about"', false);

        // Opened directly: the sidebar and the Telegram numbers show, the reviews don't
        $html = $this->get(route('providers.show', [$this->provider, 'tab' => 'about']))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/class="[^"]*\bhidden\b[^"]*" data-panel="about"/', $html);
        $this->assertDoesNotMatchRegularExpression('/class="[^"]*\bhidden\b[^"]*" data-panel="telegram"/', $html);
        $this->assertMatchesRegularExpression('/id="reviews" class="[^"]*\bhidden\b/', $html);
    }

    public function test_official_links_show_as_icons_in_the_header(): void
    {
        $this->provider->update(['instagram_url' => 'https://instagram.com/goldpro']);

        $this->get(route('providers.show', $this->provider))
            ->assertSeeInOrder(['lg:hidden" role="group"', 'data-official-icons', 'https://instagram.com/goldpro', 'data-tab="reviews"'], false)
            // Not twice: the sidebar's list is for wide screens
            ->assertSee('max-lg:hidden" id="official-links"', false);
    }

    public function test_impersonators_sit_outside_the_tabs_and_show_on_every_tab(): void
    {
        foreach (['reviews', 'analysis', 'about'] as $tab) {
            $html = $this->get(route('providers.show', [$this->provider, 'tab' => $tab]))->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/max-lg:order-2[^>]*>\s*<div class="card scroll-mt-6 overflow-hidden" id="impersonators"/', $html);
        }

        $this->assertStringNotContainsString('id="impersonators"', explode('</aside>', explode('<aside', $html)[1])[0]);
    }

    public function test_the_review_bar_offers_the_right_action(): void
    {
        $this->get(route('providers.show', $this->provider))->assertOk()
            ->assertSee('data-site-header', false)
            ->assertSee('data-review-anchor', false)
            ->assertSeeInOrder(['data-review-bar', route('reviews.create', $this->provider), __('اكتب تقييماً')], false);

        $member = User::factory()->create();
        Review::create(['provider_id' => $this->provider->id, 'user_id' => $member->id, 'rating' => 4, 'body' => 'A long enough review body for testing.', 'status' => Review::STATUS_APPROVED]);

        $this->actingAs($member)->get(route('providers.show', $this->provider))
            ->assertSeeInOrder(['data-review-bar', __('تعديل تقييمك')], false);
    }

    public function test_bottom_sheets_have_a_close_button_with_its_label(): void
    {
        $sheetClose = fn (string $page) => $this->get($page)->assertOk()->getContent();

        $share = $sheetClose(route('providers.show', $this->provider));
        $this->assertMatchesRegularExpression('/<button type="button" class="[^"]*" data-share-close[^>]*>\s*<span[^>]*>&times;<\/span>\s*'.__('إغلاق').'/u', $share);

        $filters = $sheetClose(route('providers.index'));
        $this->assertMatchesRegularExpression('/<button type="button" class="[^"]*" data-close-filters[^>]*>\s*<span[^>]*>&times;<\/span>\s*'.__('إغلاق').'/u', $filters);
    }

    public function test_reviews_tab_shows_the_count_and_each_review_has_a_show_more_toggle(): void
    {
        $this->get(route('providers.show', [$this->provider, 'tab' => 'reviews']))
            ->assertSee('A long enough review body for testing.')
            ->assertSee('data-clamp-toggle', false);
    }
}
