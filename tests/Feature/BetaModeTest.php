<?php

namespace Tests\Feature;

use App\Mail\EarlyReviewAlertMail;
use App\Models\Provider;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use App\Support\Launch;
use App\Support\SignupGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The beta period before the official launch: everything open, a countdown on every page, reviews publish straight away. */
class BetaModeTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $channel;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Setting::put(['prelaunch_enabled' => true, 'launch_mode' => 'beta', 'launch_at' => now('Asia/Amman')->addDays(4)->format('Y-m-d H:i'),
            'launch_timezone' => 'Asia/Amman', 'auto_launch' => true]);
        $this->channel = Provider::create(['name' => 'Any Channel', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/any_channel', 'is_active' => true]);
    }

    protected function review(?User $user = null, array $data = [], string $ip = '10.0.0.1')
    {
        return $this->actingAs($user ?? User::factory()->create())->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('reviews.store', $this->channel), $data + ['rating' => 5, 'body' => 'تجربة حقيقية وطويلة بما يكفي رقم '.uniqid()]);
    }

    public function test_the_whole_site_is_open_with_the_countdown_on_every_page(): void
    {
        $this->assertTrue(Launch::isBeta());
        $this->assertFalse(Launch::isPrelaunch());

        foreach ([route('home'), route('providers.index'), route('providers.show', $this->channel), route('login'), route('register')] as $page) {
            $this->get($page)->assertOk()->assertSee('data-launch-bar', false)->assertSee('data-countdown', false)->assertSee(__('النسخة التجريبية'));
        }

        $this->get(route('home'))->assertDontSee('data-prelaunch-page', false);
        $this->get(route('providers.show', $this->channel))->assertDontSee('data-early-banner', false);
        $this->assertStringContainsString(route('providers.show', $this->channel), $this->get('/sitemap.xml')->getContent());
    }

    public function test_without_a_date_it_says_soon_instead_of_a_countdown(): void
    {
        Setting::put(['launch_at' => null]);

        $this->get(route('home'))->assertSee(__('الإطلاق الرسمي قريباً'))->assertDontSee('data-countdown', false);
    }

    public function test_beta_reviews_publish_straight_away_with_an_admin_only_mark(): void
    {
        Setting::put([SignupGuard::SETTING => ['hold_new_reviews' => true, 'hold_same_ip' => true]]);

        $this->review(User::factory()->create());

        $review = Review::firstOrFail();
        $this->assertSame(Review::STATUS_APPROVED, $review->status);
        $this->assertTrue($review->is_early);
        $this->get(route('providers.show', $this->channel))->assertSee($review->body)->assertDontSee('تقييم مبكر');
    }

    public function test_alerts_reach_the_admins_without_stopping_reviews(): void
    {
        User::factory()->create(['is_admin' => true]);

        $this->review(ip: '10.0.0.5');
        $this->review(ip: '10.0.0.5');

        $latest = Review::latest('id')->first();
        $this->assertSame(['same_ip'], $latest->alerts);
        $this->assertSame(Review::STATUS_APPROVED, $latest->status);
        Mail::assertQueued(EarlyReviewAlertMail::class, 1);
    }

    public function test_at_the_launch_the_bar_goes_and_reviews_still_publish_with_alerts(): void
    {
        User::factory()->create(['is_admin' => true]);
        $early = User::factory()->create();
        $this->review($early, ip: '10.0.0.5');

        $this->travel(5)->days();

        $this->assertFalse(Launch::isBeta());
        $this->get(route('home'))->assertOk()->assertDontSee('data-launch-bar', false);
        $this->assertTrue(Review::firstOrFail()->is_early);

        $this->review(User::factory()->create(), ip: '10.0.0.5');

        $late = Review::latest('id')->first();
        $this->assertSame(Review::STATUS_APPROVED, $late->status);
        $this->assertFalse($late->is_early);
        $this->assertSame(['same_ip'], $late->alerts);
        Mail::assertQueued(EarlyReviewAlertMail::class, 1);
    }

    public function test_holds_are_off_by_default_and_can_come_back(): void
    {
        Setting::put(['prelaunch_enabled' => false]);

        $this->review();
        $this->assertSame(Review::STATUS_APPROVED, Review::latest('id')->first()->status);

        Setting::put([SignupGuard::SETTING => ['hold_new_reviews' => true]]);
        $this->review();
        $this->assertSame(Review::STATUS_PENDING, Review::latest('id')->first()->status);
    }

    public function test_the_pre_launch_mode_still_works_and_the_admin_can_choose(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get('/admin/launch-settings')->assertOk()->assertSee('نسخة تجريبية');

        \Livewire\Livewire::test(\App\Filament\Pages\LaunchSettings::class)
            ->assertSet('data.launch_mode', 'beta')->set('data.launch_mode', 'prelaunch')->call('save')->assertHasNoErrors();

        auth()->logout();
        $this->assertTrue(Launch::isPrelaunch());
        $this->assertFalse(Launch::isBeta());
        $this->get(route('home'))->assertSee('data-prelaunch-page', false)->assertDontSee('data-launch-bar', false);
        $this->get(route('providers.index'))->assertRedirect(route('home'));
    }
}
