<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The shorter review form and the thank-you page after it. */
class ReviewFormTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/GoldPro', 'is_active' => true]);
    }

    protected function post_review(User $user, array $data = [])
    {
        return $this->actingAs($user)->post(route('reviews.store', $this->provider), $data + [
            'rating' => 5, 'body' => 'التزام واضح بالتوصيات ووقف الخسارة موجود دائماً.', 'show_name' => '1',
        ]);
    }

    public function test_details_and_criteria_sit_by_the_stars_with_nothing_folded(): void
    {
        $user = User::factory()->create(['name' => 'Sara']);

        $this->actingAs($user)->get(route('reviews.create', $this->provider))->assertOk()
            ->assertSeeInOrder(['name="rating"', 'data-for="cons"', 'data-for="pros"', 'name="rating_accuracy"', 'name="body"',
                'name="experience_duration" value="lt_1m"', 'name="show_name"'], false)
            ->assertDontSee('<details', false)
            ->assertSee('data-count-for="body"', false)
            ->assertSee('ممتازة')
            ->assertSee('باسمي')->assertSee('Sara')->assertSee('مجهول');
    }

    public function test_members_stay_signed_in_after_login_and_sign_up(): void
    {
        User::factory()->create(['email' => 'sara@gmail.com', 'password' => 'secret-pass-1']);

        $this->post('/login', ['email' => 'sara@gmail.com', 'password' => 'secret-pass-1'])
            ->assertCookie(auth()->guard('web')->getRecallerName());
        auth()->logout();

        \Illuminate\Support\Facades\Mail::fake();
        $this->post('/register', ['name' => 'New', 'email' => 'new@gmail.com', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1'])
            ->assertCookie(auth()->guard('web')->getRecallerName());
    }

    public function test_the_thank_you_page_asks_for_the_skipped_details_and_saves_them(): void
    {
        $user = User::factory()->create(['created_at' => now()->subMonth()]);
        $this->post_review($user, ['rating' => 2]);

        // Low stars: offers what went wrong, not what went well
        $this->actingAs($user)->get(route('reviews.thanks', $this->provider))->assertOk()
            ->assertSee('أكمل تقييمك بنقرتين')
            ->assertSee('name="cons[]"', false)->assertDontSee('name="pros[]"', false)
            ->assertSee('name="experience_duration"', false);

        $review = Review::firstOrFail();
        $before = [$review->body, $review->rating, $review->status];

        $this->actingAs($user)->patch(route('reviews.details', $this->provider), ['cons' => ['deleted_trades', 'losses'], 'experience_duration' => '1_3m'])
            ->assertRedirect(route('reviews.thanks', $this->provider));

        $review->refresh();
        $this->assertSame(['deleted_trades', 'losses'], $review->cons);
        $this->assertSame('1_3m', $review->experience_duration);
        $this->assertSame($before, [$review->body, $review->rating, $review->status]);

        // Nothing left to ask
        $this->actingAs($user)->get(route('reviews.thanks', $this->provider))->assertDontSee('أكمل تقييمك بنقرتين');

        $this->actingAs($user)->patch(route('reviews.details', $this->provider), ['cons' => ['made-up']])->assertSessionHasErrors('cons.0');
    }

    public function test_details_from_the_thank_you_page_keep_a_held_review_held(): void
    {
        $user = User::factory()->create();
        $this->post_review($user);

        $this->actingAs($user)->patch(route('reviews.details', $this->provider), ['pros' => ['accurate']]);

        $review = Review::firstOrFail();
        $this->assertSame(Review::STATUS_PENDING, $review->status);
        $this->assertSame('new_account', $review->hold_reason);
        $this->assertSame(['accurate'], $review->pros);
    }

    public function test_choosing_anonymous_hides_the_name(): void
    {
        $user = User::factory()->create(['created_at' => now()->subMonth()]);

        $this->post_review($user, ['show_name' => '0'])->assertSessionHasNoErrors();

        $this->assertFalse(Review::firstOrFail()->show_name);
    }

    public function test_a_published_review_lands_on_the_thank_you_page_with_sharing_and_suggestions(): void
    {
        $user = User::factory()->create(['created_at' => now()->subMonth()]);
        $other = Provider::create(['name' => 'Crypto Whales', 'service_type' => 'Crypto', 'telegram_url' => 'https://t.me/Whales', 'is_active' => true]);

        $this->post_review($user)->assertRedirect(route('reviews.thanks', $this->provider));

        $review = Review::firstOrFail();
        $this->actingAs($user)->get(route('reviews.thanks', $this->provider))->assertOk()
            ->assertSee('تم نشر تقييمك')
            ->assertSee($review->shareUrl(), false)
            ->assertSee('Crypto Whales')
            ->assertSee(route('reviews.create', $other), false)
            ->assertDontSee(route('reviews.create', $this->provider).'"><', false);

        $this->post_review($user, ['rating' => 4]);
        $this->actingAs($user)->get(route('reviews.thanks', $this->provider))->assertSee('تم حفظ تعديلات تقييمك');
    }

    public function test_a_held_review_explains_why_it_waits(): void
    {
        $user = User::factory()->create();

        $this->post_review($user)->assertRedirect(route('reviews.thanks', $this->provider));

        $this->actingAs($user)->get(route('reviews.thanks', $this->provider))->assertOk()
            ->assertSee('سيظهر تقييمك خلال 24 ساعة')
            ->assertSee('لحماية الموقع من التقييمات الوهمية')
            ->assertDontSee('data-share', false);
    }

    public function test_the_thank_you_page_needs_a_review(): void
    {
        $this->actingAs(User::factory()->create())->get(route('reviews.thanks', $this->provider))
            ->assertRedirect(route('providers.show', $this->provider));
    }
}
