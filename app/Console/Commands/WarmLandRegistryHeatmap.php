<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WarmLandRegistryHeatmap extends Command
{
    protected $signature = 'property:heatmap-warm {--force : Rebuild even if cache exists}';

    protected $description = 'Warm the Land Registry heatmap cache (England & Wales only).';

    public function handle(): int
    {
        $cacheKey = 'land_registry_heatmap:lsoa21:v2';

        if (Cache::has($cacheKey) && ! $this->option('force')) {
            $this->info('Heatmap cache already exists. Use --force to rebuild.');

            return self::SUCCESS;
        }

        $this->info('Building heatmap cache. This may take a while...');

        $grammar = DB::connection()->getQueryGrammar();
        $lrPostcodeColumn = $grammar->wrap('lr.Postcode');
        $ppdCategoryColumn = $grammar->wrap('lr.PPDCategoryType');
        $oPcdsColumn = $grammar->wrap('o.pcds');
        $oLsoa21Column = $grammar->wrap('o.lsoa21');
        $oLsoa11Column = $grammar->wrap('o.lsoa11');
        $oLatColumn = $grammar->wrap('o.lat');
        $oLongColumn = $grammar->wrap('o.long');
        $m11Lsoa21Column = $grammar->wrap('m11.LSOA21CD');
        $m11Lsoa11Column = $grammar->wrap('m11.LSOA11CD');
        $gLsoa21Column = $grammar->wrap('g.LSOA21CD');
        $gLatColumn = $grammar->wrap('g.LAT');
        $gLongColumn = $grammar->wrap('g.LONG');

        $hasLsoaGeo = Schema::hasTable('lsoa21_ruc_geo');
        $hasMapTable = Schema::hasTable('lsoa_2011_to_2021');
        $lsoa21Expr = $hasMapTable
            ? "COALESCE({$oLsoa21Column}, {$m11Lsoa21Column})"
            : $oLsoa21Column;

        $query = DB::table('land_registry as lr')
            ->join('onspd as o', DB::raw("REPLACE({$oPcdsColumn}, ' ', '')"), '=', DB::raw("REPLACE({$lrPostcodeColumn}, ' ', '')"))
            ->whereIn(DB::raw($ppdCategoryColumn), ['A', 'B'])
            ->whereNotNull(DB::raw($lsoa21Expr))
            ->where(function ($q) use ($lsoa21Expr) {
                $q->where(DB::raw($lsoa21Expr), 'like', 'E01%')
                    ->orWhere(DB::raw($lsoa21Expr), 'like', 'W01%');
            });

        if ($hasMapTable) {
            $query->leftJoin('lsoa_2011_to_2021 as m11', DB::raw($m11Lsoa11Column), '=', DB::raw($oLsoa11Column));
        }

        if ($hasLsoaGeo) {
            $query->join('lsoa21_ruc_geo as g', DB::raw($lsoa21Expr), '=', DB::raw($gLsoa21Column))
                ->groupBy(DB::raw($gLsoa21Column), DB::raw($gLatColumn), DB::raw($gLongColumn))
                ->selectRaw("{$gLatColumn} as lat")
                ->selectRaw("{$gLongColumn} as lng")
                ->selectRaw('COUNT(*) as count');
        } else {
            $query->whereNotNull(DB::raw($oLatColumn))
                ->whereNotNull(DB::raw($oLongColumn))
                ->groupBy(DB::raw($lsoa21Expr))
                ->selectRaw("AVG({$oLatColumn}) as lat")
                ->selectRaw("AVG({$oLongColumn}) as lng")
                ->selectRaw('COUNT(*) as count');
        }

        $points = $query->get();

        Cache::put($cacheKey, $points, now()->addDays(45));
        Cache::put('land_registry_heatmap:last_warm', now()->toIso8601String(), now()->addDays(45));

        $this->info('Heatmap cache written: '.$points->count().' points.');

        return self::SUCCESS;
    }
}
