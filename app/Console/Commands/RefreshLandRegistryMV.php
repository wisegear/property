<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefreshLandRegistryMV extends Command
{
    protected $signature = 'landregistry:refresh-mv';

    protected $description = 'Refresh the Land Registry monthly postcode-prefix materialized view';

    public function handle(): int
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            $this->info('Skipping materialized view refresh on non-PostgreSQL connection.');

            return self::SUCCESS;
        }

        DB::statement('REFRESH MATERIALIZED VIEW land_registry_monthly_prefix_mv');

        $this->info('Refreshed land_registry_monthly_prefix_mv.');

        return self::SUCCESS;
    }
}
