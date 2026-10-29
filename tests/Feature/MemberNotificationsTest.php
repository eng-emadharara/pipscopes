<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\Provider;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\MemberNotification;
use App\Notifications\OwnerReplied;
use App\Notifications\ReviewNotPublished;
use App\Notifications\ReviewPublished;
use App\Support\NotificationPrefs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Email and Telegram notifications to members about their reviews. */
class MemberNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.bot_token' => '123456:TEST']);
        $this->provider = Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/GoldPro', 'is_active' => true]);
    }

    protected function telegramUser(array $attributes = []): User
    {
        $user = User::factory()->create(['email' => null, 'email_verified_at' => null, ...$attributes]);
        $user->socialAccounts()->create(['provider' => 'telegram', 'provider_id' => '777000']);

        return $user;
    }

    protected function review(User $user, string $status = Review::STATUS_PENDING): Review
    {
        return Review::create([
            'provider_id' => $this->provider->id, 'user_id' => $user->id, 'rating' => 4,
            'title' => 'Good entries', 'body' => 'A long enough review body for testing.', 'status' => $status,
        ]);
    }

    public function test_review_status_changes_and_first_owner_reply_notify_the_author(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $review = $this->review($user);

        $review->update(['status' => Review::STATUS_APPROVED]);
        Notification::assertSentTo($user, ReviewPublished::class);

        $review->update(['provider_reply' => 'Thanks for your feedback!']);
        $review->update(['provider_reply' => 'Thanks for your feedback, edited.']);
        Notification::assertSentToTimes($user, OwnerReplied::class, 1);

        $review->update(['status' => Review::STATUS_REJECTED]);
        Notification::assertSentTo($user, ReviewNotPublished::class, fn ($n) => $n->removed);

        $other = $this->review(User::factory()->create());
        $other->update(['status' => Review::STATUS_REJECTED]);
        Notification::assertSentTo($other->user, ReviewNotPublished::class, fn ($n) => ! $n->removed);

        // Unrelated edits send nothing
        Notification::assertCount(4);
        $review->update(['title' => 'New title']);
        Notification::assertCount(4);
    }

    public function test_channels_follow_what_the_member_has_and_chose(): void
    {
        $emailUser = User::factory()->create();
        $telegramUser = $this->telegramUser();
        $both = User::factory()->create();
        $both->socialAccounts()->create(['provider' => 'telegram', 'provider_id' => '888000']);
        $unverified = User::factory()->create(['email_verified_at' => null]);

        $this->assertSame(['mail'], NotificationPrefs::channels($emailUser, 'review_status'));
        $this->assertSame([TelegramChannel::class], NotificationPrefs::channels($telegramUser, 'review_status'));
        $this->assertSame(['mail', TelegramChannel::class], NotificationPrefs::channels($both, 'review_status'));
        $this->assertSame([], NotificationPrefs::channels($unverified, 'review_status'));

        $both->forceFill(['notification_prefs' => ['review_status' => ['mail' => false]]])->save();
        $this->assertSame([TelegramChannel::class], NotificationPrefs::channels($both->fresh(), 'review_status'));
        $this->assertSame(['mail', TelegramChannel::class], NotificationPrefs::channels($both->fresh(), 'owner_reply'));

        $telegramUser->forceFill(['telegram_blocked_at' => now()])->save();
        $this->assertSame([], NotificationPrefs::channels($telegramUser->fresh(), 'review_status'));

        Setting::put([NotificationPrefs::SETTING => ['types' => ['owner_reply' => false], 'channels' => ['telegram' => false]]]);
        $this->assertSame([], NotificationPrefs::channels($emailUser, 'owner_reply'));
        $this->assertSame(['mail'], NotificationPrefs::channels($emailUser, 'review_status'));
        $this->assertSame([], NotificationPrefs::channels($telegramUser, 'review_status'));
    }

    public function test_telegram_messages_are_sent_by_the_bot_and_logged(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $user = $this->telegramUser();
        $review = $this->review($user);

        $review->update(['status' => Review::STATUS_APPROVED]);

        Http::assertSent(function ($request) {
            $buttons = collect($request['reply_markup']['inline_keyboard'])->flatten(1);

            return str_contains($request->url(), 'bot123456:TEST/sendMessage')
                && $request['chat_id'] === '777000'
                && str_contains($request['text'], 'Gold Pro')
                && $buttons->contains(fn ($b) => str_contains($b['url'], '/notifications/unsubscribe/'));
        });
        $this->assertDatabaseHas('notification_logs', ['user_id' => $user->id, 'channel' => 'telegram', 'status' => 'sent', 'type' => 'review_status']);
    }

    public function test_a_member_who_blocked_the_bot_is_skipped_afterwards(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Forbidden: bot was blocked by the user'], 403)]);
        $user = $this->telegramUser();

        $this->review($user)->update(['status' => Review::STATUS_APPROVED]);

        $this->assertNotNull($user->fresh()->telegram_blocked_at);
        $log = NotificationLog::sole();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('blocked', $log->error);
        $this->assertSame([], NotificationPrefs::channels($user->fresh(), 'review_status'));
    }

    public function test_email_uses_the_site_layout_with_a_way_to_stop_it(): void
    {
        $user = User::factory()->create();
        $review = $this->review($user, Review::STATUS_APPROVED);
        $review->provider_reply = 'We appreciate it.';

        $mail = (new OwnerReplied($review))->toMail($user);
        $html = (string) $mail->render();

        $this->assertStringContainsString('We appreciate it.', $html);
        $this->assertStringContainsString('/notifications/unsubscribe/'.$user->id.'/owner_reply/mail', html_entity_decode($html));
        $this->assertStringContainsString(route('notifications.edit'), $html);

        // Sending it for real writes the log
        $review->saveQuietly();
        $user->notifyNow(new OwnerReplied($review->fresh()));
        $this->assertDatabaseHas('notification_logs', ['user_id' => $user->id, 'channel' => 'mail', 'status' => 'sent', 'type' => 'owner_reply']);
    }

    public function test_members_choose_their_notifications(): void
    {
        $user = $this->telegramUser();

        $this->actingAs($user)->get(route('notifications.edit'))->assertOk()
            ->assertSee(__('حالة تقييمك'))->assertSee(__('مرتبط، تصلك الرسائل من بوت الموقع'));

        // Email isn't available to this member, so the form leaves it alone
        $this->actingAs($user)->put(route('notifications.update'), ['prefs' => ['review_status' => ['telegram' => '0'], 'owner_reply' => ['telegram' => '1']]])
            ->assertRedirect(route('notifications.edit'));

        $prefs = $user->fresh()->notification_prefs;
        $this->assertFalse($prefs['review_status']['telegram']);
        $this->assertTrue($prefs['owner_reply']['telegram']);
        $this->assertArrayNotHasKey('mail', $prefs['review_status']);

        $this->get(route('profile.edit'))->assertSee(route('notifications.edit'));
    }

    public function test_unsubscribe_links_work_without_signing_in(): void
    {
        $user = User::factory()->create();
        $url = MemberNotification::unsubscribeUrl($user, 'owner_reply', 'mail');

        $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();
        $this->get($url)->assertOk()->assertSee(__('نعم، أوقفها'));
        $this->assertTrue(NotificationPrefs::wants($user, 'owner_reply', 'mail'));

        // Mail apps' one-click unsubscribe: a plain POST, no form token
        $this->post($url)->assertNoContent();
        $this->assertFalse(NotificationPrefs::wants($user->fresh(), 'owner_reply', 'mail'));
        $this->assertTrue(NotificationPrefs::wants($user->fresh(), 'review_status', 'mail'));
        $this->get($url)->assertSee(__('تم إيقاف هذه الإشعارات'));
    }

    public function test_admin_page_opens_and_saves_switches(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get('/admin/notifications')->assertOk()->assertSee('سجل الإشعارات');

        \Livewire\Livewire::test(\App\Filament\Pages\NotificationSettings::class)
            ->set('data.types.owner_reply', false)
            ->call('save');
        $this->assertFalse(NotificationPrefs::siteEnabled('types.owner_reply'));
        $this->assertTrue(NotificationPrefs::siteEnabled('types.review_status'));
    }
}
