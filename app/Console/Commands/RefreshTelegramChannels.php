<?php

namespace App\Console\Commands;

use App\Models\Provider;
use App\Services\TelegramChannel;
use App\Services\TelegramRefresh;
use Illuminate\Console\Command;

/**
 * Scheduled hourly (routes/console.php): each run refreshes a small batch of the channels that
 * are due, so every channel is read about once a day without a burst of requests to Telegram.
 */
class RefreshTelegramChannels extends Command
{
    protected $signature = 'telegram:refresh
        {--limit=20 : Channels per run}
        {--delay=3 : Seconds to wait between channels}
        {--provider= : Refresh this provider id now, due or not}';

    protected $description = 'Update Telegram subscribers and average views of the channels due a refresh';

    public function handle(): int
    {
        $providers = $this->option('provider')
            ? Provider::whereKey($this->option('provider'))->whereNotNull('telegram_url')->get()
            : TelegramRefresh::due()->limit((int) $this->option('limit'))->get();

        $failed = 0;
        foreach ($providers->values() as $i => $provider) {
            if ($i > 0) {
                sleep((int) $this->option('delay'));
            }

            $status = TelegramRefresh::refresh($provider);
            $failed += $status === TelegramChannel::CHANNEL ? 0 : 1;
            $this->line(sprintf('%-40s %s', mb_strimwidth($provider->name, 0, 38), $status));

            // Telegram stopped answering: leave the rest for the next run
            if ($status === TelegramChannel::UNREACHABLE && $i === 0) {
                $this->warn('Telegram is unreachable, stopping this run.');
                break;
            }
        }

        $this->info("Refreshed {$providers->count()} channels, {$failed} could not be read.");

        return self::SUCCESS;
    }
}
