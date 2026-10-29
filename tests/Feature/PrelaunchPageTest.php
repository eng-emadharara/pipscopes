<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\Setting;
use App\Models\User;
use App\Support\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pre-launch mode: visitors get the launch page, the rest of the site waits; admins see everything. */
class PrelaunchPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put(['prelaunch_enabled' => true, 'launch_at' => now('Asia/Amman')->addDay()->format('Y-m-d H:i'),
            'launch_timezone' => 'Asia/Amman', 'show_countdown' => true, 'auto_launch' => true]);
    }

    protected function channels(int $count): void
    {
        foreach (range(1, $count) as $i) {
            Provider::create(['name' => "Channel $i", 'service_type' => 'Signals', 'telegram_url' => "https://t.me/channel_$i", 'is_active' => true]);
        }
    }

    public function test_visitors_get_the_launch_page_with_countdown_features_and_follow_links(): void
    {
        Setting::put([Site::KEY => [...Site::all(), 'socials' => ['telegram' => 'https://t.me/pipscopes', 'instagram' => 'https://instagram.com/pipscopes']]]);
        $this->channels(12);

        $this->get('/')->assertOk()
            ->assertSee('data-countdown', false)
            ->assertSee('تابعنا لتعرف لحظة الإطلاق')
            ->assertSee('href="https://t.me/pipscopes"', false)
            ->assertSee('افحص الحساب قبل الدفع')
            ->assertSee('12 قناة جاهزة للتقييم')
            ->assertSee('لا تفوّت الإطلاق')
            ->assertSee('https://instagram.com/pipscopes', false)
            ->assertSee('name="telegram_url"', false);
    }

    public function test_the_channel_count_and_follow_strip_wait_until_they_mean_something(): void
    {
        Setting::put([Site::KEY => [...Site::all(), 'socials' => []]]);
        $this->channels(3);

        $this->get('/')->assertOk()
            ->assertDontSee('قناة جاهزة للتقييم')
            ->assertDontSee('لا تفوّت الإطلاق')
            ->assertDontSee('تابعنا لتعرف لحظة الإطلاق');
    }

    public function test_the_rest_of_the_site_waits_but_admins_see_everything(): void
    {
        $this->get('/providers')->assertRedirect(route('home'));
        $this->get('/register')->assertRedirect(route('home'));
        $this->get(route('submissions.create'))->assertOk();

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/providers')->assertOk();
    }

    public function test_the_site_opens_by_itself_when_the_countdown_ends(): void
    {
        $this->travel(25)->hours();

        $this->get('/providers')->assertOk();
        $this->get('/')->assertOk()->assertDontSee('data-countdown', false);
    }
}
