<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Members write only their experience; there is no title to fill in. */
class ReviewWithoutTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_review_needs_only_the_rating_and_the_experience(): void
    {
        $provider = Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/GoldPro', 'is_active' => true]);
        $user = User::factory()->create(['created_at' => now()->subMonth()]);

        $this->actingAs($user)->get(route('reviews.create', $provider))->assertOk()->assertDontSee('name="title"', false);

        $this->actingAs($user)->post(route('reviews.store', $provider), [
            'rating' => 4, 'body' => 'التزام واضح بالتوصيات ووقف الخسارة موجود دائماً والنتائج موثقة.',
        ])->assertSessionHasNoErrors();

        $review = Review::firstOrFail();
        $this->assertNull($review->title);
        $this->assertSame(Review::STATUS_APPROVED, $review->status);
        $this->assertStringStartsWith('التزام واضح', $review->headline());

        $this->get(route('providers.show', $provider))->assertOk()->assertSee('التزام واضح بالتوصيات');
        $this->get(route('reviews.show', $review))->assertOk()->assertSee('<title>'.e(__('تقييم :name: :title', ['name' => 'Gold Pro', 'title' => $review->headline(50)])), false);
        $this->get('/')->assertOk();
    }

    public function test_titles_of_older_reviews_are_hidden(): void
    {
        $review = new Review(['title' => 'أفضل قناة', 'body' => 'الدقة عالية والدعم يرد بسرعة.']);

        $this->assertSame('الدقة عالية والدعم يرد بسرعة.', $review->headline());
    }
}
