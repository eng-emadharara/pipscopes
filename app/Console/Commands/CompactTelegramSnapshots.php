<?php

namespace App\Console\Commands;

use App\Models\TelegramSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the Telegram history small: daily rows older than 90 days become one row per week,
 * weekly rows older than a year become one row per month (the averages of what they replace).
 */
class CompactTelegramSnapshots extends Command
{
    protected $signature = 'telegram:compact';

    protected $description = 'Merge old daily Telegram snapshots into weekly and monthly averages';

    public const DAILY_DAYS = 90;

    public const WEEKLY_DAYS = 365;

    public function handle(): int
    {
        $weeks = $this->merge(TelegramSnapshot::DAY, TelegramSnapshot::WEEK, now()->subDays(self::DAILY_DAYS),
            fn (Carbon $d) => $d->startOfWeek(Carbon::SATURDAY));
        $months = $this->merge(TelegramSnapshot::WEEK, TelegramSnapshot::MONTH, now()->subDays(self::WEEKLY_DAYS),
            fn (Carbon $d) => $d->startOfMonth());

        $this->info("Merged {$weeks} daily rows into weeks and {$months} weekly rows into months.");

        return self::SUCCESS;
    }

    /** @return int rows merged */
    protected function merge(string $from, string $to, Carbon $before, callable $bucket): int
    {
        // Only whole buckets that ended before the cut-off, so a bucket is never merged twice
        $cutoff = $bucket($before->copy())->toDateString();
        $merged = 0;

        TelegramSnapshot::where('period', $from)->where('date', '<', $cutoff)
            ->orderBy('provider_id')->orderBy('date')
            ->get()
            ->groupBy(fn (TelegramSnapshot $s) => $s->provider_id.'|'.$bucket(Carbon::parse($s->date))->toDateString())
            ->each(function ($rows, string $key) use ($to, &$merged) {
                [$providerId, $date] = explode('|', $key);
                $avg = fn (string $col) => ($values = $rows->pluck($col)->filter(fn ($v) => $v !== null))->isEmpty()
                    ? null : (int) round($values->avg());

                DB::transaction(function () use ($rows, $providerId, $date, $to, $avg) {
                    TelegramSnapshot::updateOrCreate(
                        ['provider_id' => $providerId, 'period' => $to, 'date' => $date],
                        ['subscribers' => $avg('subscribers'), 'avg_views' => $avg('avg_views')],
                    );
                    TelegramSnapshot::whereKey($rows->modelKeys())->delete();
                });
                $merged += $rows->count();
            });

        return $merged;
    }
}
