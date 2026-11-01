<?php

namespace Tests\Feature;

use App\Jobs\SendAdminAlert;
use App\Models\Category;
use App\Models\Provider;
use App\Models\Review;
use App\Models\User;
use App\Support\AdminAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.bot_token' => 'test-token']);
        config(['services.telegram.admin_chat_ids' => '111, -100222']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->provider = Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'is_active' => true, 'telegram_url' => 'https://t.me/goldpro']);
        $this->provider->categories()->attach(Category::create(['name' => 'Signals', 'slug' => 'signals'])->id);
    }

    protected function review(): Review
    {
        return Review::create([
            'provider_id' => $this->provider->id, 'user_id' => User::factory()->create(['name' => 'Sami'])->id,
            'rating' => 2, 'body' => 'Signals came <late> every single day.', 'status' => Review::STATUS_APPROVED,
        ]);
    }

    public function test_contact_message_goes_to_every_saved_chat(): void
    {
        $this->post(route('contact.store'), [
            'name' => 'Visitor', 'email' => 'v@example.com', 'subject' => 'account_deletion', 'message' => 'Please delete my account & data.',
        ])->assertRedirect(route('contact'));

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r['chat_id'] === '-100222'
            && str_contains($r['text'], 'طلب حذف حساب')
            && str_contains($r['text'], 'my account &amp; data')
            && $r['parse_mode'] === 'HTML');
    }

    public function test_review_report_is_sent_once_with_the_reason_and_escaped_text(): void
    {
        $review = $this->review();

        $this->postJson(route('reviews.report', $review), ['reason' => 'fake', 'details' => 'Copied'])->assertOk();
        $this->postJson(route('reviews.report', $review), ['reason' => 'abusive'])->assertOk();

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => str_contains($r['text'], 'بلاغ عن تقييم')
            && str_contains($r['text'], __('trust.report_reasons.fake'))
            && str_contains($r['text'], '&lt;late&gt;')
            && str_contains($r['text'], '★★☆☆☆'));
    }

    public function test_impersonator_report_names_the_account(): void
    {
        $this->post(route('impersonators.report', $this->provider), ['platform' => 'telegram', 'account' => '@goldpro_vip']);

        Http::assertSent(fn (Request $r) => str_contains($r['text'], 'حساب منتحل') && str_contains($r['text'], '@goldpro_vip')
            && str_contains($r['reply_markup']['inline_keyboard'][0][0]['url'], '/admin/impersonators'));
    }

    public function test_nothing_is_sent_without_chats_or_bot(): void
    {
        config(['services.telegram.admin_chat_ids' => null]);
        $this->post(route('contact.store'), ['name' => 'V', 'email' => 'v@example.com', 'subject' => 'general', 'message' => 'Hello there, a question.']);

        config(['services.telegram.admin_chat_ids' => '111', 'services.telegram.bot_token' => null]);
        AdminAlerts::review($this->review());

        Http::assertNothingSent();
    }

    public function test_button_link_refused_by_telegram_moves_into_the_text(): void
    {
        Http::swap(new Factory);
        Http::fake(['api.telegram.org/*' => Http::sequence()
            ->push(['ok' => false, 'description' => 'Bad Request: wrong HTTP URL'], 400)
            ->push(['ok' => true])]);

        $this->assertNull(SendAdminAlert::sendTo('111', 'Hi', ['Open', 'http://pipscopes.test/admin']));

        Http::assertSent(fn (Request $r) => ! isset($r['reply_markup']) && str_contains($r['text'], 'http://pipscopes.test/admin'));
    }
}
