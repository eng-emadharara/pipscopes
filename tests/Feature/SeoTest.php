<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Provider;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use App\Support\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** sitemap.xml, robots.txt and the schema.org data behind Google's star results. */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $category = Category::create(['name' => 'Signals', 'slug' => 'signals']);
        $this->provider = Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/gold_pro', 'is_active' => true]);
        $this->provider->categories()->attach($category->id);
        Provider::create(['name' => 'Hidden', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/hidden', 'is_active' => false]);
    }

    /** @return list<array<string, mixed>> the page's JSON-LD blocks */
    protected function jsonLd(string $url): array
    {
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $this->get($url)->getContent(), $m);

        return array_map(fn ($json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $m[1]);
    }

    public function test_sitemap_lists_public_pages_with_language_versions(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();

        $this->assertStringStartsWith('<?xml', $xml);
        $sitemap = simplexml_load_string($xml);
        $locs = [];
        foreach ($sitemap->url as $url) {
            $locs[] = (string) $url->loc;
        }

        $this->assertContains(route('providers.show', $this->provider), $locs);
        $this->assertContains(route('categories.show', 'signals'), $locs);
        $this->assertNotContains(route('providers.show', Provider::where('is_active', false)->first()), $locs);
        $this->assertStringContainsString('hreflang="en" href="'.route('providers.show', $this->provider).'?lang=en"', $xml);
    }

    public function test_robots_points_to_the_sitemap_and_keeps_private_pages_out(): void
    {
        $this->get('/robots.txt')->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee('Sitemap: '.route('seo.sitemap'));

        // A test copy of the site (noindex in the site settings) is closed entirely
        Setting::put([Site::KEY => ['noindex' => true]]);
        $this->get('/robots.txt')->assertSee("Disallow: /\n", false)->assertDontSee('Sitemap:');
    }

    public function test_channel_page_describes_its_rating_and_reviews(): void
    {
        // No reviews yet: no rating (Google rejects an empty one)
        $this->assertArrayNotHasKey('aggregateRating', $this->jsonLd(route('providers.show', $this->provider))[0]);

        foreach ([5, 4] as $rating) {
            Review::create([
                'provider_id' => $this->provider->id, 'user_id' => User::factory()->create()->id, 'rating' => $rating,
                'title' => "Review $rating", 'body' => 'A long enough review body for testing.', 'status' => Review::STATUS_APPROVED,
            ]);
        }

        [$channel, $breadcrumb] = $this->jsonLd(route('providers.show', $this->provider));

        $this->assertSame('Organization', $channel['@type']);
        $this->assertSame(['@type' => 'AggregateRating', 'ratingValue' => 4.5, 'reviewCount' => 2, 'bestRating' => 5, 'worstRating' => 1], $channel['aggregateRating']);
        $this->assertCount(2, $channel['review']);
        $this->assertSame(['https://t.me/gold_pro'], $channel['sameAs']);
        $this->assertSame('BreadcrumbList', $breadcrumb['@type']);
        $this->assertSame('Gold Pro', end($breadcrumb['itemListElement'])['name']);
    }

    public function test_home_page_describes_the_site(): void
    {
        $types = array_column($this->jsonLd('/'), '@type');

        $this->assertSame(['WebSite', 'Organization'], $types);
    }
}
