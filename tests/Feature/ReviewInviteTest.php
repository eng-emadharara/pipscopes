<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\ProviderOwner;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Inviting visitors to review or add a channel: the hero's buttons, the pick-a-channel window and the card. */
class ReviewInviteTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->channel = Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/GoldPro', 'is_active' => true]);
    }

    public function test_the_home_page_offers_both_actions_and_the_picker(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('data-hero-invite', false)
            ->assertSee(__('قيّم تجربتك'))
            ->assertSee(__('تصفح جميع القنوات'))
            ->assertSee('data-hero-browse', false)
            ->assertSee('data-open-dialog="review-picker"', false)
            ->assertSee(route('submissions.create'), false)
            ->assertSee('id="review-picker"', false)
            ->assertSee('data-review-invite', false);

        // The channels load when the window opens, not in every page's HTML
        $this->getJson(route('search.suggest', ['popular' => 1]))->assertOk()
            ->assertJsonPath('providers.0.name', 'Gold Pro')
            ->assertJsonPath('providers.0.review_url', route('reviews.create', $this->channel));
    }

    public function test_choosing_a_channel_leads_to_its_review_form_through_sign_in(): void
    {
        $this->getJson(route('search.suggest', ['q' => 'Gold']))->assertOk()
            ->assertJsonPath('providers.0.review_url', route('reviews.create', $this->channel));

        $this->get(route('reviews.create', $this->channel))->assertRedirect(route('login'));
    }

    public function test_the_card_stays_away_from_pages_with_their_own_job(): void
    {
        foreach ([route('login'), route('register'), route('submissions.create'), route('providers.show', $this->channel)] as $page) {
            $this->get($page)->assertOk()->assertDontSee('data-review-invite', false);
        }

        $this->get(route('providers.index'))->assertOk()->assertSee('data-review-invite', false);
    }

    public function test_members_who_reviewed_get_no_card_but_keep_the_hero_buttons(): void
    {
        $member = User::factory()->create();
        $this->actingAs($member)->get(route('home'))->assertSee('data-review-invite', false);

        Review::create(['provider_id' => $this->channel->id, 'user_id' => $member->id, 'rating' => 4,
            'body' => 'A long enough review body for testing.', 'status' => Review::STATUS_APPROVED]);

        $this->actingAs($member)->get(route('home'))
            ->assertDontSee('data-review-invite', false)
            ->assertSee('data-hero-invite', false)
            ->assertSee('id="review-picker"', false);
    }

    public function test_nothing_shows_on_the_closed_launch_page(): void
    {
        Setting::put(['prelaunch_enabled' => true]);

        $this->get(route('home'))->assertOk()->assertSee('data-prelaunch-page', false)
            ->assertDontSee('data-hero-invite', false)
            ->assertDontSee('id="review-picker"', false)
            ->assertDontSee('data-review-invite', false);
    }

    public function test_channel_owners_are_not_invited_to_review(): void
    {
        Setting::put(['owner_portal_enabled' => true]);
        $owner = ProviderOwner::create(['provider_id' => $this->channel->id, 'name' => 'Owner', 'username' => 'owner1', 'email' => 'owner@example.com', 'password' => 'secret-pass-1']);

        $this->actingAs($owner, 'owner');
        $this->app['auth']->shouldUse('web'); // actingAs() would make "owner" the default guard, as it never is on the site

        $this->get(route('home'))->assertOk()
            ->assertDontSee('data-hero-invite', false)
            ->assertDontSee('id="review-picker"', false);
    }
}
