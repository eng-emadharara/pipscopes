<?php

namespace App\Console\Commands;

use App\Models\Provider;
use App\Models\TelegramSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Local testing only: fills the last 7 days of Telegram numbers for every active channel, so the
 * chart on channel pages can be tried before real history exists. Real rows are never touched,
 * and --remove deletes exactly the rows it added (their ids are kept in storage/app).
 */
class TelegramDemoData extends Command
{
    protected $signature = 'telegram:demo {--remove : Delete the sample rows added earlier}';

    protected $description = 'Add (or remove) sample Telegram numbers for the last 7 days, for trying the chart';

    protected const FILE = 'telegram-demo-snapshots.json';

    /** Day-by-day multipliers on a base [subscribers, views]: the stories the chart should tell */
    protected const SCENARIOS = [
        'growing' => [[1.00, 1.00], [1.01, 1.03], [1.02, 1.05], [1.03, 1.04], [1.05, 1.09], [1.06, 1.12], [1.08, 1.15]],
        'bought' => [[1.00, 1.00], [1.00, 0.98], [1.35, 1.01], [1.36, 0.97], [1.37, 0.99], [1.37, 0.96], [1.38, 0.95]],
        'declining' => [[1.00, 1.00], [0.99, 0.94], [0.99, 0.90], [0.98, 0.85], [0.97, 0.83], [0.97, 0.78], [0.96, 0.74]],
        'steady' => [[1.00, 1.00], [1.00, 1.02], [1.00, 0.99], [1.01, 1.01], [1.01, 1.03], [1.01, 1.00], [1.01, 1.02]],
    ];

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Sample data is for local testing only.');

            return self::FAILURE;
        }

        if ($this->option('remove')) {
            $ids = json_decode(Storage::get(self::FILE) ?? '[]', true) ?: [];
            $deleted = TelegramSnapshot::whereKey($ids)->delete();
            Storage::delete(self::FILE);
            $this->info("Removed {$deleted} sample rows.");

            return self::SUCCESS;
        }

        $ids = json_decode(Storage::get(self::FILE) ?? '[]', true) ?: [];
        $names = array_keys(self::SCENARIOS);

        foreach (Provider::active()->whereNotNull('telegram_url')->orderBy('id')->get()->values() as $n => $provider) {
            $scenario = $names[$n % count($names)];
            // Real numbers when there are some, otherwise a believable size; views 5–30% of subscribers
            $subs = $provider->telegram_members ?: random_int(3, 60) * 1000;
            $views = $provider->telegram_data['avg_views'] ?? (int) ($subs * random_int(5, 30) / 100);

            [$endS, $endV] = self::SCENARIOS[$scenario][6];

            foreach (self::SCENARIOS[$scenario] as $i => [$s, $v]) {
                $date = now()->subDays(6 - $i)->toDateString();
                if (TelegramSnapshot::where(['provider_id' => $provider->id, 'period' => 'd', 'date' => $date])->exists()) {
                    continue; // a real reading: keep it
                }

                $ids[] = TelegramSnapshot::create([
                    'provider_id' => $provider->id, 'date' => $date, 'period' => 'd',
                    // Scenarios end on today's real numbers when the channel has them
                    'subscribers' => (int) round($subs * $s / $endS),
                    'avg_views' => (int) round($views * $v / $endV),
                ])->id;
            }

            $this->line(sprintf('%-40s %s', mb_strimwidth($provider->name, 0, 38), $scenario));
        }

        Storage::put(self::FILE, json_encode(array_values(array_unique($ids))));
        $this->info('Done. Remove them with: php artisan telegram:demo --remove');

        return self::SUCCESS;
    }
}
