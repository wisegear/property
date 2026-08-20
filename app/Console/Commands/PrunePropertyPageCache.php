<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PrunePropertyPageCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:prune-property-pages
                            {--batch=1000 : Maximum records to delete in each batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete obsolete per-property cache entries without affecting shared caches';

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
        $table = (string) ($store['table'] ?? 'cache');
        $prefix = (string) config('cache.prefix');
        $deleted = 0;
        $lastKey = null;

        do {
            $keys = $this->obsoletePropertyKeys($connection, $table, $prefix)
                ->when($lastKey !== null, fn ($query) => $query->where('key', '>', $lastKey))
                ->orderBy('key')
                ->limit($batchSize)
                ->pluck('key');

            if ($keys->isEmpty()) {
                break;
            }

            $lastKey = $keys->last();
            $deleted += $connection->table($table)->whereIn('key', $keys)->delete();
        } while ($keys->count() === $batchSize);

        $this->info("Pruned {$deleted} obsolete per-property cache entries.");

        return self::SUCCESS;
    }

    private function obsoletePropertyKeys(
        ConnectionInterface $connection,
        string $table,
        string $prefix,
    ): Builder {
        return $connection->table($table)
            ->where('key', 'like', $prefix.'property:%')
            ->where(function ($query): void {
                $query->where('key', 'like', '%:records:v2:catAB')
                    ->orWhere('key', 'like', '%:priceHistory:v4:catA')
                    ->orWhere('key', 'like', '%:council-tax-estimate:v2');
            });
    }
}
