<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Market;
use App\Models\Provider;
use App\Models\ProviderSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The add-channel form and the site's default language. */
class SubmissionFormTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::create(['name' => 'توصيات', 'slug' => 'signals']);
        Http::fake(['t.me/*' => Http::response('<meta property="og:title" content="قناة الذهب الذهبية">')]);
    }

    /** @return array<string, mixed> */
    protected function payload(array $overrides = []): array
    {
        return $overrides + [
            'submitter_name' => 'Tester', 'submitter_contact' => '@tester', 'relation' => 'user',
            'category_ids' => [$this->category->id], 'market_ids' => [Market::where('slug', 'gold')->value('id')],
            'name' => 'Gold Channel', 'telegram_url' => '@GoldChannel',
        ];
    }

    public function test_site_opens_in_arabic_whatever_the_browser_language(): void
    {
        $this->get('/', ['Accept-Language' => 'en-US,en;q=0.9'])->assertSee('lang="ar"', false);

        // Until the visitor switches
        $this->get('/lang/en');
        $this->get('/', ['Accept-Language' => 'ar'])->assertSee('lang="en"', false);
    }

    public function test_short_links_are_accepted_and_service_type_is_optional(): void
    {
        $this->post(route('submissions.store'), $this->payload(['instagram_url' => 'instagram.com/gold']))->assertSessionHasNoErrors();

        $submission = ProviderSubmission::first();
        $this->assertSame('https://t.me/GoldChannel', $submission->telegram_url);
        $this->assertSame('https://instagram.com/gold', $submission->instagram_url);
        $this->assertSame('توصيات', $submission->service_type);
    }

    public function test_non_telegram_links_are_rejected(): void
    {
        $this->post(route('submissions.store'), $this->payload(['telegram_url' => 'https://example.com/gold']))
            ->assertSessionHasErrors('telegram_url');
    }

    public function test_channels_already_listed_or_pending_are_rejected(): void
    {
        Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/goldchannel', 'is_active' => true]);
        $this->post(route('submissions.store'), $this->payload())->assertSessionHasErrors('telegram_url');

        $this->post(route('submissions.store'), $this->payload(['telegram_url' => 't.me/other_channel']))->assertSessionHasNoErrors();
        $this->post(route('submissions.store'), $this->payload(['telegram_url' => 'https://t.me/Other_Channel']))->assertSessionHasErrors('telegram_url');
        $this->assertSame(1, ProviderSubmission::count());
    }

    public function test_live_check_reports_status_and_suggests_the_name(): void
    {
        $this->getJson(route('submissions.check', ['telegram' => '@new_gold']))
            ->assertJson(['status' => 'new', 'url' => 'https://t.me/new_gold', 'title' => 'قناة الذهب الذهبية']);

        $provider = Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/gold_pro', 'is_active' => true]);
        $this->getJson(route('submissions.check', ['telegram' => 't.me/GOLD_PRO']))
            ->assertJson(['status' => 'exists', 'name' => 'Gold Pro', 'url' => route('providers.show', $provider)]);

        $this->getJson(route('submissions.check', ['telegram' => 'example.com']))->assertJson(['status' => 'invalid']);
    }
}
