<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CacheCleanExpired extends Command
{
    protected $signature = 'cache:clean-expired';

    protected $description = 'Delete the cache rows that have expired (the database cache only does that when the same key is asked for again)';

    public function handle(): int
    {
        $deleted = DB::table(config('cache.stores.database.table', 'cache'))->where('expiration', '<', now()->timestamp)->delete();

        $this->info("{$deleted} expired cache rows deleted.");

        return self::SUCCESS;
    }
}
