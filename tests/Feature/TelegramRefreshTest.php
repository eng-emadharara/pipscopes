<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\TelegramSnapshot;
use App\Models\User;
use App\Services\TelegramRefresh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FakesTelegram;
use Tests\TestCase;

/** Daily Telegram numbers: refresh, history kept only on change, and compaction of old rows. */
class TelegramRefreshTest extends TestCase
{
    use FakesTelegram, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function channel(string $username, array $attributes = []): Provider
    {
        return Provider::create($attributes + [
            'name' => $username, 'service_type' => 'Signals', 'telegram_url' => "https://t.me/$username", 'is_active' => true,
        ]);
    }

    public function test_refresh_updates_numbers_and_records_today(): void
    {
        $this->fakeTelegram(['gold' => ['extra' => '5 000 subscribers']]);
        $provider = $this->channel('gold');

        $this->artisan('telegram:refresh', ['--delay' => 0])->assertSuccessful();

        $provider->refresh();
        $this->assertSame(5000, $provider->telegram_members);
        $this->assertSame(1000, $provider->telegram_data['avg_views']);
        $this->assertSame('channel', $provider->telegram_status);
        $this->assertNotNull($provider->telegram_checked_at);
        $this->assertNotNull($provider->avatar); // had none, took the channel's photo

        $snapshot = TelegramSnapshot::sole();
        $this->assertSame([now()->toDateString(), 'd', 5000, 1000], [$snapshot->date, $snapshot->period, $snapshot->subscribers, $snapshot->avg_views]);

        // Read today: not due again until tomorrow
        $this->assertSame(0, TelegramRefresh::due()->count());
    }

    public function test_unchanged_numbers_add_no_row_but_changes_do(): void
    {
        $this->fakeTelegram(['steady' => ['extra' => '5 000 subscribers'], 'growing' => ['extra' => '5 600 subscribers']]);
        $steady = $this->channel('steady');
        $growing = $this->channel('growing');
        foreach ([$steady, $growing] as $provider) {
            TelegramSnapshot::create(['provider_id' => $provider->id, 'date' => now()->subDays(3)->toDateString(), 'period' => 'd', 'subscribers' => 5000, 'avg_views' => 1000]);
            TelegramRefresh::refresh($provider);
        }

        $this->assertSame([5000], $steady->telegramSnapshots()->pluck('subscribers')->all());
        $this->assertSame([5000, 5600], $growing->telegramSnapshots()->orderBy('date')->pluck('subscribers')->all());
    }

    public function test_failures_keep_the_last_numbers_and_slow_down_retries(): void
    {
        $this->fakeTelegram([]);
        $provider = $this->channel('gone', ['telegram_members' => 900, 'telegram_failures' => TelegramRefresh::GIVE_UP_AFTER - 1]);

        TelegramRefresh::refresh($provider);

        $provider->refresh();
        $this->assertSame(900, $provider->telegram_members);
        $this->assertSame('not_found', $provider->telegram_status);
        $this->assertSame(TelegramRefresh::GIVE_UP_AFTER, $provider->telegram_failures);

        // Given up: due again only after a week
        $this->travel(2)->days();
        $this->assertSame(0, TelegramRefresh::due()->count());
        $this->travel(6)->days();
        $this->assertSame(1, TelegramRefresh::due()->count());
    }

    public function test_compaction_merges_old_days_into_weeks_and_old_weeks_into_months(): void
    {
        $this->travelTo('2026-10-27');
        $provider = $this->channel('gold');
        $add = fn (string $date, string $period, int $subs) => TelegramSnapshot::create(['provider_id' => $provider->id, 'date' => $date, 'period' => $period, 'subscribers' => $subs, 'avg_views' => $subs / 10]);

        // Two days of one old week (weeks start on Saturday), and a recent day that stays
        $add('2026-06-06', 'd', 1000);
        $add('2026-06-08', 'd', 2000);
        $add('2026-10-20', 'd', 5000);
        // Two weeks of one month from last year
        $add('2025-03-01', 'w', 300);
        $add('2025-03-15', 'w', 500);

        $this->artisan('telegram:compact')->assertSuccessful();

        $rows = TelegramSnapshot::orderBy('date')->get(['date', 'period', 'subscribers'])
            ->map(fn ($s) => [$s->date, $s->period, $s->subscribers])->all();
        $this->assertSame([
            ['2025-03-01', 'm', 400],
            ['2026-06-06', 'w', 1500],
            ['2026-10-20', 'd', 5000],
        ], $rows);

        // Running it again changes nothing
        $this->artisan('telegram:compact')->assertSuccessful();
        $this->assertSame(3, TelegramSnapshot::count());
    }

    public function test_admin_can_refresh_a_channel_now_and_the_page_shows_the_numbers(): void
    {
        $this->fakeTelegram(['gold' => ['extra' => '7 000 subscribers']]);
        $provider = $this->channel('gold');

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        \Livewire\Livewire::test(\App\Filament\Resources\ProviderResource\Pages\EditProvider::class, ['record' => $provider->getRouteKey()])
            ->callAction('telegram_refresh')
            ->assertHasNoActionErrors();

        $this->assertSame(7000, $provider->fresh()->telegram_members);
        $this->get(route('providers.show', $provider))
            ->assertSee('7,000')
            ->assertSee(__('متوسط مشاهدات المنشور'));
    }
}
