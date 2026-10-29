<?php

namespace Tests\Feature;

use App\Models\HomePick;
use App\Models\Provider;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Team picks on the homepage, and the homepage's section order and titles. */
class HomePicksTest extends TestCase
{
    use RefreshDatabase;

    protected function provider(string $name, bool $active = true): Provider
    {
        return Provider::create(['name' => $name, 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/'.str_replace(' ', '', $name), 'is_active' => $active]);
    }

    public function test_picks_show_in_order_with_their_note_and_respect_dates(): void
    {
        $this->get('/')->assertDontSee(__('اختيارات فريق PipScopes'));

        HomePick::create(['provider_id' => $this->provider('Alpha Gold')->id, 'note' => 'Clear entries and exits']);
        HomePick::create(['provider_id' => $this->provider('Beta Forex')->id]);
        HomePick::create(['provider_id' => $this->provider('Future Pick')->id, 'starts_at' => now()->addWeek()]);
        HomePick::create(['provider_id' => $this->provider('Old Pick')->id, 'ends_at' => now()->subDay()]);
        HomePick::create(['provider_id' => $this->provider('Hidden Pick', false)->id]);

        $this->assertSame(['Alpha Gold', 'Beta Forex'], HomePick::showing()->with('provider')->get()->pluck('provider.name')->all());

        $this->get('/')
            ->assertSee(__('اختيارات فريق PipScopes'))
            ->assertSeeInOrder(['Alpha Gold', 'Clear entries and exits', 'Beta Forex'])
            ->assertDontSee('Hidden Pick');

        HomePick::query()->each(fn ($pick) => $pick->update(['position' => $pick->provider->name === 'Beta Forex' ? 0 : 5]));
        $this->get('/')->assertSeeInOrder(['Beta Forex', 'Alpha Gold']);
    }

    public function test_sections_follow_the_saved_order_titles_and_switches(): void
    {
        $this->provider('Alpha Gold');

        $this->get('/')->assertSeeInOrder([__('الأعلى تقييماً'), __('كيف نضمن المصداقية')]);

        Setting::put(['home_layout' => [
            ['key' => 'trust', 'enabled' => true, 'title' => ['ar' => 'لماذا تثق بنا']],
            ['key' => 'top_rated', 'enabled' => true, 'title' => ['ar' => 'الأفضل هذا الشهر'], 'limit' => 4],
            ['key' => 'cta', 'enabled' => false],
        ]]);

        $this->get('/')
            ->assertSeeInOrder(['لماذا تثق بنا', 'الأفضل هذا الشهر'])
            ->assertDontSee(__('تقدّم خدمة توصيات أو نسخ صفقات؟'));
    }

    public function test_top_rated_uses_its_limit(): void
    {
        foreach (range(1, 6) as $i) {
            $this->provider("Channel $i");
        }
        Setting::put(['home_layout' => [['key' => 'top_rated', 'enabled' => true, 'limit' => 4]]]);

        $this->assertCount(4, $this->get('/')->viewData('featured'));
    }

    public function test_admin_pages_open(): void
    {
        HomePick::create(['provider_id' => $this->provider('Alpha Gold')->id, 'note' => 'Great']);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/home-picks')->assertOk()->assertSee('Alpha Gold');
        $this->actingAs($admin)->get('/admin/home-sections')->assertOk()->assertSee('اختيارات الفريق');
    }
}
