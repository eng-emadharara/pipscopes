<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Market;
use App\Models\Provider;
use App\Models\ProviderSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FakesTelegram;
use Tests\TestCase;

/** The add-channel form (link + markets + categories, the rest read from Telegram) and the default language. */
class SubmissionFormTest extends TestCase
{
    use FakesTelegram, RefreshDatabase;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->category = Category::create(['name' => 'توصيات', 'slug' => 'signals']);
        $this->fakeTelegram([
            'GoldChannel' => ['title' => 'قناة الذهب الذهبية', 'verified' => true],
            'other_channel' => [],
            'some_person' => ['extra' => '@some_person'],
            'some_group' => ['extra' => '1 200 members, 40 online'],
        ]);
    }

    /** @return array<string, mixed> */
    protected function payload(array $overrides = []): array
    {
        return $overrides + [
            'telegram_url' => '@GoldChannel',
            'category_ids' => [$this->category->id],
            'market_ids' => [Market::where('slug', 'gold')->value('id')],
        ];
    }

    public function test_site_opens_in_arabic_whatever_the_browser_language(): void
    {
        $this->get('/', ['Accept-Language' => 'en-US,en;q=0.9'])->assertSee('lang="ar"', false);

        // Until the visitor switches
        $this->get('/lang/en');
        $this->get('/', ['Accept-Language' => 'ar'])->assertSee('lang="en"', false);
    }

    public function test_form_asks_only_for_the_link_markets_and_categories(): void
    {
        $this->get(route('submissions.create'))
            ->assertSee('name="telegram_url"', false)
            ->assertSee('name="market_ids[]"', false)
            ->assertSee('name="category_ids[]"', false)
            ->assertDontSee('name="name"', false)
            ->assertDontSee('name="submitter_name"', false)
            ->assertDontSee('name="submitter_contact"', false);
    }

    public function test_channel_details_and_photo_are_read_from_telegram_and_stored(): void
    {
        $this->post(route('submissions.store'), $this->payload())->assertSessionHasNoErrors();

        $submission = ProviderSubmission::first();
        $this->assertSame('https://t.me/GoldChannel', $submission->telegram_url);
        $this->assertSame('قناة الذهب الذهبية', $submission->name);
        $this->assertSame('Daily gold signals', $submission->description);
        $this->assertSame('توصيات', $submission->service_type);
        $this->assertNull($submission->submitter_name);
        $this->assertSame(12345, $submission->telegram_data['subscribers']);
        $this->assertTrue($submission->telegram_data['verified']);
        $this->assertSame(1000, $submission->telegram_data['avg_views']);
        $this->assertSame('2026-10-25T10:00:00+00:00', $submission->telegram_data['last_post_at']);

        // The photo is our own copy, not a link to Telegram
        $this->assertStringStartsWith('avatars/tg-goldchannel-', $submission->avatar);
        Storage::disk('public')->assertExists($submission->avatar);

        // …and it all carries over when the admin approves it
        $provider = $submission->convertToProvider();
        $this->assertSame($submission->avatar, $provider->avatar);
        $this->assertSame(12345, $provider->telegram_members);
    }

    public function test_private_channels_people_groups_and_unknown_links_are_rejected(): void
    {
        // Six tries in a row would hit the form's 5-per-10-minutes limit
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        foreach ([
            't.me/+AbCdEf123' => __('هذه القناة خاصة، ولا يمكن إضافتها. نضيف القنوات العامة فقط.'),
            'https://t.me/joinchat/AbCdEf' => __('هذه القناة خاصة، ولا يمكن إضافتها. نضيف القنوات العامة فقط.'),
            '@some_person' => __('هذا الرابط لحساب شخصي أو مجموعة أو بوت، وليس لقناة.'),
            '@some_group' => __('هذا الرابط لحساب شخصي أو مجموعة أو بوت، وليس لقناة.'),
            '@no_such_channel' => __('لم نجد قناة بهذا الرابط على تيليجرام. تأكد من كتابته بشكل صحيح.'),
            'https://example.com/gold' => __('أدخل رابط قناة تيليجرام صحيحاً، مثل t.me/channel أو @channel.'),
        ] as $link => $message) {
            $this->post(route('submissions.store'), $this->payload(['telegram_url' => $link]))
                ->assertSessionHasErrors(['telegram_url' => $message]);
        }

        $this->assertSame(0, ProviderSubmission::count());
    }

    public function test_channels_already_listed_or_pending_are_rejected(): void
    {
        Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/goldchannel', 'is_active' => true]);
        $this->post(route('submissions.store'), $this->payload())->assertSessionHasErrors('telegram_url');

        $this->post(route('submissions.store'), $this->payload(['telegram_url' => 't.me/other_channel']))->assertSessionHasNoErrors();
        $this->post(route('submissions.store'), $this->payload(['telegram_url' => 'https://t.me/Other_Channel']))->assertSessionHasErrors('telegram_url');
        $this->assertSame(1, ProviderSubmission::count());
    }

    public function test_live_check_previews_the_channel_or_explains_the_problem(): void
    {
        $this->getJson(route('submissions.check', ['telegram' => '@GoldChannel']))
            ->assertJson(['status' => 'new', 'url' => 'https://t.me/GoldChannel', 'title' => 'قناة الذهب الذهبية', 'verified' => true])
            ->assertJsonPath('photo', 'https://cdn4.telesco.pe/file/GoldChannel.jpg');

        $this->getJson(route('submissions.check', ['telegram' => 't.me/+secret']))
            ->assertJson(['status' => 'private', 'message' => __('هذه القناة خاصة، ولا يمكن إضافتها. نضيف القنوات العامة فقط.')]);

        $provider = Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/gold_pro', 'is_active' => true]);
        $this->getJson(route('submissions.check', ['telegram' => 't.me/GOLD_PRO']))
            ->assertJson(['status' => 'exists', 'name' => 'Gold Pro', 'url' => route('providers.show', $provider)]);
    }
}
