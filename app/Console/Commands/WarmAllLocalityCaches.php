<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WarmAllLocalityCaches extends Command
{
    protected $signature = 'reports:warm-localities
                            {--ppd=A : PPDCategoryType filter (A or B)}
                            {--limit=0 : Limit number of localities to warm}
                            {--only=all : Only warm one group: all|price|sales|types}';

    protected $description = 'Precompute and cache locality-level datasets (price history, sales history, property types).';

    public function handle(): int
    {
        $ppd = strtoupper((string) $this->option('ppd')) === 'B' ? 'B' : 'A';
        $only = strtolower((string) $this->option('only'));
        $limit = (int) $this->option('limit');

        $ttl = now()->addDays(45);

        // Avoid Laravel keeping every result in memory
        DB::connection()->disableQueryLog();

        $grammar = DB::connection()->getQueryGrammar();
        $localityColumn = $grammar->wrap('Locality');
        $yearDateColumn = $grammar->wrap('YearDate');
        $priceColumn = $grammar->wrap('Price');
        $propertyTypeColumn = $grammar->wrap('PropertyType');
        $trimLocalityExpression = "TRIM({$localityColumn})";

        // Get distinct localities
        // IMPORTANT: TRIM() so cache keys match controller usage
        $localities = DB::table('land_registry')
            ->selectRaw("{$trimLocalityExpression} AS locality")
            ->whereNotNull('Locality')
            ->whereRaw("{$trimLocalityExpression} <> ''")
            ->distinct()
            ->orderByRaw($trimLocalityExpression)
            ->pluck('locality');

        if ($limit > 0) {
            $localities = $localities->take($limit);
        }

        $count = $localities->count();
        if ($count === 0) {
            $this->info('No localities found.');

            return self::SUCCESS;
        }

        $sections = match ($only) {
            'price', 'sales', 'types' => 1,
            default => 3,
        };

        $steps = $count * $sections;

        $this->info("Warming caches for {$count} localities (PPD={$ppd}, only={$only})...");
        $bar = $this->output->createProgressBar($steps);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %elapsed:6s% elapsed | %estimated:-6s% eta | %memory:6s% | %message%');
        $bar->start();

        // If limiting, keep the simpler per-locality approach (small runs only)
        if ($limit > 0) {
            foreach ($localities as $locality) {
                $locality = trim((string) $locality);

                // ---- PRICE HISTORY (v2 + v3) ----
                if ($only === 'all' || $only === 'price') {
                    // v2: all property types for this locality
                    $price = DB::table('land_registry')
                        ->selectRaw("{$yearDateColumn} as year")
                        ->selectRaw("ROUND(AVG({$priceColumn})) as avg_price")
                        ->where('PPDCategoryType', $ppd)
                        ->whereRaw("{$trimLocalityExpression} = ?", [$locality])
                        ->groupBy('YearDate')
                        ->orderBy('YearDate')
                        ->get();

                    Cache::put('locality:priceHistory:v2:cat'.$ppd.':'.$locality, $price, $ttl);
                    $bar->setMessage('Price: '.$locality);
                    $bar->advance();

                    // v3: per-property-type for this locality
                    $priceByType = DB::table('land_registry')
                        ->selectRaw("{$propertyTypeColumn} as property_type")
                        ->selectRaw("{$yearDateColumn} as year")
                        ->selectRaw("ROUND(AVG({$priceColumn})) as avg_price")
                        ->where('PPDCategoryType', $ppd)
                        ->whereRaw("{$trimLocalityExpression} = ?", [$locality])
                        ->groupBy(DB::raw($propertyTypeColumn), 'YearDate')
                        ->orderByRaw($propertyTypeColumn)
                        ->orderBy('YearDate')
                        ->get()
                        ->groupBy('property_type');

                    foreach ($priceByType as $type => $series) {
                        Cache::put(
                            'locality:priceHistory:v3:cat'.$ppd.':'.$locality.':type:'.$type,
                            $series->values(),
                            $ttl
                        );
                    }
                }

                // ---- SALES HISTORY (v2 + v3) ----
                if ($only === 'all' || $only === 'sales') {
                    // v2: all property types for this locality
                    $sales = DB::table('land_registry')
                        ->selectRaw("{$yearDateColumn} as year")
                        ->selectRaw('COUNT(*) as total_sales')
                        ->where('PPDCategoryType', $ppd)
                        ->whereRaw("{$trimLocalityExpression} = ?", [$locality])
                        ->groupBy('YearDate')
                        ->orderBy('YearDate')
                        ->get();

                    Cache::put('locality:salesHistory:v2:cat'.$ppd.':'.$locality, $sales, $ttl);
                    $bar->setMessage('Sales: '.$locality);
                    $bar->advance();

                    // v3: per-property-type for this locality
                    $salesByType = DB::table('land_registry')
                        ->selectRaw("{$propertyTypeColumn} as property_type")
                        ->selectRaw("{$yearDateColumn} as year")
                        ->selectRaw('COUNT(*) as total_sales')
                        ->where('PPDCategoryType', $ppd)
                        ->whereRaw("{$trimLocalityExpression} = ?", [$locality])
                        ->groupBy(DB::raw($propertyTypeColumn), 'YearDate')
                        ->orderByRaw($propertyTypeColumn)
                        ->orderBy('YearDate')
                        ->get()
                        ->groupBy('property_type');

                    foreach ($salesByType as $type => $series) {
                        Cache::put(
                            'locality:salesHistory:v3:cat'.$ppd.':'.$locality.':type:'.$type,
                            $series->values(),
                            $ttl
                        );
                    }
                }

                // ---- PROPERTY TYPES (v2) ----
                if ($only === 'all' || $only === 'types') {
                    $map = [
                        'D' => 'Detached',
                        'S' => 'Semi-Detached',
                        'T' => 'Terraced',
                        'F' => 'Flat',
                        'O' => 'Other',
                    ];

                    $rows = DB::table('land_registry')
                        ->selectRaw("{$propertyTypeColumn} as property_type")
                        ->selectRaw('COUNT(*) as property_count')
                        ->where('PPDCategoryType', $ppd)
                        ->whereRaw("{$trimLocalityExpression} = ?", [$locality])
                        ->groupBy(DB::raw($propertyTypeColumn))
                        ->get();

                    $mapped = $rows->map(function ($row) use ($map) {
                        return [
                            'label' => $map[$row->property_type] ?? $row->property_type,
                            'value' => (int) $row->property_count,
                        ];
                    });

                    Cache::put('locality:types:v2:cat'.$ppd.':'.$locality, $mapped, $ttl);
                    $bar->setMessage('Types: '.$locality);
                    $bar->advance();
                }
            }
        } else {
            // Full run: stream grouped rows once per section (fast + constant-memory)

            // ---- PRICE HISTORY (v2 + v3) ----
            if ($only === 'all' || $only === 'price') {
                // v2: locality price history (flush per-locality)
                $cursor = DB::table('land_registry')
                    ->selectRaw("{$trimLocalityExpression} AS locality, {$yearDateColumn} AS year, ROUND(AVG({$priceColumn})) AS avg_price")
                    ->where('PPDCategoryType', $ppd)
                    ->whereNotNull('Locality')
                    ->whereRaw("{$trimLocalityExpression} <> ''")
                    ->groupBy(DB::raw($trimLocalityExpression), 'YearDate')
                    ->orderByRaw($trimLocalityExpression)
                    ->orderBy('YearDate')
                    ->cursor();

                $currentLocality = null;
                $bucket = [];

                foreach ($cursor as $row) {
                    $loc = trim((string) $row->locality);

                    if ($currentLocality === null) {
                        $currentLocality = $loc;
                    }

                    if ($loc !== $currentLocality) {
                        Cache::put('locality:priceHistory:v2:cat'.$ppd.':'.$currentLocality, collect($bucket), $ttl);
                        $bar->setMessage('Price: '.$currentLocality);
                        $bar->advance();

                        $currentLocality = $loc;
                        $bucket = [];
                    }

                    $bucket[] = $row;
                }

                if ($currentLocality !== null) {
                    Cache::put('locality:priceHistory:v2:cat'.$ppd.':'.$currentLocality, collect($bucket), $ttl);
                    $bar->setMessage('Price: '.$currentLocality);
                    $bar->advance();
                }

                // v3: per-property-type locality price history (flush per (locality,type))
                $cursorByType = DB::table('land_registry')
                    ->selectRaw("{$trimLocalityExpression} AS locality, {$propertyTypeColumn} as property_type, {$yearDateColumn} AS year, ROUND(AVG({$priceColumn})) AS avg_price")
                    ->where('PPDCategoryType', $ppd)
                    ->whereNotNull('Locality')
                    ->whereRaw("{$trimLocalityExpression} <> ''")
                    ->groupBy(DB::raw($trimLocalityExpression), DB::raw($propertyTypeColumn), 'YearDate')
                    ->orderByRaw($trimLocalityExpression)
                    ->orderByRaw($propertyTypeColumn)
                    ->orderBy('YearDate')
                    ->cursor();

                $currentLocality = null;
                $currentType = null;
                $bucket = [];

                foreach ($cursorByType as $row) {
                    $loc = trim((string) $row->locality);
                    $type = (string) $row->property_type;

                    if ($currentLocality === null) {
                        $currentLocality = $loc;
                        $currentType = $type;
                    }

                    if ($loc !== $currentLocality || $type !== $currentType) {
                        if ($currentLocality !== null && $currentType !== null) {
                            Cache::put(
                                'locality:priceHistory:v3:cat'.$ppd.':'.$currentLocality.':type:'.$currentType,
                                collect($bucket)->values(),
                                $ttl
                            );
                        }

                        $currentLocality = $loc;
                        $currentType = $type;
                        $bucket = [];
                    }

                    $bucket[] = $row;
                }

                if ($currentLocality !== null && $currentType !== null) {
                    Cache::put(
                        'locality:priceHistory:v3:cat'.$ppd.':'.$currentLocality.':type:'.$currentType,
                        collect($bucket)->values(),
                        $ttl
                    );
                }
            }

            // ---- SALES HISTORY (v2 + v3) ----
            if ($only === 'all' || $only === 'sales') {
                // v2: locality sales history (flush per-locality)
                $cursor = DB::table('land_registry')
                    ->selectRaw("{$trimLocalityExpression} AS locality, {$yearDateColumn} AS year, COUNT(*) AS total_sales")
                    ->where('PPDCategoryType', $ppd)
                    ->whereNotNull('Locality')
                    ->whereRaw("{$trimLocalityExpression} <> ''")
                    ->groupBy(DB::raw($trimLocalityExpression), 'YearDate')
                    ->orderByRaw($trimLocalityExpression)
                    ->orderBy('YearDate')
                    ->cursor();

                $currentLocality = null;
                $bucket = [];

                foreach ($cursor as $row) {
                    $loc = trim((string) $row->locality);

                    if ($currentLocality === null) {
                        $currentLocality = $loc;
                    }

                    if ($loc !== $currentLocality) {
                        Cache::put('locality:salesHistory:v2:cat'.$ppd.':'.$currentLocality, collect($bucket), $ttl);
                        $bar->setMessage('Sales: '.$currentLocality);
                        $bar->advance();

                        $currentLocality = $loc;
                        $bucket = [];
                    }

                    $bucket[] = $row;
                }

                if ($currentLocality !== null) {
                    Cache::put('locality:salesHistory:v2:cat'.$ppd.':'.$currentLocality, collect($bucket), $ttl);
                    $bar->setMessage('Sales: '.$currentLocality);
                    $bar->advance();
                }

                // v3: per-property-type locality sales history (flush per (locality,type))
                $cursorByType = DB::table('land_registry')
                    ->selectRaw("{$trimLocalityExpression} AS locality, {$propertyTypeColumn} as property_type, {$yearDateColumn} AS year, COUNT(*) AS total_sales")
                    ->where('PPDCategoryType', $ppd)
                    ->whereNotNull('Locality')
                    ->whereRaw("{$trimLocalityExpression} <> ''")
                    ->groupBy(DB::raw($trimLocalityExpression), DB::raw($propertyTypeColumn), 'YearDate')
                    ->orderByRaw($trimLocalityExpression)
                    ->orderByRaw($propertyTypeColumn)
                    ->orderBy('YearDate')
                    ->cursor();

                $currentLocality = null;
                $currentType = null;
                $bucket = [];

                foreach ($cursorByType as $row) {
                    $loc = trim((string) $row->locality);
                    $type = (string) $row->property_type;

                    if ($currentLocality === null) {
                        $currentLocality = $loc;
                        $currentType = $type;
                    }

                    if ($loc !== $currentLocality || $type !== $currentType) {
                        if ($currentLocality !== null && $currentType !== null) {
                            Cache::put(
                                'locality:salesHistory:v3:cat'.$ppd.':'.$currentLocality.':type:'.$currentType,
                                collect($bucket)->values(),
                                $ttl
                            );
                        }

                        $currentLocality = $loc;
                        $currentType = $type;
                        $bucket = [];
                    }

                    $bucket[] = $row;
                }

                if ($currentLocality !== null && $currentType !== null) {
                    Cache::put(
                        'locality:salesHistory:v3:cat'.$ppd.':'.$currentLocality.':type:'.$currentType,
                        collect($bucket)->values(),
                        $ttl
                    );
                }
            }

            // ---- PROPERTY TYPES (v2) ----
            if ($only === 'all' || $only === 'types') {
                $map = [
                    'D' => 'Detached',
                    'S' => 'Semi-Detached',
                    'T' => 'Terraced',
                    'F' => 'Flat',
                    'O' => 'Other',
                ];

                $cursor = DB::table('land_registry')
                    ->selectRaw("{$trimLocalityExpression} AS locality, {$propertyTypeColumn} as property_type, COUNT(*) AS property_count")
                    ->where('PPDCategoryType', $ppd)
                    ->whereNotNull('Locality')
                    ->whereRaw("{$trimLocalityExpression} <> ''")
                    ->groupBy(DB::raw($trimLocalityExpression), DB::raw($propertyTypeColumn))
                    ->orderByRaw($trimLocalityExpression)
                    ->orderByRaw($propertyTypeColumn)
                    ->cursor();

                $currentLocality = null;
                $bucket = [];

                foreach ($cursor as $row) {
                    $loc = trim((string) $row->locality);

                    if ($currentLocality === null) {
                        $currentLocality = $loc;
                    }

                    if ($loc !== $currentLocality) {
                        $mapped = collect($bucket)->map(function ($r) use ($map) {
                            return [
                                'label' => $map[$r->property_type] ?? $r->property_type,
                                'value' => (int) $r->property_count,
                            ];
                        });

                        Cache::put('locality:types:v2:cat'.$ppd.':'.$currentLocality, $mapped, $ttl);
                        $bar->setMessage('Types: '.$currentLocality);
                        $bar->advance();

                        $currentLocality = $loc;
                        $bucket = [];
                    }

                    $bucket[] = $row;
                }

                if ($currentLocality !== null) {
                    $mapped = collect($bucket)->map(function ($r) use ($map) {
                        return [
                            'label' => $map[$r->property_type] ?? $r->property_type,
                            'value' => (int) $r->property_count,
                        ];
                    });

                    Cache::put('locality:types:v2:cat'.$ppd.':'.$currentLocality, $mapped, $ttl);
                    $bar->setMessage('Types: '.$currentLocality);
                    $bar->advance();
                }
            }
        }

        Cache::put('locality:v2:last_warm', now()->toIso8601String(), $ttl);
        $bar->finish();
        $this->newLine(2);
        $this->info('Locality cache warm complete.');

        return self::SUCCESS;
    }
}
