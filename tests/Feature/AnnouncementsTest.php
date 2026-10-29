<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Market;
use App\Models\NotificationLog;
use App\Models\Provider;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use App\Notifications\Channels\TelegramChannel;
use App\Support\AnnouncementAudience;
use App\Support\AnnouncementSender;
use App\Support\NotificationPrefs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Admin announcements to members: audience, channels, weekly cap, sending, scheduling and clicks. */
class AnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.bot_token' => '123456:TEST']);
    }

    protected function telegramUser(string $id = '777000', array $attributes = []): User
    {
        $user = User::factory()->create(['email' => null, 'email_verified_at' => null, ...$attributes]);
        $user->socialAccounts()->create(['provider' => 'telegram', 'provider_id' => $id]);

        return $user;
    }

    protected function announcement(array $attributes = []): Announcement
    {
        return Announcement::create([
            'title' => 'ميزة جديدة', 'body' => "الفقرة الأولى.\n\nالفقرة الثانية.", 'button_label' => 'جرّبها',
            'button_url' => 'https://pipscopes.com/providers', 'audience' => ['channel' => 'auto'], ...$attributes,
        ]);
    }

    public function test_auto_mode_sends_one_message_per_member_preferring_telegram(): void
    {
        $emailOnly = User::factory()->create();
        $telegramOnly = $this->telegramUser();
        $both = User::factory()->create();
        $both->socialAccounts()->create(['provider' => 'telegram', 'provider_id' => '888000']);
        $optedOut = User::factory()->create(['notification_prefs' => null]);
        $optedOut->forceFill(['notification_prefs' => ['announcements' => ['mail' => false]]])->save();

        $this->assertSame(['mail'], AnnouncementAudience::channelsFor($emailOnly, 'auto'));
        $this->assertSame([TelegramChannel::class], AnnouncementAudience::channelsFor($telegramOnly, 'auto'));
        $this->assertSame([TelegramChannel::class], AnnouncementAudience::channelsFor($both, 'auto'));
        $this->assertSame(['mail', TelegramChannel::class], AnnouncementAudience::channelsFor($both, 'both'));
        $this->assertSame(['mail'], AnnouncementAudience::channelsFor($both, 'mail'));
        $this->assertSame([], AnnouncementAudience::channelsFor($emailOnly, 'telegram'));

        $this->assertSame(['total' => 3, 'mail' => 1, 'telegram' => 2], AnnouncementAudience::estimate(['channel' => 'auto']));

        // Admin can make announcements opt-in only
        Setting::put([NotificationPrefs::SETTING => ['announcements_default' => false]]);
        $this->assertSame(0, AnnouncementAudience::estimate(['channel' => 'auto'])['total']);
    }

    public function test_audience_filters_by_market_reviews_and_join_date(): void
    {
        $gold = Market::create(['name' => 'سوق تجريبي', 'slug' => 'test-market', 'is_active' => true]);
        $provider = Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/GoldPro', 'is_active' => true]);
        $provider->markets()->attach($gold->id);

        $reviewer = User::factory()->create();
        Review::create(['provider_id' => $provider->id, 'user_id' => $reviewer->id, 'rating' => 5, 'title' => 'Great',
            'body' => 'A long enough review body for testing.', 'status' => Review::STATUS_APPROVED]);
        $newcomer = User::factory()->create();
        $old = User::factory()->create(['created_at' => now()->subMonths(6)]);

        $ids = fn (array $audience) => AnnouncementAudience::query($audience)->pluck('id')->sort()->values()->all();

        $this->assertSame([$reviewer->id], $ids(['markets' => [$gold->id]]));
        $this->assertSame([$reviewer->id], $ids(['reviewed' => 'yes']));
        $this->assertSame([$newcomer->id, $old->id], $ids(['reviewed' => 'no']));
        $this->assertSame([$reviewer->id, $newcomer->id], $ids(['joined_days' => 30]));
    }

    public function test_sending_queues_each_member_and_counts_results_and_clicks(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $telegramUser = $this->telegramUser();
        $emailUser = User::factory()->create();
        $announcement = $this->announcement();

        $this->assertSame(2, AnnouncementSender::send($announcement));
        $this->assertSame(0, AnnouncementSender::send($announcement->fresh()), 'never sent twice');

        $announcement->refresh();
        $this->assertSame('sent', $announcement->status);
        $this->assertSame(2, $announcement->recipients_count);
        $this->assertSame(2, $announcement->sent_count);
        $this->assertSame(2, NotificationLog::where('announcement_id', $announcement->id)->where('status', 'sent')->count());

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'الفقرة الثانية')
            && $request['reply_markup']['inline_keyboard'][0][0]['url'] === route('announcements.click', [$announcement, 'telegram']));

        $this->get(route('announcements.click', [$announcement, 'mail']))
            ->assertRedirect('https://pipscopes.com/providers?utm_source=email&utm_medium=announcement&utm_campaign=announcement-'.$announcement->id);
        $this->assertSame(1, $announcement->fresh()->clicks_count);
    }

    public function test_weekly_cap_skips_members_who_had_enough(): void
    {
        $user = User::factory()->create();
        Setting::put([NotificationPrefs::SETTING => ['weekly_cap' => 1]]);

        AnnouncementSender::send($this->announcement());
        $this->assertSame(0, AnnouncementAudience::estimate(['channel' => 'auto'])['total']);
        $this->assertSame(0, AnnouncementSender::send($this->announcement()));

        $this->travel(8)->days();
        $this->assertSame(1, AnnouncementAudience::estimate(['channel' => 'auto'])['total']);
    }

    public function test_scheduled_announcements_go_out_at_their_time(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $announcement = $this->announcement(['status' => 'scheduled', 'scheduled_at' => now()->addHour()]);

        $this->assertSame(0, AnnouncementSender::sendDue());
        Notification::assertNothingSent();

        $this->travel(61)->minutes();
        $this->assertSame(1, AnnouncementSender::sendDue());
        Notification::assertSentTo($user, AnnouncementNotification::class, fn ($n) => $n->announcement->is($announcement));
    }

    public function test_photo_goes_with_the_telegram_message_and_into_the_email(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        \Illuminate\Support\Facades\Storage::fake('public');
        $image = \Illuminate\Http\UploadedFile::fake()->image('cover.png', 1200, 630)->store('announcements', 'public');
        $user = $this->telegramUser();
        $announcement = $this->announcement(['image' => $image]);

        $user->notifyNow(new AnnouncementNotification($announcement, [TelegramChannel::class]));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendPhoto') && str_contains($request['caption'], 'ميزة جديدة'));

        $html = (string) (new AnnouncementNotification($announcement, ['mail']))->toMail(User::factory()->create())->render();
        $this->assertStringContainsString(asset('storage/'.$image), $html);
        $this->assertStringContainsString(route('announcements.click', [$announcement, 'mail']), $html);
        $this->assertStringContainsString('/announcements/mail', html_entity_decode($html));
    }

    public function test_members_get_it_in_their_language(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $user = $this->telegramUser(attributes: ['locale' => 'en']);
        $announcement = $this->announcement(['translations' => ['en' => ['title' => 'New feature', 'body' => 'English body.']]]);

        AnnouncementSender::send($announcement);

        Http::assertSent(fn ($request) => str_contains($request['text'], 'New feature') && str_contains($request['text'], 'English body.'));
    }

    public function test_the_site_language_is_remembered_on_the_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/?lang=en');
        $this->assertSame('en', $user->fresh()->locale);
        $this->assertSame('en', $user->fresh()->preferredLocale());
    }

    public function test_admin_can_create_test_and_send(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create();
        $this->actingAs($admin);

        $this->get('/admin/announcements')->assertOk();
        $this->get('/admin/announcements/create')->assertOk()->assertSee('سيصل إلى');

        $announcement = $this->announcement();
        $this->get("/admin/announcements/{$announcement->id}/edit")->assertOk();

        Notification::fake();
        \Livewire\Livewire::test(\App\Filament\Resources\AnnouncementResource\Pages\EditAnnouncement::class, ['record' => $announcement->getRouteKey()])
            ->callAction('test');
        Notification::assertSentTo($admin, AnnouncementNotification::class, fn ($n) => $n->isTest);
        Notification::assertNotSentTo($member, AnnouncementNotification::class);

        \Livewire\Livewire::test(\App\Filament\Resources\AnnouncementResource\Pages\EditAnnouncement::class, ['record' => $announcement->getRouteKey()])
            ->callAction('send');
        Notification::assertSentTo($member, AnnouncementNotification::class);
        $this->assertSame('sent', $announcement->fresh()->status);
    }
}
