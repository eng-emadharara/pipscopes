<?php

namespace App\Console\Commands;

use App\Models\Provider;
use Illuminate\Console\Command;

class ReindexProviders extends Command
{
    protected $signature = 'providers:reindex';

    protected $description = 'Rebuild the search index of every provider';

    public function handle(): int
    {
        $count = 0;
        Provider::with(['categories', 'markets'])->chunkById(100, function ($providers) use (&$count) {
            $providers->each->refreshSearchIndex();
            $count += $providers->count();
        });

        $this->info("Reindexed {$count} providers.");

        return self::SUCCESS;
    }
}
