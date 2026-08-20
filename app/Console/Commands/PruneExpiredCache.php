<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class PruneExpiredCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:prune-expired
                            {--batch=1000 : Maximum records to delete in each batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired database cache entries without removing active caches';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $store = config('cache.stores.database');

        if (! is_array($store)) {
            $this->error('The database cache store is not configured.');

            return self::FAILURE;
        }

        $batchSize = max(1, (int) $this->option('batch'));
        $connection = DB::connection($store['connection'] ?? null);
        $expiration = now()->getTimestamp();
        $cacheCount = $this->pruneTable($connection, (string) ($store['table'] ?? 'cache'), $expiration, $batchSize);
        $lockCount = $this->pruneTable($connection, (string) ($store['lock_table'] ?? 'cache_locks'), $expiration, $batchSize);

        $this->info("Pruned {$cacheCount} expired cache entries and {$lockCount} expired cache locks.");

        return self::SUCCESS;
    }

    private function pruneTable(
        ConnectionInterface $connection,
        string $table,
        int $expiration,
        int $batchSize,
    ): int {
        $deleted = 0;

        do {
            $keys = $connection->table($table)
                ->where('expiration', '<=', $expiration)
                ->limit($batchSize)
                ->pluck('key');

            if ($keys->isEmpty()) {
                break;
            }

            $deleted += $connection->table($table)->whereIn('key', $keys)->delete();
        } while ($keys->count() === $batchSize);

        return $deleted;
    }
}
