<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\Setting;
use App\Models\TelegramSnapshot;
use App\Support\Site;
use App\Support\TelegramTrend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The 7-day Telegram chart on a channel's page. */
class TelegramTrendTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-27 12:00');
        $this->provider = Provider::create(['name' => 'Gold', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/gold', 'is_active' => true]);
    }

    protected function snapshot(string $date, int $subscribers, int $views): void
    {
        TelegramSnapshot::create(['provider_id' => $this->provider->id, 'date' => $date, 'period' => 'd', 'subscribers' => $subscribers, 'avg_views' => $views]);
    }

    public function test_missing_days_repeat_the_last_known_numbers(): void
    {
        $this->snapshot('2026-10-15', 1000, 100); // before the window: carried into it
        $this->snapshot('2026-10-24', 1200, 300);

        $days = TelegramTrend::lastDays($this->provider);

        $this->assertCount(7, $days);
        $this->assertSame('2026-10-21', $days[0]['date']->toDateString());
        $this->assertSame([1000, 1000, 1000, 1200, 1200, 1200, 1200], array_column($days, 'subscribers'));
        $this->assertSame([10.0, 25.0], [$days[0]['ratio'], $days[6]['ratio']]);
    }

    public function test_chart_shows_with_two_known_days_and_can_be_switched_off(): void
    {
        $page = fn () => $this->get(route('providers.show', $this->provider));

        $this->snapshot('2026-10-27', 1200, 300);
        $page()->assertDontSee('id="telegram-trend"', false); // one day is not a line

        $this->snapshot('2026-10-26', 1100, 250);
        $page()->assertSee('id="telegram-trend"', false)
            ->assertSee(__('نسبة المشاهدات للمشتركين'))
            ->assertSee('+100', false);

        Setting::put([Site::KEY => ['telegram_chart' => false]]);
        $page()->assertDontSee('id="telegram-trend"', false);
    }
}
