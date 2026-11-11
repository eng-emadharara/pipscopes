<?php

namespace App\Console\Commands;

use App\Services\TelegramPosts;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/** Scheduled daily (routes/console.php): yesterday's post counts per channel, then posts past 45 days go. */
class SummarizeTelegramPosts extends Command
{
    protected $signature = 'telegram:posts-daily {--date= : The day to summarize (Y-m-d), yesterday by default}';

    protected $description = 'Save each channel\'s daily posts / deleted / edited counts and drop posts older than 45 days';

    public function handle(): int
    {
        $day = $this->option('date') ? Carbon::parse($this->option('date')) : now()->subDay();
        $pruned = TelegramPosts::summarize($day);

        $this->info("Summarized {$day->toDateString()}; removed {$pruned} posts older than ".TelegramPosts::KEEP_DAYS.' days.');

        return self::SUCCESS;
    }
}
