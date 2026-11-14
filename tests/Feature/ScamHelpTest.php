<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Provider;
use App\Models\ScamCase;
use App\Models\ScamCaseAction;
use App\Models\ScamReport;
use App\Models\User;
use App\Notifications\ScamCaseUpdated;
use App\Notifications\ScamReportReviewed;
use App\Support\ScamHelp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScamHelpTest extends TestCase
{
    use RefreshDatabase;

    protected function member(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    protected function reportFor(User $user, array $extra = []): ScamReport
    {
        $target = ScamHelp::target($extra['account'] ?? '@goldpro_vip', $extra['wallet'] ?? null, null);

        return ScamReport::create($extra + [
            'user_id' => $user->id,
            'scam_case_id' => ScamCase::forTarget($target)->id,
            'scam_type' => 'subscription',
            'payment_method' => 'crypto',
            'amount' => 300,
            'currency' => 'USDT',
            'account' => '@goldpro_vip',
            'story' => 'Paid for the VIP group and was blocked the same day.',
        ]);
    }

    public function test_guide_is_open_and_shows_steps_for_the_situation(): void
    {
        $this->get(route('scam-help'))->assertOk()->assertSee(__('نُصب عليك من قناة توصيات؟'))->assertDontSee('data-scam-advice', false);

        $this->get(route('scam-help', ['type' => 'broker', 'pay' => 'card', 'paid_at' => now()->subDays(10)->toDateString()]))
            ->assertOk()
            ->assertSee('data-step="card"', false)
            ->assertSee('data-step="regulator"', false)
            ->assertSee(__('مر :days يوماً على الدفع. ', ['days' => 10]), false)
            ->assertDontSee('data-step="crypto"', false);

        $this->get(route('scam-help', ['type' => 'recovery', 'pay' => 'crypto']))
            ->assertSee('data-step="recovery"', false)
            ->assertSee('data-step="crypto"', false);
    }

    public function test_late_card_payments_are_told_it_may_be_past_the_usual_window(): void
    {
        $steps = collect(ScamHelp::advice('subscription', 'card', now()->subDays(200)));

        $this->assertStringContainsString('200', $steps->firstWhere('key', 'card')['text']);
        $this->assertSame('stop', $steps->first()['key']);
    }

    public function test_reporting_needs_a_signed_in_member(): void
    {
        $this->get(route('scam-reports.create'))->assertRedirect(route('login'));
        $this->post(route('scam-reports.store'), [])->assertRedirect(route('login'));
    }

    public function test_a_report_with_proofs_joins_the_case_of_its_account(): void
    {
        Storage::fake('local');
        $user = $this->member();

        $this->actingAs($user)->get(route('scam-reports.create', ['type' => 'impersonator']))->assertOk()->assertSee('data-scam-report-form', false);

        $this->actingAs($user)->post(route('scam-reports.store'), [
            'scam_type' => 'impersonator',
            'payment_method' => 'crypto',
            'paid_at' => now()->subDays(3)->toDateString(),
            'amount' => '500',
            'currency' => 'USDT',
            'account' => 'https://t.me/GoldPro_VIP',
            'wallet' => 'TXyz1234567890abcdefghijk',
            'story' => 'He said he was the channel admin and asked for 500 USDT to join VIP.',
            'proofs' => [UploadedFile::fake()->image('chat.jpg'), UploadedFile::fake()->image('receipt.png')],
            'consent_true' => '1',
            'consent_share' => '1',
        ])->assertRedirect(route('scam-reports.index'));

        $report = ScamReport::first();
        $this->assertSame('pending', $report->status);
        $this->assertCount(2, $report->proofs);
        Storage::disk('local')->assertExists($report->proofs[0]);
        $this->assertSame('telegram:goldpro_vip', $report->scamCase->target_key);
        $this->assertEquals(500, $report->amount_usd);
        // Pending reports don't count yet
        $this->assertSame(0, $report->scamCase->reports_count);

        // Another member, the same account written differently: the same case
        $this->actingAs($this->member())->post(route('scam-reports.store'), [
            'scam_type' => 'impersonator', 'payment_method' => 'card', 'account' => '@goldpro_vip',
            'story' => 'Same account messaged me privately and took my card payment.', 'consent_true' => '1',
        ])->assertRedirect();
        $this->assertSame(1, ScamCase::count());
        $this->assertSame(2, ScamCase::first()->reports()->count());

        $this->actingAs($user)->get(route('scam-reports.index'))->assertOk()->assertSee('data-scam-report="'.$report->id.'"', false);
    }

    public function test_a_report_needs_someone_to_gather_it_under_and_the_confirmation(): void
    {
        $this->actingAs($this->member())->post(route('scam-reports.store'), [
            'scam_type' => 'subscription', 'payment_method' => 'bank',
            'story' => 'Paid for a subscription and they disappeared after a week.',
        ])->assertSessionHasErrors(['consent_true']);

        $this->actingAs($this->member())->post(route('scam-reports.store'), [
            'scam_type' => 'subscription', 'payment_method' => 'bank', 'consent_true' => '1',
            'story' => 'Paid for a subscription and they disappeared after a week.',
        ])->assertSessionHasErrors(['account']);

        $this->assertSame(0, ScamReport::count());
    }

    public function test_wallets_and_brokers_make_their_own_cases(): void
    {
        $this->assertSame('wallet:0xabcdef1234567890abcdef12', ScamHelp::target(null, ' 0xABCDEF1234567890abcdef12 ', null)['key']);
        $this->assertSame('broker:megafx', ScamHelp::target('', null, 'Mega FX')['key']);
        $this->assertNull(ScamHelp::target(null, 'short', null));
        $this->assertEquals(80, ScamHelp::toUsd(300, 'SAR'));
    }

    public function test_confirming_counts_the_report_and_tells_the_member(): void
    {
        Notification::fake();
        $user = $this->member();
        $report = $this->reportFor($user);

        $report->update(['status' => ScamReport::STATUS_CONFIRMED]);

        $case = $report->scamCase->fresh();
        $this->assertSame(1, $case->reports_count);
        $this->assertEquals(300, $case->total_usd);
        Notification::assertSentTo($user, ScamReportReviewed::class);
    }

    public function test_every_reporter_hears_about_what_the_team_did(): void
    {
        Notification::fake();
        $confirmed = $this->member();
        $pending = $this->member();
        $report = $this->reportFor($confirmed, ['status' => ScamReport::STATUS_CONFIRMED]);
        $this->reportFor($pending);

        ScamCaseAction::create(['scam_case_id' => $report->scam_case_id, 'kind' => 'telegram', 'note' => 'Sent the file to @notoscam', 'done_at' => now()]);

        Notification::assertSentTo($confirmed, ScamCaseUpdated::class);
        Notification::assertNotSentTo($pending, ScamCaseUpdated::class);
    }

    public function test_the_joint_file_is_for_reporters_of_the_case_and_hides_other_stories(): void
    {
        $user = $this->member();
        $mine = $this->reportFor($user, ['status' => ScamReport::STATUS_CONFIRMED, 'story' => 'My own story about the VIP payment.']);
        $this->reportFor($this->member(), ['status' => ScamReport::STATUS_CONFIRMED, 'story' => 'Someone else private story here.']);

        $this->actingAs($user)->get(route('scam-reports.file', $mine))->assertOk()
            ->assertSee('My own story about the VIP payment.')
            ->assertDontSee('Someone else private story here.')
            ->assertSee('PS-'.$mine->scam_case_id);

        $this->actingAs($this->member())->get(route('scam-reports.file', $mine))->assertNotFound();
    }

    public function test_proofs_are_for_the_team_only(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->image('p.jpg')->store('scam-proofs', 'local');
        $user = $this->member();
        $report = $this->reportFor($user, ['proofs' => [$path]]);

        $this->actingAs($user)->get(route('scam-reports.proof', [$report, 0]))->assertNotFound();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('scam-reports.proof', [$report, 0]))->assertOk();
    }

    public function test_a_case_is_public_only_once_published_and_shows_on_the_channel(): void
    {
        $provider = Provider::create(['name' => 'Gold Pro', 'service_type' => 'Gold', 'telegram_url' => 'https://t.me/goldpro', 'is_active' => true]);
        $provider->categories()->attach(Category::create(['name' => 'Signals', 'slug' => 'signals'])->id);
        $report = $this->reportFor($this->member(), ['account' => '@goldpro', 'status' => ScamReport::STATUS_CONFIRMED]);
        $case = $report->scamCase;
        $this->assertSame($provider->id, $case->provider_id);

        $this->get(route('scam-cases.show', $case))->assertNotFound();
        $this->get(route('providers.show', $provider))->assertDontSee('data-provider-scam-case', false);

        $case->update(['is_public' => true, 'public_summary' => 'Members paid for VIP and were blocked.']);

        $this->get(route('scam-cases.show', $case))->assertOk()->assertSee('Members paid for VIP and were blocked.')
            ->assertDontSee('Paid for the VIP group and was blocked the same day.');
        $this->get(route('providers.show', $provider))->assertSee('data-provider-scam-case', false);
        $this->assertNotNull($case->fresh()->published_at);
    }

    public function test_the_team_can_reach_the_member_on_telegram_or_by_email(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        \Illuminate\Support\Facades\Http::fake(['api.telegram.org/*' => \Illuminate\Support\Facades\Http::response(['ok' => true, 'result' => []])]);
        config(['services.telegram.bot_token' => '123456:TEST']);
        $user = $this->member();
        $user->socialAccounts()->create(['provider' => 'telegram', 'provider_id' => '5551234', 'nickname' => 'ahmad_trader']);
        $report = $this->reportFor($user, ['contact' => '+962 79 123 4567']);
        $admin = User::factory()->create(['is_admin' => true, 'email' => 'team@pipscopes.test']);

        $this->actingAs($admin)->get("/admin/scam-reports/{$report->id}")->assertOk()
            ->assertSee('https://t.me/ahmad_trader', false)
            ->assertSee('https://wa.me/962791234567', false)
            ->assertSee('mailto:'.$user->email, false);

        $this->assertNull(\App\Support\ScamReportContact::sendTelegram($report->fresh(), 'We need the full receipt.'));
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sendMessage') && $r['chat_id'] === '5551234'
            && str_contains($r['text'], 'We need the full receipt.') && str_contains($r['text'], '@goldpro_vip'));

        $this->assertNull(\App\Support\ScamReportContact::sendEmail($report, 'About your report', 'Please send the receipt.', $admin->email));
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\ScamReportMessageMail::class, fn ($m) => $m->hasTo($user->email) && $m->hasReplyTo('team@pipscopes.test'));

        $this->assertSame('https://t.me/someone', \App\Support\ScamReportContact::contactUrl('@someone'));
        $this->assertNull(\App\Support\ScamReportContact::contactUrl('call me after 5'));
    }

    public function test_admin_pages_render(): void
    {
        $report = $this->reportFor($this->member());
        $admin = User::factory()->create(['is_admin' => true]);

        foreach (['/admin/scam-reports', "/admin/scam-reports/{$report->id}", '/admin/scam-cases', "/admin/scam-cases/{$report->scam_case_id}/edit"] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
