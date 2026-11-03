<?php

namespace Tests\Feature;

use App\Mail\EarlyReviewAlertMail;
use App\Mail\VerificationCodeMail;
use App\Models\Provider;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use App\Support\SignupGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Opening a channel before launch at its owner's request: what opens, publishing, alerts and launch day. */
class EarlyAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $open;

    protected Provider $closed;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Setting::put(['prelaunch_enabled' => true, 'launch_at' => now('Asia/Amman')->addDays(5)->format('Y-m-d H:i'),
            'launch_timezone' => 'Asia/Amman', 'show_countdown' => true, 'auto_launch' => true]);

        $this->open = Provider::create(['name' => 'Open Channel', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/open_channel', 'is_active' => true, 'is_early_open' => true]);
        $this->closed = Provider::create(['name' => 'Closed Channel', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/closed_channel', 'is_active' => true]);
    }

    protected function review(?User $user = null, array $data = [], string $ip = '10.0.0.1', ?Provider $provider = null)
    {
        return $this->actingAs($user ?? User::factory()->create())->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('reviews.store', $provider ?? $this->open), $data + ['rating' => 5, 'body' => 'تجربة حقيقية وطويلة بما يكفي رقم '.uniqid()]);
    }

    public function test_only_the_opened_channel_opens_and_browsing_stays_closed(): void
    {
        $this->get(route('providers.show', $this->open))->assertOk();
        $this->get(route('share-images.provider', ['ar', $this->open]))->assertOk();

        $this->get(route('providers.show', $this->closed))->assertRedirect(route('home'));
        $this->get(route('share-images.provider', ['ar', $this->closed]))->assertRedirect(route('home'));
        $this->get(route('providers.index'))->assertRedirect(route('home'));
        $this->get('/search/suggest?q=open')->assertRedirect(route('home'));

        // Signed in makes no difference
        $member = User::factory()->create();
        $this->actingAs($member)->get(route('providers.show', $this->closed))->assertRedirect(route('home'));
        $this->actingAs($member)->get(route('reviews.create', $this->closed))->assertRedirect(route('home'));
        $this->actingAs($member)->get(route('reviews.create', $this->open))->assertOk();
        $this->actingAs($member)->get(route('home'))->assertSee('data-prelaunch-page', false);
    }

    public function test_the_banner_shows_the_launch_date_or_leaves_it_out(): void
    {
        $this->get(route('providers.show', $this->open))->assertOk()
            ->assertSee('data-early-banner', false)
            ->assertSee('PipScopes قيد الإطلاق، وموعد الإطلاق', false)
            ->assertSee('data-local-date', false)
            ->assertSee('تم فتح هذه القناة مبكراً بموافقة صاحبها، وستستطيع تصفح جميع القنوات بعد الإطلاق. كن من أوائل المقيّمين، وتجربتك رح تساعد غيرك يختار صح.');

        Setting::put(['launch_at' => null]);
        $this->get(route('providers.show', $this->open))
            ->assertSee('PipScopes قيد الإطلاق. تم فتح هذه القناة مبكراً بموافقة صاحبها، وستستطيع تصفح جميع القنوات بعد الإطلاق.')
            ->assertDontSee('data-local-date', false);
    }

    public function test_the_channel_page_has_no_way_into_browsing_for_visitors(): void
    {
        $html = $this->get(route('providers.show', $this->open))->assertOk()->getContent();

        $this->assertStringNotContainsString('href="'.route('providers.index').'"', $html);
        $this->assertStringNotContainsString('href="'.route('providers.index').'?', $html);
    }

    public function test_signing_up_is_open_and_brings_the_visitor_back_to_the_channel(): void
    {
        $this->get(route('home'))->assertSee(route('register'), false);
        $this->get(route('register'))->assertOk();
        $this->get(route('login'))->assertOk();

        $this->get(route('providers.show', $this->open));
        $this->post('/register', ['name' => 'Sara', 'email' => 'sara@gmail.com', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1'])
            ->assertRedirect(route('verification.notice'));

        $code = null;
        Mail::assertSent(VerificationCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->post(route('verification.verify'), ['code' => $code])->assertRedirect(route('providers.show', $this->open));
    }

    public function test_early_reviews_publish_straight_away_with_an_admin_only_badge(): void
    {
        $newcomer = User::factory()->create();

        $this->review($newcomer)->assertRedirect(route('reviews.thanks', $this->open));

        $review = Review::firstOrFail();
        $this->assertSame(Review::STATUS_APPROVED, $review->status);
        $this->assertTrue($review->is_early);
        $this->assertNull($review->hold_reason);

        $this->get(route('providers.show', $this->open))->assertSee($review->body)->assertDontSee('تقييم مبكر');

        $this->actingAs($newcomer)->get(route('reviews.thanks', $this->open))->assertOk()
            ->assertSee('تم نشر تقييمك')->assertDontSee('جرّبت قناة أخرى');
    }

    public function test_an_admin_rejection_survives_editing(): void
    {
        $member = User::factory()->create();
        $this->review($member);
        Review::firstOrFail()->update(['status' => Review::STATUS_REJECTED]);

        $this->review($member, ['body' => 'نص معدّل لتقييم رفضته الإدارة من قبل.']);

        $this->assertSame(Review::STATUS_REJECTED, Review::firstOrFail()->status);
        $this->actingAs($member)->get(route('reviews.thanks', $this->open))->assertSee('غير منشور حالياً');
    }

    public function test_signup_protection_still_applies(): void
    {
        $this->post('/register', ['name' => 'Spam', 'email' => 'x@mailinator.com', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1'])
            ->assertSessionHasErrors('email');

        $unverified = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($unverified)->get(route('reviews.create', $this->open))->assertRedirect(route('verification.notice'));
    }

    public function test_a_burst_of_reviews_alerts_the_admins_once(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Setting::put([SignupGuard::SETTING => ['early_alert_burst' => 3]]);

        $this->review(ip: '10.0.0.1');
        $this->review(ip: '10.0.0.2');
        Mail::assertNotQueued(EarlyReviewAlertMail::class);

        $this->review(ip: '10.0.0.3');
        $this->review(ip: '10.0.0.4');

        Mail::assertQueued(EarlyReviewAlertMail::class, 1);
        Mail::assertQueued(EarlyReviewAlertMail::class, fn ($mail) => $mail->hasTo($admin->email) && $mail->reasons === ['burst']);

        $this->assertSame(Review::STATUS_APPROVED, Review::latest('id')->first()->status, 'an alert never stops a review');
        $this->assertSame(['burst'], Review::latest('id')->first()->alerts);
    }

    public function test_copied_text_and_shared_ip_are_flagged(): void
    {
        User::factory()->create(['is_admin' => true]);
        $text = 'قناة ممتازة جداً وأنصح الجميع بالاشتراك فيها.';

        $this->review(data: ['body' => $text], ip: '10.0.0.1');
        $this->review(data: ['body' => '  قناة ممتازة جداً   وأنصح الجميع بالاشتراك فيها.'], ip: '10.0.0.2');
        $this->assertSame(['duplicate'], Review::latest('id')->first()->alerts);

        $this->review(ip: '10.0.0.2');
        $this->assertSame(['same_ip'], Review::latest('id')->first()->alerts);

        Mail::assertQueued(EarlyReviewAlertMail::class, 2);
    }

    public function test_the_sitemap_lists_only_opened_channels_before_launch(): void
    {
        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString(route('providers.show', $this->open), $sitemap);
        $this->assertStringNotContainsString(route('providers.show', $this->closed), $sitemap);

        $this->closed->update(['is_early_open' => true]);
        $this->assertStringContainsString(route('providers.show', $this->closed), $this->get('/sitemap.xml')->getContent());

        // Open to search engines: no noindex on the page
        $this->get(route('providers.show', $this->open))->assertDontSee('noindex', false);
    }

    public function test_on_launch_day_everything_opens_and_holds_work_if_switched_on(): void
    {
        $early = User::factory()->create();
        $this->review($early);

        $this->travel(6)->days();

        $this->get(route('providers.show', $this->closed))->assertOk();
        $this->get(route('providers.show', $this->open))->assertOk()->assertDontSee('data-early-banner', false);

        $this->assertSame(Review::STATUS_APPROVED, Review::firstOrFail()->status);

        \App\Models\Setting::put([\App\Support\SignupGuard::SETTING => ['hold_new_reviews' => true, 'hold_same_ip' => true]]);
        $this->review(User::factory()->create(), ip: '10.9.9.9');
        $late = Review::latest('id')->first();
        $this->assertSame(Review::STATUS_PENDING, $late->status);
        $this->assertSame('new_account', $late->hold_reason);
        $this->assertFalse($late->is_early);
    }

    public function test_admin_pages_show_the_controls(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get('/admin/providers')->assertOk()->assertSee('فتح مبكر');
        $this->get('/admin/signup-protection')->assertOk()->assertSee('إنذار: عدد التقييمات لقناة واحدة خلال ساعة');

        $review = Review::create(['provider_id' => $this->open->id, 'user_id' => User::factory()->create()->id, 'rating' => 5,
            'body' => 'A long enough review body for testing.', 'status' => Review::STATUS_APPROVED, 'is_early' => true]);
        $review->forceFill(['alerts' => ['burst']])->save();

        \Livewire\Livewire::test(\App\Filament\Resources\ReviewResource\Pages\ListReviews::class)
            ->assertSee('تقييم مبكر')->assertSee('موجة تقييمات خلال ساعة')
            ->filterTable('alerted')->assertCanSeeTableRecords([$review]);
    }

    public function test_the_alert_email_renders(): void
    {
        $review = Review::create(['provider_id' => $this->open->id, 'user_id' => User::factory()->create()->id, 'rating' => 1,
            'body' => 'A long enough review body for testing.', 'status' => Review::STATUS_APPROVED]);

        $html = (new EarlyReviewAlertMail($review, ['burst', 'same_ip']))->render();

        $this->assertStringContainsString('Open Channel', $html);
        $this->assertStringContainsString('موجة تقييمات خلال ساعة', $html);
        $this->assertStringContainsString('/admin/reviews', $html);
    }
}
