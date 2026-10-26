<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Provider;
use App\Models\Review;
use App\Models\User;
use App\Support\ArabicShaper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SharingTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $provider;

    protected Review $review;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->provider = Provider::create([
            'name' => 'قناة صقر الفوركس', 'slug' => 'saqr', 'service_type' => 'توصيات فوركس MT4/MT5',
            'telegram_url' => 'https://t.me/saqr_fx', 'is_active' => true, 'is_verified' => true,
        ]);
        $this->provider->categories()->attach(Category::create(['name' => 'Signals', 'slug' => 'signals'])->id);

        $this->review = Review::create([
            'provider_id' => $this->provider->id, 'user_id' => User::factory()->create()->id, 'rating' => 5,
            'title' => 'أفضل قناة جربتها', 'body' => 'الدقة عالية والدعم يرد بسرعة على أي استفسار.', 'status' => Review::STATUS_APPROVED,
        ]);
    }

    protected function assertPng(\Illuminate\Testing\TestResponse $response): void
    {
        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        [$width, $height] = getimagesize($response->baseResponse->getFile()->getPathname());
        $this->assertSame([1200, 630], [$width, $height]);
    }

    public function test_share_images_are_generated_in_both_languages(): void
    {
        $this->assertPng($this->get(route('share-images.provider', ['ar', $this->provider])));
        $this->assertPng($this->get(route('share-images.provider', ['en', $this->provider])));
        $this->assertPng($this->get(route('share-images.review', ['ar', $this->review])));
        $this->assertPng($this->get(route('share-images.site', ['en'])));
    }

    public function test_images_are_cached_until_the_content_changes(): void
    {
        $first = $this->get(route('share-images.provider', ['ar', $this->provider]))->baseResponse->getFile()->getPathname();
        $again = $this->get(route('share-images.provider', ['ar', $this->provider]))->baseResponse->getFile()->getPathname();
        $this->assertSame($first, $again);

        $this->provider->forceFill(['rating_avg' => 2.5, 'reviews_count' => 9])->saveQuietly();
        $changed = $this->get(route('share-images.provider', ['ar', $this->provider->fresh()]))->baseResponse->getFile()->getPathname();
        $this->assertNotSame($first, $changed);
    }

    public function test_hidden_content_has_no_image_or_page(): void
    {
        $this->review->update(['status' => Review::STATUS_REJECTED]);

        $this->get(route('share-images.review', ['ar', $this->review]))->assertNotFound();
        $this->get(route('reviews.show', $this->review))->assertNotFound();
        $this->get('/share-images/fr/site.png')->assertNotFound();
    }

    public function test_pages_carry_social_meta_tags_and_share_buttons(): void
    {
        $this->get(route('providers.show', [$this->provider, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('property="og:image" content="'.route('share-images.provider', ['en', $this->provider]).'"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false)
            ->assertSee('https://t.me/share/url?url='.rawurlencode($this->provider->shareUrl()), false);

        $this->get(route('reviews.show', [$this->review, 'lang' => 'ar']))
            ->assertOk()
            ->assertSee('property="og:image" content="'.route('share-images.review', ['ar', $this->review]).'"', false)
            ->assertSee('property="og:type" content="article"', false)
            ->assertSee('أفضل قناة جربتها');

        $this->get(route('home'))->assertSee(route('share-images.site', 'ar'), false);
    }

    public function test_arabic_shaper_joins_letters_and_keeps_latin_runs_in_order(): void
    {
        // Lam-alef ligature, isolated
        $this->assertSame(mb_chr(0xFEFB), ArabicShaper::shape('لا'));

        // "سلام": initial seen, medial lam-alef? no: lam + alef ligature (final form), then isolated meem
        $this->assertSame(implode('', array_map('mb_chr', [0xFEB3, 0xFEFC, 0xFEE1])), ArabicShaper::shape('سلام'));

        // Visual order: Latin and numbers stay readable inside a reversed Arabic line
        $visual = ArabicShaper::forImage('عبر MT4/MT5 بسعر 4.6');
        $this->assertStringContainsString('MT4/MT5', $visual);
        $this->assertStringContainsString('4.6', $visual);
        $this->assertTrue(mb_strpos($visual, '4.6') < mb_strpos($visual, 'MT4/MT5'), 'RTL: the last word is drawn first (leftmost)');

        $this->assertSame('Gold Pro', ArabicShaper::forImage('Gold Pro'));
    }
}
