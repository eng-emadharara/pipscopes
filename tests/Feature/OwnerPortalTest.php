<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Market;
use App\Models\Provider;
use App\Models\ProviderChange;
use App\Models\ProviderOwner;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Channel owners sign in on the site and manage their channel from its public page. */
class OwnerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $provider;

    protected Provider $otherProvider;

    protected ProviderOwner $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put(['owner_portal_enabled' => true]);

        $category = Category::create(['name' => 'Signals', 'slug' => 'signals']);
        foreach (['provider' => 'Gold Pro', 'otherProvider' => 'Other Channel'] as $prop => $name) {
            $this->{$prop} = Provider::create([
                'name' => $name, 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/'.str_replace(' ', '_', $name), 'is_active' => true,
            ]);
            $this->{$prop}->categories()->attach($category->id);
        }

        $this->owner = $this->provider->owners()->create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'Secret-12345']);
    }

    protected function review(Provider $provider, array $attributes = []): Review
    {
        return Review::create($attributes + [
            'provider_id' => $provider->id, 'user_id' => User::factory()->create(['name' => 'Real Name'])->id,
            'rating' => 2, 'title' => 'Review of '.$provider->name, 'body' => 'A long enough review body for testing.',
            'status' => Review::STATUS_APPROVED,
        ]);
    }

    /**
     * Signs in the channel owner. actingAs() would also make "owner" the default guard, which
     * never happens on the real site (the owner guard is only used explicitly), so it's reset.
     */
    protected function asOwner(): static
    {
        $this->actingAs($this->owner, 'owner');
        $this->app['auth']->shouldUse('web');

        return $this;
    }

    /** @return array<string, mixed> a valid "edit channel details" submission */
    protected function editPayload(array $overrides = []): array
    {
        return $overrides + [
            'service_type' => 'Signals', 'telegram_url' => 'https://t.me/Gold_Pro', 'market_ids' => [],
        ];
    }

    public function test_login_is_closed_when_the_portal_is_off(): void
    {
        Setting::put(['owner_portal_enabled' => false]);

        $this->get('/owner/login')->assertStatus(503)->assertSee(__('بوابة أصحاب القنوات غير متاحة حالياً'));
        $this->post('/owner/login', ['email' => 'owner@example.com', 'password' => 'Secret-12345'])->assertStatus(503);
    }

    public function test_owner_signs_in_and_lands_on_their_channel_page_in_owner_mode(): void
    {
        $this->post('/owner/login', ['email' => 'owner@example.com', 'password' => 'Secret-12345'])
            ->assertRedirect(route('providers.show', $this->provider));

        $this->assertAuthenticatedAs($this->owner, 'owner');
        $this->assertNotNull($this->owner->fresh()->last_login_at);

        $this->get(route('providers.show', $this->provider))
            ->assertSee(__('أنت تدير هذه القناة'))
            ->assertSee(__('تعديل بيانات القناة'));

        // Other channels' pages look the same as for any visitor
        $this->get(route('providers.show', $this->otherProvider))->assertDontSee(__('أنت تدير هذه القناة'));
    }

    public function test_wrong_password_or_suspended_account_cannot_sign_in(): void
    {
        $this->post('/owner/login', ['email' => 'owner@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest('owner');

        $this->owner->update(['is_active' => false]);
        $this->post('/owner/login', ['email' => 'owner@example.com', 'password' => 'Secret-12345'])->assertSessionHasErrors('email');
        $this->assertGuest('owner');
    }

    public function test_owner_mode_disappears_when_the_portal_is_switched_off(): void
    {
        $this->asOwner();
        Setting::put(['owner_portal_enabled' => false]);

        $this->get(route('providers.show', $this->provider))->assertDontSee(__('أنت تدير هذه القناة'));
    }

    public function test_owner_replies_from_the_page_as_official(): void
    {
        $review = $this->review($this->provider);

        $this->asOwner()
            ->post(route('owner.reply', [$this->provider, $review]), ['provider_reply' => 'شكراً لملاحظتك، سنعمل على تحسين الدعم.'])
            ->assertRedirect(route('providers.show', $this->provider).'#review-'.$review->id);

        $review->refresh();
        $this->assertSame($this->owner->id, $review->provider_reply_owner_id);
        $this->assertNotNull($review->provider_replied_at);

        $this->get(route('providers.show', $this->provider))
            ->assertSee('شكراً لملاحظتك، سنعمل على تحسين الدعم.')
            ->assertSee(__('رد رسمي من صاحب القناة'));

        $this->delete(route('owner.reply.delete', [$this->provider, $review]))->assertRedirect();
        $this->assertNull($review->fresh()->provider_reply);
    }

    public function test_owner_cannot_reply_on_another_channel(): void
    {
        $other = $this->review($this->otherProvider);

        $this->asOwner()
            ->post(route('owner.reply', [$this->otherProvider, $other]), ['provider_reply' => 'Not my channel at all'])
            ->assertForbidden();

        // Nor through their own channel's URL with someone else's review
        $this->post(route('owner.reply', [$this->provider, $other]), ['provider_reply' => 'Not my channel at all'])
            ->assertNotFound();

        $this->assertNull($other->fresh()->provider_reply);
    }

    public function test_visitors_and_site_members_cannot_reply_or_edit(): void
    {
        $review = $this->review($this->provider);

        $this->post(route('owner.reply', [$this->provider, $review]), ['provider_reply' => 'Hello there'])
            ->assertRedirect(route('owner.login'));

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->put(route('owner.update', $this->provider), $this->editPayload(['service_type' => 'Changed']))
            ->assertRedirect(route('owner.login'));

        $this->assertNull($review->fresh()->provider_reply);
        $this->assertSame('Signals', $this->provider->fresh()->service_type);
    }

    public function test_owner_cannot_review_their_own_channel_and_gets_a_message(): void
    {
        $this->asOwner()
            ->get(route('reviews.create', $this->provider))
            ->assertRedirect(route('providers.show', $this->provider))
            ->assertSessionHas('error', __('لا يمكنك تقييم قناتك لأنك صاحبها. يمكنك الرد على التقييمات من هذه الصفحة.'));
    }

    public function test_owner_cannot_review_other_channels_even_with_a_member_account(): void
    {
        // Same browser, also signed in as a verified site member
        $member = User::factory()->create();
        $this->actingAs($member)->asOwner();

        $this->get(route('reviews.create', $this->otherProvider))
            ->assertRedirect(route('providers.show', $this->otherProvider))
            ->assertSessionHas('error', __('لا يمكن لأصحاب القنوات كتابة تقييمات للقنوات الأخرى، حفاظاً على حيادية التقييمات.'));

        $this->post(route('reviews.store', $this->otherProvider), [
            'rating' => 1, 'title' => 'Competitor', 'body' => 'Trying to leave a review as a channel owner.',
        ])->assertRedirect(route('providers.show', $this->otherProvider));

        $this->assertSame(0, Review::where('user_id', $member->id)->count());
    }

    public function test_owner_cannot_vote_helpful_on_any_channel(): void
    {
        $own = $this->review($this->provider);
        $other = $this->review($this->otherProvider);

        $this->asOwner();
        $this->postJson(route('reviews.helpful', $own))->assertForbidden();
        $this->postJson(route('reviews.helpful', $other))->assertForbidden();
        $this->assertSame(0, $other->fresh()->helpful_count);

        // No vote button for owners, only the count
        $this->get(route('providers.show', $this->otherProvider))->assertDontSee('data-helpful-form', false);
    }

    public function test_owner_reports_only_their_own_channels_reviews(): void
    {
        $own = $this->review($this->provider);
        $other = $this->review($this->otherProvider);

        $this->asOwner();
        $this->postJson(route('reviews.report', $own), ['reason' => 'fake'])->assertOk();
        $this->postJson(route('reviews.report', $other), ['reason' => 'fake'])->assertForbidden();

        $this->get(route('providers.show', $this->otherProvider))->assertDontSee('data-report-form', false);
    }

    public function test_owner_rules_lift_when_the_portal_is_off(): void
    {
        // With the portal off the owner session is ignored and the visitor is treated normally
        Setting::put(['owner_portal_enabled' => false]);
        $other = $this->review($this->otherProvider);

        $this->asOwner()->postJson(route('reviews.helpful', $other))->assertOk();
    }

    public function test_anonymous_reviewers_stay_anonymous_in_owner_mode(): void
    {
        $this->review($this->provider, ['show_name' => false]);

        $this->asOwner()
            ->get(route('providers.show', $this->provider))
            ->assertSee(__('مستخدم مجهول'))
            ->assertDontSee('Real Name');
    }

    public function test_unanswered_filter_shows_only_reviews_without_a_reply(): void
    {
        $this->review($this->provider, ['body' => 'Answered one, a long enough body.', 'provider_reply' => 'Thanks for the feedback']);
        $this->review($this->provider, ['body' => 'Waiting one, a long enough body.']);

        $this->asOwner()
            ->get(route('providers.show', [$this->provider, 'replied' => '0']))
            ->assertSee('Waiting one')
            ->assertDontSee('Answered one');
    }

    public function test_edits_are_saved_and_logged_but_name_is_locked(): void
    {
        $gold = Market::where('slug', 'gold')->value('id');

        $this->asOwner()
            ->put(route('owner.update', $this->provider), $this->editPayload([
                'name' => 'Hacked Name',
                'telegram_url' => 'https://t.me/gold_pro_new',
                'bio' => 'نبذة جديدة',
                'translations' => ['en' => ['bio' => 'New bio'], 'fr' => ['bio' => 'ignored']],
                'market_ids' => [$gold],
                'extra_links' => [['label' => 'Support', 'url' => 'https://t.me/gold_support'], ['label' => '', 'url' => '']],
            ]))
            ->assertRedirect(route('providers.show', $this->provider))
            ->assertSessionHasNoErrors();

        $provider = $this->provider->fresh();
        $this->assertSame('Gold Pro', $provider->name);
        $this->assertSame('https://t.me/gold_pro_new', $provider->telegram_url);
        $this->assertSame(['en' => ['bio' => 'New bio']], $provider->translations);
        $this->assertSame([['label' => 'Support', 'url' => 'https://t.me/gold_support']], $provider->extra_links);
        $this->assertSame(['gold'], $provider->markets->pluck('slug')->all());

        $change = ProviderChange::first();
        $this->assertSame($this->owner->id, $change->provider_owner_id);
        $this->assertSame(['old' => 'https://t.me/Gold_Pro', 'new' => 'https://t.me/gold_pro_new'], $change->diff['telegram_url']);
        $this->assertArrayHasKey('markets', $change->diff);
        $this->assertTrue($change->touchesSensitiveFields());
    }

    public function test_saving_without_changes_logs_nothing(): void
    {
        $this->asOwner()
            ->put(route('owner.update', $this->provider), $this->editPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ProviderChange::count());
    }

    public function test_invalid_edit_reopens_the_window_with_errors(): void
    {
        $this->asOwner()
            ->from(route('providers.show', $this->provider))
            ->put(route('owner.update', $this->provider), $this->editPayload(['telegram_url' => 'not a link']))
            ->assertSessionHasErrorsIn('ownerEdit', 'telegram_url');

        $this->get(route('providers.show', $this->provider))->assertSee('data-open-on-load', false);
    }
}
