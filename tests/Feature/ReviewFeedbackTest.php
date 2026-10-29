<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Provider;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = Provider::create(['name' => 'Test Channel', 'service_type' => 'Signals', 'is_active' => true]);
        $this->provider->categories()->attach(Category::create(['name' => 'Signals', 'slug' => 'signals'])->id);
    }

    protected function review(array $attributes = []): Review
    {
        return Review::create($attributes + [
            'provider_id' => $this->provider->id,
            'user_id' => User::factory()->create()->id,
            'rating' => 4,
            'title' => 'Good',
            'body' => 'A long enough review body for testing.',
            'status' => Review::STATUS_APPROVED,
        ]);
    }

    public function test_guest_can_toggle_helpful_vote(): void
    {
        $review = $this->review();

        $this->postJson(route('reviews.helpful', $review))->assertJson(['voted' => true, 'count' => 1]);
        $this->postJson(route('reviews.helpful', $review))->assertJson(['voted' => false, 'count' => 0]);
    }

    public function test_votes_are_counted_per_person(): void
    {
        $review = $this->review();

        $this->actingAs(User::factory()->create())->postJson(route('reviews.helpful', $review))->assertJson(['count' => 1]);
        $this->actingAs(User::factory()->create())->postJson(route('reviews.helpful', $review))->assertJson(['count' => 2]);

        $this->assertSame(2, $review->fresh()->helpful_count);
    }

    public function test_authors_cannot_vote_or_report_their_own_review(): void
    {
        $author = User::factory()->create();
        $review = $this->review(['user_id' => $author->id]);

        $this->actingAs($author)->postJson(route('reviews.helpful', $review))->assertForbidden();
        $this->actingAs($author)->postJson(route('reviews.report', $review), ['reason' => 'fake'])->assertForbidden();
    }

    public function test_hidden_reviews_cannot_receive_feedback(): void
    {
        $review = $this->review(['status' => Review::STATUS_REJECTED]);

        $this->postJson(route('reviews.helpful', $review))->assertNotFound();
    }

    public function test_report_is_stored_once_per_visitor_and_needs_a_valid_reason(): void
    {
        $review = $this->review();

        $this->postJson(route('reviews.report', $review), ['reason' => 'nonsense'])->assertUnprocessable();
        $this->postJson(route('reviews.report', $review), ['reason' => 'fake', 'details' => 'Same text as another review'])->assertOk();
        $this->postJson(route('reviews.report', $review), ['reason' => 'abusive'])->assertOk();

        $this->assertSame(1, ReviewReport::count());
        $this->assertSame('abusive', ReviewReport::first()->reason);
        $this->assertSame(1, $review->fresh()->open_reports_count);
    }

    public function test_reports_do_not_hide_the_review_by_themselves(): void
    {
        $review = $this->review();

        foreach (range(1, 5) as $_) {
            $this->actingAs(User::factory()->create())->postJson(route('reviews.report', $review), ['reason' => 'fake']);
        }

        $this->assertSame(Review::STATUS_APPROVED, $review->fresh()->status);
        $this->assertSame(5, $review->fresh()->open_reports_count);
    }

    public function test_reviews_are_sorted_by_most_helpful_by_default(): void
    {
        // The useful review is older, so the two sorts must order them differently
        $useful = $this->review(['body' => 'Most useful review, long enough body.']);
        $useful->forceFill(['helpful_count' => 7, 'created_at' => now()->subDays(3)])->saveQuietly();
        $this->review(['body' => 'Newer plain review, long enough body.']);

        $this->get(route('providers.show', $this->provider))
            ->assertOk()
            ->assertSeeInOrder(['Most useful review', 'Newer plain review']);

        $this->get(route('providers.show', [$this->provider, 'sort' => 'newest']))
            ->assertSeeInOrder(['Newer plain review', 'Most useful review']);
    }
}
