<?php

namespace App\Http\Controllers;

use App\Models\Crime;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PropertyStreetController extends Controller
{
    private const CACHE_TTL = 60 * 60 * 24 * 45;

    private const CACHE_VERSION = 'v4';

    private const MIN_RELIABLE_SALES = 5;

    private const NEARBY_STREETS_LIMIT = 10;

    private const ONSPD_TABLE = 'onspd_v2';

    /**
     * @var array<string, array<string, string>>
     */
    private array $streetSlugMaps = [];

    /**
     * @var array<string, array<int, array{name:string,slug:string,outcode:string,sales_count:int,url:string}>>
     */
    private array $nearbyStreetCatalogs = [];

    private ?bool $hasCrimeTable = null;

    private ?bool $hasOnspdTable = null;

    private bool $warmProfilingEnabled = false;

    private const PROFILE_SLOW_MS = 100.0;

    /**
     * @var null|callable(string):void
     */
    private $warmProfilingLogger = null;

    /**
     * @var array<string, array{count:int,total_ms:float,max_ms:float}>
     */
    private array $warmProfilingStats = [];

    public static function cacheKey(string $streetSlug, string $outcode): string
    {
        return sprintf('property:street:%s:%s:%s', self::CACHE_VERSION, $streetSlug, Str::lower($outcode));
    }

    public static function outcodeCrimeCacheKey(string $outcode): string
    {
        return sprintf('property:street:crime:outcode:%s', Str::lower($outcode));
    }

    public static function outcodeCrimePointCacheKey(string $outcode): string
    {
        return sprintf('property:street:crime:outcode-point:%s', Str::lower($outcode));
    }

    public static function streetPath(string $outcode, string $streetSlug): string
    {
        return '/property/street/'.Str::lower($outcode).'/'.$streetSlug;
    }

    public function legacy(Request $request, string $street): RedirectResponse
    {
        $outcode = $this->normalizeOutcode((string) $request->query('outcode', ''));

        if ($outcode === null) {
            abort(404);
        }

        return redirect()->to(self::streetPath($outcode, $street), 301);
    }

    public function show(Request $request, string $outcode, string $street): View|RedirectResponse
    {
        $normalizedOutcode = $this->normalizeOutcode($outcode);

        if ($normalizedOutcode === null) {
            abort(404);
        }

        $canonicalOutcode = Str::lower($normalizedOutcode);

        if ($outcode !== $canonicalOutcode) {
            return redirect()->to(self::streetPath($normalizedOutcode, $street), 301);
        }

        $payload = $this->warmStreetCache($street, $outcode);

        return view('property.street', [
            'streetName' => $payload['street_name'],
            'streetSlug' => $street,
            'outcode' => $payload['outcode'],
            'summary' => $payload['summary'],
            'glanceMetrics' => $payload['glance_metrics'],
            'canonicalUrl' => $payload['canonical_url'],
            'metaTitle' => $payload['meta_title'],
            'metaDescription' => $payload['meta_description'],
            'yearlyMedianPrice' => $payload['yearly_median_price'],
            'yearlySalesCount' => $payload['yearly_sales_count'],
            'outcodeComparison' => $payload['outcode_comparison'],
            'nearbyStreets' => $payload['nearby_streets'],
            'faqItems' => $payload['faq_items'],
            'pageLastModified' => $payload['page_last_modified'],
            'topSales' => $payload['top_sales'],
            'sales' => $this->paginateSales($payload['sales'], $request),
            'limitedData' => $payload['limited_data'],
            'crimeData' => $payload['crime_data'],
            'crimeTrend' => $payload['crime_trend'],
            'crimeSummary' => $payload['crime_summary'],
            'crimeDirection' => $payload['crime_direction'],
            'crimeTrendLabels' => $payload['crime_trend_labels'],
            'crimeTrendValues' => $payload['crime_trend_values'],
            'totalChange' => $payload['crime_total_change'],
            'topIncrease' => $payload['crime_top_increase'],
            'topDecrease' => $payload['crime_top_decrease'],
            'depr' => $payload['deprivation'] ?? null,
            'deprMsg' => $payload['deprivation_message'] ?? null,
            'lsoaLink' => $payload['deprivation_link'] ?? null,
        ]);
    }

    public function warmStreetCache(string $streetSlug, string $outcode): array
    {
        $normalizedOutcode = $this->normalizeOutcode($outcode);

        if ($normalizedOutcode === null) {
            abort(404);
        }

        $cacheKey = self::cacheKey($streetSlug, $normalizedOutcode);

        if (! $this->warmProfilingEnabled) {
            return Cache::remember(
                $cacheKey,
                self::CACHE_TTL,
                fn (): array => $this->buildPayload($streetSlug, $normalizedOutcode)
            );
        }

        $rememberStartedAt = microtime(true);
        $buildElapsedMs = null;
        $payload = Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            function () use ($streetSlug, $normalizedOutcode, &$buildElapsedMs): array {
                $buildStartedAt = microtime(true);
                $payload = $this->buildPayload($streetSlug, $normalizedOutcode);
                $buildElapsedMs = $this->elapsedMs($buildStartedAt);

                return $payload;
            }
        );

        $rememberElapsedMs = $this->elapsedMs($rememberStartedAt);
        $this->recordSectionTiming('cache remember total', $rememberElapsedMs);
        $this->logWarmProfile(sprintf(
            'street=%s outcode=%s section="%s" elapsed_ms=%.2f',
            $streetSlug,
            $normalizedOutcode,
            'cache remember total',
            $rememberElapsedMs
        ));

        if ($buildElapsedMs !== null) {
            $cacheWriteElapsedMs = max(0.0, $rememberElapsedMs - $buildElapsedMs);
            $this->recordSectionTiming('cache write', $cacheWriteElapsedMs);
            $this->logWarmProfile(sprintf(
                'street=%s outcode=%s section="%s" elapsed_ms=%.2f',
                $streetSlug,
                $normalizedOutcode,
                'cache write',
                $cacheWriteElapsedMs
            ));
        }

        return $payload;
    }

    public function enableWarmProfiling(?callable $logger = null): void
    {
        $this->warmProfilingEnabled = true;
        $this->warmProfilingLogger = $logger;
        $this->warmProfilingStats = [];
    }

    /**
     * @return array<int, array{section:string,count:int,total_ms:float,max_ms:float,avg_ms:float}>
     */
    public function warmProfilingSummary(): array
    {
        return collect($this->warmProfilingStats)
            ->map(function (array $stats, string $section): array {
                return [
                    'section' => $section,
                    'count' => $stats['count'],
                    'total_ms' => round($stats['total_ms'], 2),
                    'max_ms' => round($stats['max_ms'], 2),
                    'avg_ms' => round($stats['total_ms'] / max(1, $stats['count']), 2),
                ];
            })
            ->sortByDesc('total_ms')
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     street_name:string,
     *     outcode:string,
     *     summary:array<string, int|string|null>,
     *     glance_metrics:array<int, array{label:string, value:string}>,
     *     canonical_url:string,
     *     meta_title:string,
     *     meta_description:string,
     *     yearly_median_price:array<int, array{year:int, value:int}>,
     *     yearly_sales_count:array<int, array{year:int, value:int}>,
     *     outcode_comparison:array<string, mixed>,
     *     nearby_streets:array<int, array<string, mixed>>,
     *     faq_items:array<int, array{question:string, answer:string}>,
     *     page_last_modified:?string,
     *     top_sales:array<int, array<string, mixed>>,
     *     sales:array<int, array<string, mixed>>,
     *     limited_data:bool,
     *     crime_data:array<int, array<string, mixed>>,
     *     crime_trend:array<int, array<string, mixed>>,
     *     crime_summary:?string,
     *     crime_direction:string,
     *     crime_trend_labels:array<int, string>,
     *     crime_trend_values:array<int, int>,
     *     crime_total_change:float,
     *     crime_top_increase:?array<string, mixed>,
     *     crime_top_decrease:?array<string, mixed>,
     *     deprivation:?array<string, mixed>,
     *     deprivation_message:?string,
     *     deprivation_link:?string
     * }
     */
    private function buildPayload(string $streetSlug, string $outcode): array
    {
        $payloadStartedAt = microtime(true);

        $streetName = $this->resolveStreetName($streetSlug, $outcode);

        if ($streetName === null) {
            abort(404);
        }

        $recordsQuery = DB::table('land_registry')
            ->select([
                'Date',
                'Price',
                'PAON',
                'SAON',
                'Street',
                'Postcode',
                'PropertyType',
                'Duration',
                'NewBuild',
            ])
            ->whereRaw('"PPDCategoryType" = ?', ['A'])
            ->whereRaw('TRIM("Street") = ?', [$streetName])
            ->whereRaw($this->outcodeExpression().' = ?', [$outcode])
            ->orderByDesc('Date')
            ->orderByDesc('Price');

        $records = $this->profileQuery(
            'street sales records query',
            $recordsQuery,
            fn (QueryBuilder $query): array => $query->get()
                ->map(function (object $row): array {
                    $date = $row->Date !== null ? Carbon::parse((string) $row->Date) : null;
                    $price = $row->Price !== null ? (int) $row->Price : null;
                    $paon = trim((string) ($row->PAON ?? ''));
                    $saon = trim((string) ($row->SAON ?? ''));

                    return [
                        'date' => $date?->toDateString(),
                        'date_label' => $date?->format('d M Y'),
                        'price' => $price,
                        'price_label' => $price !== null ? '£'.number_format($price) : null,
                        'paon' => $paon,
                        'saon' => $saon,
                        'street' => trim((string) ($row->Street ?? '')),
                        'postcode' => trim((string) ($row->Postcode ?? '')),
                        'property_type' => $this->propertyTypeLabel($row->PropertyType !== null ? (string) $row->PropertyType : null),
                        'tenure' => $this->tenureLabel($row->Duration !== null ? (string) $row->Duration : null),
                        'build_status' => $this->buildStatusLabel($row->NewBuild !== null ? (string) $row->NewBuild : null),
                        'address' => $this->formatAddress($paon, $saon),
                        'property_slug' => $this->buildPropertySlug(
                            trim((string) ($row->Postcode ?? '')),
                            $paon,
                            trim((string) ($row->Street ?? '')),
                            $saon !== '' ? $saon : null
                        ),
                    ];
                })
                ->values()
                ->all(),
            [
                'street' => $streetSlug,
                'outcode' => $outcode,
            ]
        );

        if ($records === []) {
            abort(404);
        }

        $prices = $this->profileSection('price extraction', function () use ($records) {
            return collect($records)
                ->pluck('price')
                ->filter(fn ($price): bool => is_int($price) && $price > 0)
                ->sort()
                ->values();
        });

        $summary = $this->profileSection('summary', function () use ($records, $prices): array {
            $latestSaleDate = collect($records)
                ->pluck('date')
                ->filter()
                ->first();

            return [
                'total_sales' => count($records),
                'median_sale_price' => $this->medianValue($prices->all()),
                'average_sale_price' => $prices->isNotEmpty() ? (int) round($prices->avg()) : null,
                'latest_sale_date' => $latestSaleDate !== null ? Carbon::parse($latestSaleDate)->format('d M Y') : null,
                'latest_sale_date_iso' => $latestSaleDate,
                'highest_sale' => $prices->isNotEmpty() ? (int) $prices->last() : null,
                'most_common_property_type' => $this->profileSection('property type distribution', fn () => $this->mostCommonPropertyType($records)),
            ];
        });

        [$yearlyMedianPrice, $yearlySalesCount] = $this->profileSection('yearly chart aggregation', function () use ($records): array {
            $yearly = collect($records)
                ->filter(fn (array $record): bool => $record['date'] !== null)
                ->groupBy(fn (array $record): int => Carbon::parse((string) $record['date'])->year)
                ->sortKeys();

            $yearlyMedianPrice = $yearly
                ->map(function ($items, int $year): array {
                    $prices = collect($items)
                        ->pluck('price')
                        ->filter(fn ($price): bool => is_int($price) && $price > 0)
                        ->sort()
                        ->values()
                        ->all();

                    return [
                        'year' => $year,
                        'value' => $this->medianValue($prices) ?? 0,
                    ];
                })
                ->values()
                ->all();

            $yearlySalesCount = $yearly
                ->map(fn ($items, int $year): array => [
                    'year' => $year,
                    'value' => count($items),
                ])
                ->values()
                ->all();

            return [$yearlyMedianPrice, $yearlySalesCount];
        });

        $topSales = $this->profileSection('top sales aggregation', function () use ($records): array {
            return collect($records)
                ->filter(fn (array $record): bool => $record['price'] !== null)
                ->sortByDesc('price')
                ->take(10)
                ->values()
                ->all();
        });

        $centroid = $this->hasOnspdTable() ? $this->streetCentroid($records) : null;
        $crimePayload = $this->buildOutcodeCrimePayload($outcode, $centroid['lat'] ?? null, $centroid['lng'] ?? null);
        $deprivationPayload = $this->buildDeprivationPayload($centroid);
        $outcodeComparison = $this->buildOutcodeComparison($streetName, $outcode, $summary);
        $nearbyStreets = $this->buildNearbyStreets($streetName, $outcode);
        $glanceMetrics = $this->profileSection('glance metrics', fn () => $this->buildGlanceMetrics($summary, $crimePayload, $deprivationPayload));
        $metaDescription = $this->profileSection('meta description', fn () => $this->buildMetaDescription($streetName, $outcode, $summary));
        $faqItems = $this->profileSection('faq items', fn () => $this->buildFaqItems($streetName, $outcode, $summary, $outcodeComparison));

        $payload = [
            'street_name' => $streetName,
            'outcode' => $outcode,
            'summary' => $summary,
            'glance_metrics' => $glanceMetrics,
            'canonical_url' => url(self::streetPath($outcode, $streetSlug)),
            'meta_title' => sprintf('%s %s Sold Prices & Property Data', $this->titleCaseStreetName($streetName), $outcode),
            'meta_description' => $metaDescription,
            'yearly_median_price' => $yearlyMedianPrice,
            'yearly_sales_count' => $yearlySalesCount,
            'outcode_comparison' => $outcodeComparison,
            'nearby_streets' => $nearbyStreets,
            'faq_items' => $faqItems,
            'page_last_modified' => $summary['latest_sale_date_iso'],
            'top_sales' => $topSales,
            'sales' => $records,
            'limited_data' => count($records) < self::MIN_RELIABLE_SALES,
            'crime_data' => $crimePayload['crime_data'],
            'crime_trend' => $crimePayload['crime_trend'],
            'crime_summary' => $crimePayload['crime_summary'],
            'crime_direction' => $crimePayload['crime_direction'],
            'crime_trend_labels' => $crimePayload['crime_trend_labels'],
            'crime_trend_values' => $crimePayload['crime_trend_values'],
            'crime_total_change' => $crimePayload['crime_total_change'],
            'crime_top_increase' => $crimePayload['crime_top_increase'],
            'crime_top_decrease' => $crimePayload['crime_top_decrease'],
            'deprivation' => $deprivationPayload['depr'],
            'deprivation_message' => $deprivationPayload['deprMsg'],
            'deprivation_link' => $deprivationPayload['lsoaLink'],
        ];

        $payloadElapsedMs = $this->elapsedMs($payloadStartedAt);
        $this->recordSectionTiming('total payload build', $payloadElapsedMs);
        $this->logWarmProfile(sprintf(
            'street=%s outcode=%s section="%s" elapsed_ms=%.2f',
            $streetSlug,
            $outcode,
            'total payload build',
            $payloadElapsedMs
        ));

        return $payload;
    }

    /**
     * @return array{depr:?array<string, mixed>,deprMsg:?string,lsoaLink:?string}
     */
    private function buildDeprivationPayload(?array $centroid): array
    {
        $startedAt = microtime(true);
        $emptyPayload = [
            'depr' => null,
            'deprMsg' => null,
            'lsoaLink' => null,
        ];

        if (! $this->hasOnspdTable()) {
            return [
                ...$emptyPayload,
                'deprMsg' => 'Unable to resolve this street to ONSPD.',
            ];
        }

        if ($centroid === null) {
            return [
                ...$emptyPayload,
                'deprMsg' => 'Unable to derive a street centroid for deprivation context.',
            ];
        }

        $resolveImdForLsoa = function (string $lsoa) {
            $tables = ['imd2025', 'wimd2019'];
            $keyCols = ['LSOA_Code_2021', 'LSOA_code', 'lsoa21cd', 'lsoa21', 'LSOA21CD', 'lsoa11cd', 'lsoa11', 'LSOA11CD', 'lsoa_code', 'lsoa', 'LSOA'];
            $rankCols = ['Index_of_Multiple_Deprivation_Rank', 'rank', 'Rank', 'imd_rank', 'IMD_RANK', 'wimd_rank', 'WIMD_RANK', 'Overall_Rank', 'overall_rank', 'IMD_Rank', 'IMD_rank', 'WIMD_Rank', 'WIMD_rank', 'IMD_Rank_2025', 'IMD_rank_2025', 'Rank_2025', 'WIMD_Rank_2019', 'WIMD_rank_2019', 'Rank_2019'];
            $decileCols = ['Index_of_Multiple_Deprivation_Decile', 'decile', 'Decile', 'imd_decile', 'IMD_DECILE', 'wimd_decile', 'WIMD_DECILE', 'Overall_Decile', 'overall_decile', 'IMD_Decile', 'IMD_decile', 'WIMD_Decile', 'WIMD_decile', 'IMD_Decile_2025', 'IMD_decile_2025', 'Decile_2025', 'WIMD_Decile_2019', 'WIMD_decile_2019', 'Decile_2019'];
            $nameCols = ['LSOA_Name_2021', 'name', 'lsoa_name', 'lsoa21nm', 'LSOA21NM', 'lsoa11nm', 'LSOA11NM', 'LSOA_Name', 'LSOA_name'];

            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $cols = Cache::remember('depr:cols:'.$table, now()->addDays(90), function () use ($table) {
                    try {
                        return array_map(fn ($column) => (string) $column, Schema::getColumnListing($table));
                    } catch (\Throwable $throwable) {
                        return [];
                    }
                });

                $pickCol = function (array $preferred) use ($cols) {
                    if ($cols === []) {
                        return null;
                    }

                    foreach ($preferred as $column) {
                        if (in_array($column, $cols, true)) {
                            return $column;
                        }
                    }

                    $lowercaseColumns = array_map('strtolower', $cols);
                    foreach ($preferred as $column) {
                        $index = array_search(strtolower($column), $lowercaseColumns, true);
                        if ($index !== false) {
                            return $cols[$index];
                        }
                    }

                    foreach ($preferred as $column) {
                        $needle = strtolower($column);
                        foreach ($cols as $candidate) {
                            if (str_contains(strtolower($candidate), $needle)) {
                                return $candidate;
                            }
                        }
                    }

                    return null;
                };

                $forced = null;
                if ($table === 'imd2025') {
                    $forced = [
                        'key' => 'LSOA_Code_2021',
                        'name' => 'LSOA_Name_2021',
                        'rank' => 'Index_of_Multiple_Deprivation_Rank',
                        'decile' => 'Index_of_Multiple_Deprivation_Decile',
                    ];
                } elseif ($table === 'wimd2019') {
                    $forced = [
                        'key' => 'LSOA_code',
                        'name' => 'LSOA_name',
                        'rank' => 'WIMD_2019',
                        'decile' => null,
                    ];
                }

                $keyCol = null;
                if ($forced !== null && Schema::hasColumn($table, $forced['key'])) {
                    $keyCol = $forced['key'];
                } else {
                    foreach ($keyCols as $candidate) {
                        if (Schema::hasColumn($table, $candidate)) {
                            $keyCol = $candidate;
                            break;
                        }
                    }
                }

                if ($keyCol === null) {
                    continue;
                }

                $select = [$keyCol];
                $rankCol = null;
                $decileCol = null;
                $nameCol = null;

                if ($forced !== null) {
                    if (! empty($forced['rank']) && Schema::hasColumn($table, $forced['rank'])) {
                        $rankCol = $forced['rank'];
                        $select[] = $rankCol;
                    }
                    if (! empty($forced['decile']) && Schema::hasColumn($table, $forced['decile'])) {
                        $decileCol = $forced['decile'];
                        $select[] = $decileCol;
                    }
                    if (! empty($forced['name']) && Schema::hasColumn($table, $forced['name'])) {
                        $nameCol = $forced['name'];
                        $select[] = $nameCol;
                    }
                }

                if ($rankCol === null) {
                    $rankCol = $pickCol($rankCols);
                    if ($rankCol !== null) {
                        $select[] = $rankCol;
                    }
                }

                if ($decileCol === null) {
                    $decileCol = $pickCol($decileCols);
                    if ($decileCol !== null) {
                        $select[] = $decileCol;
                    }
                }

                if ($nameCol === null) {
                    $nameCol = $pickCol($nameCols);
                    if ($nameCol !== null) {
                        $select[] = $nameCol;
                    }
                }

                $row = DB::table($table)
                    ->select(array_values(array_unique($select)))
                    ->where($keyCol, trim($lsoa))
                    ->first();

                if ($row === null) {
                    continue;
                }

                $total = Cache::remember('imd:total:'.$table, now()->addDays(90), function () use ($table) {
                    return (int) DB::table($table)->count();
                });

                $rank = $rankCol !== null ? (int) ($row->{$rankCol} ?? 0) : 0;
                $decile = $decileCol !== null ? (int) ($row->{$decileCol} ?? 0) : 0;

                if ($decile === 0 && $rank > 0 && $total > 0) {
                    $decile = (int) max(1, min(10, ceil(($rank / $total) * 10)));
                }

                return [
                    'table' => $table,
                    'rank' => $rank ?: null,
                    'decile' => $decile ?: null,
                    'name' => $nameCol !== null ? ((string) ($row->{$nameCol} ?? '') ?: null) : null,
                    'total' => $total ?: null,
                    'pct' => ($rank > 0 && $total > 0) ? round(($rank / $total) * 100, 1) : null,
                ];
            }

            return null;
        };

        $nearestOnspdQuery = DB::table(self::ONSPD_TABLE)
            ->select([
                'pcds',
                'lsoa21cd as lsoa21',
                'lsoa11cd as lsoa11',
                'lat',
                'long',
            ])
            ->whereNotNull('lat')
            ->whereNotNull('long');

        if (DB::connection()->getDriverName() === 'pgsql') {
            $nearestOnspdQuery->orderByRaw('point("long", lat) <-> point(?, ?)', [$centroid['lng'], $centroid['lat']]);
        } else {
            $nearestOnspdQuery->orderByRaw('POWER(lat - ?, 2) + POWER("long" - ?, 2)', [$centroid['lat'], $centroid['lng']]);
        }

        $nearestOnspdRow = $this->profileQuery(
            'deprivation postcode lookup',
            $nearestOnspdQuery,
            fn (QueryBuilder $query) => $query->first()
        );

        if ($nearestOnspdRow === null) {
            return [
                ...$emptyPayload,
                'deprMsg' => 'Unable to resolve the nearest postcode area for deprivation context.',
            ];
        }

        $lsoa = trim((string) ($nearestOnspdRow->lsoa21 ?? $nearestOnspdRow->lsoa11 ?? ''));
        $isEngland = $lsoa !== '' && str_starts_with($lsoa, 'E01');
        $isWales = $lsoa !== '' && str_starts_with($lsoa, 'W01');

        if (! $isEngland && ! $isWales) {
            return [
                ...$emptyPayload,
                'deprMsg' => 'Unable to resolve this street centroid to an English or Welsh LSOA.',
            ];
        }

        $imd = $this->profileSection('deprivation lsoa lookup', function () use ($resolveImdForLsoa, $lsoa) {
            return Cache::remember('depr:lsoa:street:'.$lsoa, now()->addDays(90), function () use ($resolveImdForLsoa, $lsoa) {
                return $resolveImdForLsoa($lsoa);
            });
        });

        if ($imd === null) {
            return [
                ...$emptyPayload,
                'deprMsg' => 'Closest LSOA found, but no deprivation record could be located in the database.',
            ];
        }

        $payload = [
            'depr' => [
                'lsoa21' => $lsoa,
                'name' => $imd['name'] ?? null,
                'rank' => $imd['rank'] ?? null,
                'decile' => $imd['decile'] ?? null,
                'pct' => $imd['pct'] ?? null,
                'total' => $imd['total'] ?? null,
                'lat' => $centroid['lat'],
                'long' => $centroid['lng'],
                'postcode' => (string) ($nearestOnspdRow->pcds ?? ''),
            ],
            'deprMsg' => null,
            'lsoaLink' => $isEngland
                ? route('deprivation.show', ['lsoa21cd' => $lsoa])
                : route('deprivation.wales.show', ['lsoa' => $lsoa]),
        ];

        $this->logSectionTiming('deprivation payload', $startedAt);

        return $payload;
    }

    /**
     * @return array{
     *     crime_data:array<int, array<string, mixed>>,
     *     crime_trend:array<int, array<string, mixed>>,
     *     crime_summary:?string,
     *     crime_direction:string,
     *     crime_trend_labels:array<int, string>,
     *     crime_trend_values:array<int, int>,
     *     crime_total_change:float,
     *     crime_top_increase:?array<string, mixed>,
     *     crime_top_decrease:?array<string, mixed>
     * }
     */
    private function buildOutcodeCrimePayload(string $outcode, ?float $latitude, ?float $longitude): array
    {
        $startedAt = microtime(true);
        $emptyPayload = [
            'crime_data' => [],
            'crime_trend' => [],
            'crime_summary' => null,
            'crime_direction' => 'stable',
            'crime_trend_labels' => [],
            'crime_trend_values' => [],
            'crime_total_change' => 0.0,
            'crime_top_increase' => null,
            'crime_top_decrease' => null,
        ];

        if (! $this->hasCrimeTable() || ! $this->hasOnspdTable()) {
            return $emptyPayload;
        }

        $coords = $this->profileSection('crime representative point', fn () => $this->outcodeCrimePoint($outcode, $latitude, $longitude));

        if ($coords === null) {
            $this->logSectionTiming('crime payload', $startedAt);

            return $emptyPayload;
        }

        $cacheKey = self::outcodeCrimeCacheKey($outcode);
        $cacheLookupStartedAt = microtime(true);
        $cachedPayload = Cache::get($cacheKey);
        $cacheLookupElapsedMs = $this->elapsedMs($cacheLookupStartedAt);
        if ($this->warmProfilingEnabled) {
            $this->recordSectionTiming('crime cache lookup', $cacheLookupElapsedMs);
            $this->logWarmProfile(sprintf(
                'section="%s" outcode=%s elapsed_ms=%.2f',
                'crime cache lookup',
                $outcode,
                $cacheLookupElapsedMs
            ));
        }

        if (is_array($cachedPayload)) {
            if ($this->warmProfilingEnabled) {
                $this->recordSectionTiming('crime cache hit', $cacheLookupElapsedMs);
                $this->logWarmProfile(sprintf(
                    'section="%s" outcode=%s elapsed_ms=%.2f',
                    'crime cache hit',
                    $outcode,
                    $cacheLookupElapsedMs
                ));
            }
            $this->logSectionTiming('crime payload', $startedAt);

            return $cachedPayload;
        }

        if ($this->warmProfilingEnabled) {
            $this->logWarmProfile(sprintf('section="%s" outcode=%s', 'crime cache miss', $outcode));
        }

        $payload = $this->profileSection('crime cache miss build', fn (): array => $this->compileCrimePayload($coords['lat'], $coords['lng']));

        $cacheWriteStartedAt = microtime(true);
        Cache::put($cacheKey, $payload, self::CACHE_TTL);
        $cacheWriteElapsedMs = $this->elapsedMs($cacheWriteStartedAt);
        if ($this->warmProfilingEnabled) {
            $this->recordSectionTiming('crime cache write', $cacheWriteElapsedMs);
            $this->logWarmProfile(sprintf(
                'section="%s" outcode=%s elapsed_ms=%.2f',
                'crime cache write',
                $outcode,
                $cacheWriteElapsedMs
            ));
        }
        $this->logSectionTiming('crime payload', $startedAt);

        return $payload;
    }

    /**
     * @return array{
     *     crime_data:array<int, array<string, mixed>>,
     *     crime_trend:array<int, array<string, mixed>>,
     *     crime_summary:?string,
     *     crime_direction:string,
     *     crime_trend_labels:array<int, string>,
     *     crime_trend_values:array<int, int>,
     *     crime_total_change:float,
     *     crime_top_increase:?array<string, mixed>,
     *     crime_top_decrease:?array<string, mixed>
     * }
     */
    private function compileCrimePayload(float $latitude, float $longitude): array
    {
        $emptyPayload = [
            'crime_data' => [],
            'crime_trend' => [],
            'crime_summary' => null,
            'crime_direction' => 'stable',
            'crime_trend_labels' => [],
            'crime_trend_values' => [],
            'crime_total_change' => 0.0,
            'crime_top_increase' => null,
            'crime_top_decrease' => null,
        ];

        $latestCrimeMonth = $this->profileSection('crime latest month lookup', fn () => $this->latestCrimeMonth());

        if ($latestCrimeMonth === null) {
            return $emptyPayload;
        }

        $currentWindowEnd = Carbon::parse((string) $latestCrimeMonth)->startOfMonth();
        $crimeWindowStart = $currentWindowEnd->copy()->subMonths(11);
        $previousWindowStart = $crimeWindowStart->copy()->subMonths(12);

        $crimeSummaryRows = $this->profileQuery(
            'crime summary query',
            Crime::query()
                ->selectRaw('crime_type, COUNT(*) as total')
                ->whereDate('month', '>=', $crimeWindowStart->toDateString())
                ->whereBetween('latitude', [$latitude - 0.005, $latitude + 0.005])
                ->whereBetween('longitude', [$longitude - 0.005, $longitude + 0.005])
                ->whereNotNull('crime_type')
                ->groupBy('crime_type')
                ->orderByDesc('total')
                ->limit(10),
            fn (EloquentBuilder $query) => $query->get()
        );

        $crimeTrendCounts = $this->profileQuery(
            'crime trend query',
            Crime::query()
                ->selectRaw(
                    'crime_type,
                SUM(CASE WHEN month >= ? THEN 1 ELSE 0 END) as current_total,
                SUM(CASE WHEN month >= ? AND month < ? THEN 1 ELSE 0 END) as previous_total',
                    [
                        $crimeWindowStart->toDateString(),
                        $previousWindowStart->toDateString(),
                        $crimeWindowStart->toDateString(),
                    ]
                )
                ->whereDate('month', '>=', $previousWindowStart->toDateString())
                ->whereDate('month', '<=', $currentWindowEnd->toDateString())
                ->whereBetween('latitude', [$latitude - 0.005, $latitude + 0.005])
                ->whereBetween('longitude', [$longitude - 0.005, $longitude + 0.005])
                ->whereNotNull('crime_type')
                ->groupBy('crime_type'),
            fn (EloquentBuilder $query) => $query->get()
                ->map(function ($crime) {
                    $currentTotal = (int) $crime->current_total;
                    $previousTotal = (int) $crime->previous_total;
                    $pctChange = $previousTotal > 0
                        ? round((($currentTotal - $previousTotal) * 100) / $previousTotal, 1)
                        : ($currentTotal > 0 ? 100.0 : 0.0);

                    return [
                        'crime_type' => (string) $crime->crime_type,
                        'current_total' => $currentTotal,
                        'previous_total' => $previousTotal,
                        'pct_change' => $pctChange,
                    ];
                })
                ->values()
        );

        $crimeTrendSeriesRows = $this->profileQuery(
            'crime series query',
            Crime::query()
                ->selectRaw('month, COUNT(*) as total')
                ->whereDate('month', '>=', $previousWindowStart->toDateString())
                ->whereDate('month', '<=', $currentWindowEnd->toDateString())
                ->whereBetween('latitude', [$latitude - 0.005, $latitude + 0.005])
                ->whereBetween('longitude', [$longitude - 0.005, $longitude + 0.005])
                ->groupBy('month')
                ->orderBy('month'),
            fn (EloquentBuilder $query) => $query->pluck('total', 'month')
        );

        $crimeTrendLabels = [];
        $crimeTrendValues = [];
        $trendCursor = $previousWindowStart->copy();

        while ($trendCursor->lte($currentWindowEnd)) {
            $monthKey = $trendCursor->toDateString();
            $crimeTrendLabels[] = $trendCursor->format('M y');
            $crimeTrendValues[] = (int) ($crimeTrendSeriesRows[$monthKey] ?? 0);
            $trendCursor->addMonth();
        }

        $crimeTrendByType = $crimeTrendCounts->keyBy('crime_type');
        $crimeTotal = (int) $crimeSummaryRows->sum('total');

        $nationalCrimeTrendByType = $this->profileSection('national crime trend lookup', fn () => $this->nationalCrimeTrendByType(
            $previousWindowStart->toDateString(),
            $crimeWindowStart->toDateString(),
            $currentWindowEnd->toDateString()
        ));

        $crimeData = $crimeSummaryRows
            ->map(function ($crime) use ($crimeTotal, $crimeTrendByType, $nationalCrimeTrendByType): array {
                $crimeType = (string) $crime->crime_type;
                $trend = $crimeTrendByType->get($crimeType);

                return [
                    'crime_type' => $crimeType,
                    'total' => (int) $crime->total,
                    'pct' => $crimeTotal > 0 ? round(((int) $crime->total * 100) / $crimeTotal, 1) : 0.0,
                    'pct_change' => (float) ($trend['pct_change'] ?? 0.0),
                    'national_pct_change' => $nationalCrimeTrendByType->get($crimeType),
                ];
            })
            ->values();

        $totalCurrent = (int) $crimeTrendCounts->sum('current_total');
        $totalPrevious = (int) $crimeTrendCounts->sum('previous_total');
        $totalChange = $totalPrevious > 0
            ? round((($totalCurrent - $totalPrevious) * 100) / $totalPrevious, 1)
            : ($totalCurrent > 0 ? 100.0 : 0.0);

        $crimeTrend = $crimeTrendCounts
            ->map(function (array $crime) use ($totalCurrent, $totalPrevious, $totalChange): array {
                $crime['total_current'] = $totalCurrent;
                $crime['total_previous'] = $totalPrevious;
                $crime['total_pct_change'] = $totalChange;

                return $crime;
            })
            ->sortByDesc('pct_change')
            ->values();

        $topIncrease = $crimeTrend
            ->first(fn (array $crime): bool => (float) $crime['pct_change'] > 0);

        if ($topIncrease === null) {
            $topIncrease = $crimeTrend->sortByDesc('pct_change')->first();
        }

        $topDecrease = $crimeTrend
            ->sortBy('pct_change')
            ->first(fn (array $crime): bool => (float) $crime['pct_change'] < 0);

        if ($topDecrease === null) {
            $topDecrease = $crimeTrend->sortBy('pct_change')->first();
        }

        $crimeDirection = 'stable';

        if ($totalChange > 10) {
            $crimeDirection = 'rising';
        } elseif ($totalChange < -10) {
            $crimeDirection = 'falling';
        }

        if ($topIncrease !== null && (int) $topIncrease['previous_total'] < 20) {
            $topIncrease['pct_change_label'] = $topIncrease['pct_change'].'% (low volume)';
        }

        $directionWord = $totalChange > 0 ? 'up' : 'down';
        $crimeSummary = 'Crime is '.$directionWord.' '.abs($totalChange).'% over the past year';

        if ($topIncrease !== null && $topDecrease !== null) {
            $crimeSummary .= ', driven by increases in '.strtolower((string) $topIncrease['crime_type']);
            $crimeSummary .= ' and offset by decreases in '.strtolower((string) $topDecrease['crime_type']).'.';
        } else {
            $crimeSummary .= '.';
        }

        $payload = [
            'crime_data' => $crimeData->all(),
            'crime_trend' => $crimeTrend->all(),
            'crime_summary' => $crimeSummary,
            'crime_direction' => $crimeDirection,
            'crime_trend_labels' => $crimeTrendLabels,
            'crime_trend_values' => $crimeTrendValues,
            'crime_total_change' => $totalChange,
            'crime_top_increase' => $topIncrease,
            'crime_top_decrease' => $topDecrease,
        ];

        return $payload;
    }

    /**
     * @return array{lat:float,lng:float}|null
     */
    private function outcodeCrimePoint(string $outcode, ?float $fallbackLatitude, ?float $fallbackLongitude): ?array
    {
        $cacheKey = self::outcodeCrimePointCacheKey($outcode);
        $cachedPoint = Cache::get($cacheKey);

        if (is_array($cachedPoint) && isset($cachedPoint['lat'], $cachedPoint['lng'])) {
            return [
                'lat' => (float) $cachedPoint['lat'],
                'lng' => (float) $cachedPoint['lng'],
            ];
        }

        $point = $this->profileQuery(
            'crime representative point query',
            DB::table(self::ONSPD_TABLE)
                ->select(['pcds', 'lat', 'long', 'dointr'])
                ->whereNotNull('lat')
                ->whereNotNull('long')
                ->whereRaw($this->onspdOutcodeExpression().' = ?', [$outcode])
                ->orderBy('pcds')
                ->orderByDesc('dointr'),
            function (QueryBuilder $query): ?array {
                $row = $query->first();

                if ($row === null || $row->lat === null || $row->long === null) {
                    return null;
                }

                return [
                    'lat' => (float) $row->lat,
                    'lng' => (float) $row->long,
                ];
            },
            ['outcode' => $outcode]
        );

        if ($point !== null) {
            Cache::put($cacheKey, $point, self::CACHE_TTL);

            return $point;
        }

        if ($fallbackLatitude === null || $fallbackLongitude === null) {
            return null;
        }

        return [
            'lat' => $fallbackLatitude,
            'lng' => $fallbackLongitude,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array{lat:float,lng:float}|null
     */
    private function streetCentroid(array $records): ?array
    {
        $startedAt = microtime(true);
        $postcodes = collect($records)
            ->pluck('postcode')
            ->filter(fn ($postcode): bool => is_string($postcode) && trim($postcode) !== '')
            ->map(fn (string $postcode): string => $this->canonicalPostcode($postcode))
            ->unique()
            ->values();

        if ($postcodes->isEmpty()) {
            return null;
        }

        $rows = $this->profileQuery(
            'centroid postcode lookup',
            DB::table(self::ONSPD_TABLE)
                ->select(['pcds', 'lat', 'long', 'dointr'])
                ->whereIn('pcds', $postcodes)
                ->orderBy('pcds')
                ->orderByDesc('dointr'),
            fn (QueryBuilder $query) => $query->get()
        );

        $coordsByPostcode = [];

        foreach ($rows as $row) {
            $pcds = (string) $row->pcds;

            if (isset($coordsByPostcode[$pcds])) {
                continue;
            }

            if ($row->lat === null || $row->long === null) {
                continue;
            }

            $coordsByPostcode[$pcds] = [
                'lat' => (float) $row->lat,
                'lng' => (float) $row->long,
            ];
        }

        if ($coordsByPostcode === []) {
            return null;
        }

        $latAverage = collect($coordsByPostcode)->avg('lat');
        $lngAverage = collect($coordsByPostcode)->avg('lng');

        if ($latAverage === null || $lngAverage === null) {
            return null;
        }

        $coords = [
            'lat' => (float) $latAverage,
            'lng' => (float) $lngAverage,
        ];

        $this->logSectionTiming('centroid calculation', $startedAt);

        return $coords;
    }

    private function resolveStreetName(string $streetSlug, string $outcode): ?string
    {
        $startedAt = microtime(true);
        $slugMap = $this->streetSlugMapForOutcode($outcode);
        $this->logSectionTiming('resolveStreetName', $startedAt);

        return $slugMap[$streetSlug] ?? null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    private function mostCommonPropertyType(array $records): ?string
    {
        $counts = collect($records)
            ->pluck('property_type')
            ->filter(fn ($propertyType): bool => is_string($propertyType) && $propertyType !== '' && $propertyType !== 'Unknown')
            ->countBy();

        if ($counts->isEmpty()) {
            return null;
        }

        return (string) $counts
            ->sortDesc()
            ->keys()
            ->first();
    }

    /**
     * @param  array<string, int|string|null>  $summary
     * @param  array<string, mixed>  $crimePayload
     * @param  array<string, mixed>  $deprivationPayload
     * @return array<int, array{label:string, value:string}>
     */
    private function buildGlanceMetrics(array $summary, array $crimePayload, array $deprivationPayload): array
    {
        $metrics = [
            ['label' => 'Total recorded sales', 'value' => number_format((int) ($summary['total_sales'] ?? 0))],
            ['label' => 'Average sale price', 'value' => $this->formatPrice($summary['average_sale_price'] ?? null)],
            ['label' => 'Median sale price', 'value' => $this->formatPrice($summary['median_sale_price'] ?? null)],
            ['label' => 'Latest sale date', 'value' => (string) ($summary['latest_sale_date'] ?? '')],
            ['label' => 'Highest recorded sale', 'value' => $this->formatPrice($summary['highest_sale'] ?? null)],
            ['label' => 'Most common property type', 'value' => (string) ($summary['most_common_property_type'] ?? '')],
        ];

        $deprivation = $deprivationPayload['depr'] ?? null;
        if (is_array($deprivation) && ! empty($deprivation['decile'])) {
            $metrics[] = [
                'label' => 'Deprivation band',
                'value' => 'Decile '.$deprivation['decile'].' / 10',
            ];
        }

        if (! empty($crimePayload['crime_summary'])) {
            $metrics[] = [
                'label' => 'Crime level',
                'value' => (string) $crimePayload['crime_summary'],
            ];
        }

        return collect($metrics)
            ->filter(fn (array $metric): bool => trim($metric['value']) !== '' && $metric['value'] !== 'N/A')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int|string|null>  $summary
     * @return array<string, mixed>
     */
    private function buildOutcodeComparison(string $streetName, string $outcode, array $summary): array
    {
        $startedAt = microtime(true);
        $cacheKey = sprintf('property:street:%s:outcode-comparison:%s', self::CACHE_VERSION, Str::lower($outcode));

        $outcodeSummary = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($outcode): array {
            $aggregate = $this->profileQuery(
                'outcode comparison aggregate query',
                DB::table('land_registry')
                    ->selectRaw('COUNT(*) as sales_count')
                    ->selectRaw('AVG("Price") as average_sale_price')
                    ->whereRaw('"PPDCategoryType" = ?', ['A'])
                    ->whereRaw($this->outcodeExpression().' = ?', [$outcode]),
                fn (QueryBuilder $query) => $query->first()
            );

            $prices = $this->profileQuery(
                'outcode comparison prices query',
                DB::table('land_registry')
                    ->whereRaw('"PPDCategoryType" = ?', ['A'])
                    ->whereRaw($this->outcodeExpression().' = ?', [$outcode])
                    ->whereNotNull('Price')
                    ->where('Price', '>', 0)
                    ->orderBy('Price'),
                fn (QueryBuilder $query) => $query->pluck('Price')
                    ->map(fn ($price): int => (int) $price)
                    ->all()
            );

            $propertyType = $this->profileQuery(
                'outcode comparison property type query',
                DB::table('land_registry')
                    ->select('PropertyType')
                    ->selectRaw('COUNT(*) as total')
                    ->whereRaw('"PPDCategoryType" = ?', ['A'])
                    ->whereRaw($this->outcodeExpression().' = ?', [$outcode])
                    ->whereNotNull('PropertyType')
                    ->groupBy('PropertyType')
                    ->orderByDesc('total')
                    ->orderBy('PropertyType'),
                fn (QueryBuilder $query) => $query->value('PropertyType')
            );

            return [
                'sales_count' => (int) ($aggregate->sales_count ?? 0),
                'average_sale_price' => isset($aggregate->average_sale_price) ? (int) round((float) $aggregate->average_sale_price) : null,
                'median_sale_price' => $this->medianValue($prices),
                'most_common_property_type' => $this->propertyTypeLabel($propertyType !== null ? (string) $propertyType : null),
            ];
        });

        $payload = [
            'street_label' => $this->titleCaseStreetName($streetName),
            'outcode_label' => $outcode,
            'street' => [
                'sales_count' => (int) ($summary['total_sales'] ?? 0),
                'average_sale_price' => $summary['average_sale_price'] ?? null,
                'median_sale_price' => $summary['median_sale_price'] ?? null,
                'most_common_property_type' => $summary['most_common_property_type'] ?? null,
            ],
            'outcode' => $outcodeSummary,
        ];

        $this->logSectionTiming('outcode comparison', $startedAt);

        return $payload;
    }

    /**
     * @return array<int, array{name:string, slug:string, outcode:string, sales_count:int, url:string}>
     */
    private function buildNearbyStreets(string $streetName, string $outcode): array
    {
        $startedAt = microtime(true);
        $nearbyStreets = collect($this->nearbyStreetCatalogForOutcode($outcode))
            ->reject(fn (array $street): bool => $street['name'] === $this->titleCaseStreetName($streetName))
            ->take(self::NEARBY_STREETS_LIMIT)
            ->values()
            ->all();

        $this->logSectionTiming('nearby streets', $startedAt);

        return $nearbyStreets;
    }

    /**
     * @return array<string, string>
     */
    private function streetSlugMapForOutcode(string $outcode): array
    {
        if (array_key_exists($outcode, $this->streetSlugMaps)) {
            return $this->streetSlugMaps[$outcode];
        }

        $cacheKey = sprintf('property:street:%s:street-slugs:%s', self::CACHE_VERSION, Str::lower($outcode));

        /** @var array<string, string> $slugMap */
        $slugMap = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($outcode): array {
            return $this->profileQuery(
                'street slug map query',
                DB::table('land_registry')
                    ->selectRaw('TRIM("Street") as street')
                    ->whereRaw('"PPDCategoryType" = ?', ['A'])
                    ->whereRaw('"Street" IS NOT NULL')
                    ->whereRaw('TRIM("Street") <> ?', [''])
                    ->whereRaw($this->outcodeExpression().' = ?', [$outcode])
                    ->distinct()
                    ->orderByRaw('TRIM("Street")'),
                fn (QueryBuilder $query) => $query->pluck('street')
                    ->map(fn ($street): string => trim((string) $street))
                    ->reduce(function (array $carry, string $street): array {
                        $carry[Str::slug($street)] ??= $street;

                        return $carry;
                    }, [])
            );
        });

        return $this->streetSlugMaps[$outcode] = $slugMap;
    }

    /**
     * @return array<int, array{name:string,slug:string,outcode:string,sales_count:int,url:string}>
     */
    private function nearbyStreetCatalogForOutcode(string $outcode): array
    {
        if (array_key_exists($outcode, $this->nearbyStreetCatalogs)) {
            return $this->nearbyStreetCatalogs[$outcode];
        }

        $cacheKey = sprintf('property:street:%s:nearby-streets:%s', self::CACHE_VERSION, Str::lower($outcode));

        /** @var array<int, array{name:string,slug:string,outcode:string,sales_count:int,url:string}> $catalog */
        $catalog = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($outcode): array {
            return $this->profileQuery(
                'nearby streets query',
                DB::table('land_registry')
                    ->selectRaw('TRIM("Street") as street')
                    ->selectRaw('COUNT(*) as sales_count')
                    ->whereRaw('"PPDCategoryType" = ?', ['A'])
                    ->whereRaw($this->outcodeExpression().' = ?', [$outcode])
                    ->whereRaw('"Street" IS NOT NULL')
                    ->whereRaw('TRIM("Street") <> ?', [''])
                    ->groupByRaw('TRIM("Street")')
                    ->havingRaw('COUNT(*) >= ?', [self::MIN_RELIABLE_SALES])
                    ->orderByDesc('sales_count')
                    ->orderByRaw('TRIM("Street")'),
                fn (QueryBuilder $query) => $query->get()
                    ->map(function (object $row) use ($outcode): array {
                        $rawName = trim((string) $row->street);
                        $slug = Str::slug($rawName);

                        return [
                            'name' => $this->titleCaseStreetName($rawName),
                            'slug' => $slug,
                            'outcode' => $outcode,
                            'sales_count' => (int) $row->sales_count,
                            'url' => self::streetPath($outcode, $slug),
                        ];
                    })
                    ->all()
            );
        });

        return $this->nearbyStreetCatalogs[$outcode] = $catalog;
    }

    private function latestCrimeMonth(): ?string
    {
        return Cache::remember(
            sprintf('property:street:%s:crime-latest-month', self::CACHE_VERSION),
            self::CACHE_TTL,
            fn (): ?string => Crime::query()->max('month')
        );
    }

    /**
     * @return Collection<string, float>
     */
    private function nationalCrimeTrendByType(string $previousWindowStart, string $crimeWindowStart, string $currentWindowEnd)
    {
        $cacheKey = sprintf(
            'property:street:%s:national-crime-trend:%s:%s',
            self::CACHE_VERSION,
            $previousWindowStart,
            $currentWindowEnd
        );

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($previousWindowStart, $crimeWindowStart, $currentWindowEnd) {
            return Crime::query()
                ->selectRaw(
                    'crime_type,
                    SUM(CASE WHEN month >= ? THEN 1 ELSE 0 END) as current_total,
                    SUM(CASE WHEN month >= ? AND month < ? THEN 1 ELSE 0 END) as previous_total',
                    [
                        $crimeWindowStart,
                        $previousWindowStart,
                        $crimeWindowStart,
                    ]
                )
                ->whereDate('month', '>=', $previousWindowStart)
                ->whereDate('month', '<=', $currentWindowEnd)
                ->groupBy('crime_type')
                ->get()
                ->mapWithKeys(function ($crime) {
                    $currentTotal = (int) $crime->current_total;
                    $previousTotal = (int) $crime->previous_total;
                    $pctChange = $previousTotal > 0
                        ? round((($currentTotal - $previousTotal) * 100) / $previousTotal, 1)
                        : ($currentTotal > 0 ? 100.0 : 0.0);

                    return [(string) $crime->crime_type => $pctChange];
                });
        });
    }

    private function hasCrimeTable(): bool
    {
        return $this->hasCrimeTable ??= Schema::hasTable('crime');
    }

    private function hasOnspdTable(): bool
    {
        return $this->hasOnspdTable ??= Schema::hasTable(self::ONSPD_TABLE);
    }

    private function onspdOutcodeExpression(): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return 'UPPER(SPLIT_PART(pcds, \' \', 1))';
        }

        return 'UPPER(TRIM(SUBSTR(pcds, 1, CASE WHEN INSTR(pcds, \' \') = 0 THEN LENGTH(pcds) ELSE INSTR(pcds, \' \') - 1 END)))';
    }

    /**
     * @template TReturn
     *
     * @param  callable():TReturn  $callback
     * @return TReturn
     */
    private function profileSection(string $section, callable $callback)
    {
        if (! $this->warmProfilingEnabled) {
            return $callback();
        }

        $startedAt = microtime(true);
        $result = $callback();
        $elapsedMs = $this->elapsedMs($startedAt);
        $this->recordSectionTiming($section, $elapsedMs);
        $this->logWarmProfile(sprintf('section="%s" elapsed_ms=%.2f', $section, $elapsedMs));

        return $result;
    }

    /**
     * @template TBuilder of QueryBuilder|EloquentBuilder
     * @template TResult
     *
     * @param  TBuilder  $query
     * @param  callable(TBuilder):TResult  $runner
     * @param  array<string, string>  $context
     * @return TResult
     */
    private function profileQuery(string $section, $query, callable $runner, array $context = [])
    {
        if (! $this->warmProfilingEnabled) {
            return $runner($query);
        }

        [$sql, $bindings] = $this->querySqlAndBindings($query);
        $startedAt = microtime(true);
        $result = $runner($query);
        $elapsedMs = $this->elapsedMs($startedAt);
        $this->recordSectionTiming($section, $elapsedMs);

        $contextPrefix = collect($context)
            ->map(fn (string $value, string $key): string => $key.'='.$value)
            ->implode(' ');

        $this->logWarmProfile(trim(sprintf(
            '%s section="%s" elapsed_ms=%.2f',
            $contextPrefix,
            $section,
            $elapsedMs
        )));

        if ($elapsedMs >= self::PROFILE_SLOW_MS) {
            $planSummary = $this->explainQueryPlan($sql, $bindings);
            $this->logWarmProfile(sprintf(
                'slow-query section="%s" elapsed_ms=%.2f uses_idx_land_registry_street_page_sales=%s plan=%s sql=%s bindings=%s',
                $section,
                $elapsedMs,
                $planSummary['uses_target_index'] ? 'yes' : 'no',
                $planSummary['summary'],
                preg_replace('/\s+/', ' ', trim($sql)) ?? $sql,
                json_encode($bindings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ));
        }

        return $result;
    }

    private function logSectionTiming(string $section, float $startedAt): void
    {
        if (! $this->warmProfilingEnabled) {
            return;
        }

        $elapsedMs = $this->elapsedMs($startedAt);
        $this->recordSectionTiming($section, $elapsedMs);
        $this->logWarmProfile(sprintf('section="%s" elapsed_ms=%.2f', $section, $elapsedMs));
    }

    private function elapsedMs(float $startedAt): float
    {
        return (microtime(true) - $startedAt) * 1000;
    }

    private function recordSectionTiming(string $section, float $elapsedMs): void
    {
        if (! isset($this->warmProfilingStats[$section])) {
            $this->warmProfilingStats[$section] = [
                'count' => 0,
                'total_ms' => 0.0,
                'max_ms' => 0.0,
            ];
        }

        $this->warmProfilingStats[$section]['count']++;
        $this->warmProfilingStats[$section]['total_ms'] += $elapsedMs;
        $this->warmProfilingStats[$section]['max_ms'] = max($this->warmProfilingStats[$section]['max_ms'], $elapsedMs);
    }

    private function logWarmProfile(string $message): void
    {
        if (! $this->warmProfilingEnabled) {
            return;
        }

        $logger = $this->warmProfilingLogger;

        if ($logger !== null) {
            $logger('[street-profile] '.$message);
        }
    }

    /**
     * @param  QueryBuilder|EloquentBuilder  $query
     * @return array{0:string,1:array<int, mixed>}
     */
    private function querySqlAndBindings($query): array
    {
        if ($query instanceof EloquentBuilder) {
            return [$query->toBase()->toSql(), $query->getQuery()->getBindings()];
        }

        return [$query->toSql(), $query->getBindings()];
    }

    /**
     * @param  array<int, mixed>  $bindings
     * @return array{uses_target_index:bool,summary:string}
     */
    private function explainQueryPlan(string $sql, array $bindings): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return [
                'uses_target_index' => false,
                'summary' => 'not-pgsql',
            ];
        }

        $rows = DB::select('EXPLAIN (FORMAT JSON) '.$sql, $bindings);
        $planJson = $rows[0]->{'QUERY PLAN'} ?? null;

        if (! is_string($planJson)) {
            return [
                'uses_target_index' => false,
                'summary' => 'no-plan',
            ];
        }

        $decoded = json_decode($planJson, true);
        $rootPlan = $decoded[0]['Plan'] ?? null;

        if (! is_array($rootPlan)) {
            return [
                'uses_target_index' => false,
                'summary' => 'invalid-plan',
            ];
        }

        $nodes = [];
        $usesTargetIndex = false;
        $stack = [$rootPlan];

        while ($stack !== []) {
            $node = array_pop($stack);

            if (! is_array($node)) {
                continue;
            }

            $nodeType = (string) ($node['Node Type'] ?? 'Unknown');
            $indexName = (string) ($node['Index Name'] ?? '');
            $relationName = (string) ($node['Relation Name'] ?? '');

            $summary = $nodeType;
            if ($relationName !== '') {
                $summary .= '@'.$relationName;
            }
            if ($indexName !== '') {
                $summary .= '['.$indexName.']';
            }
            $nodes[] = $summary;

            if ($indexName === 'idx_land_registry_street_page_sales') {
                $usesTargetIndex = true;
            }

            foreach (($node['Plans'] ?? []) as $childPlan) {
                $stack[] = $childPlan;
            }
        }

        return [
            'uses_target_index' => $usesTargetIndex,
            'summary' => implode(' -> ', array_slice($nodes, 0, 6)),
        ];
    }

    /**
     * @param  array<string, int|string|null>  $summary
     */
    private function buildMetaDescription(string $streetName, string $outcode, array $summary): string
    {
        $streetLabel = $this->titleCaseStreetName($streetName);

        if (($summary['average_sale_price'] ?? null) !== null || ($summary['median_sale_price'] ?? null) !== null) {
            return sprintf(
                'Sold property data for %s, %s. View average sale prices, recent transactions, property types, EPC ratings and local market trends.',
                $streetLabel,
                $outcode
            );
        }

        return sprintf(
            'See sold house prices, property types, EPC ratings and local property data for %s, %s.',
            $streetLabel,
            $outcode
        );
    }

    /**
     * @param  array<string, int|string|null>  $summary
     * @param  array<string, mixed>  $outcodeComparison
     * @return array<int, array{question:string, answer:string}>
     */
    private function buildFaqItems(string $streetName, string $outcode, array $summary, array $outcodeComparison): array
    {
        $streetLabel = $this->titleCaseStreetName($streetName);
        $faqItems = [];

        if (($summary['average_sale_price'] ?? null) !== null) {
            $faqItems[] = [
                'question' => "What is the average house price on {$streetLabel}?",
                'answer' => sprintf(
                    'The average recorded sale price on %s, %s is %s based on %s sales.',
                    $streetLabel,
                    $outcode,
                    $this->formatPrice($summary['average_sale_price']),
                    number_format((int) ($summary['total_sales'] ?? 0))
                ),
            ];
        }

        $faqItems[] = [
            'question' => "How many properties have sold on {$streetLabel}?",
            'answer' => sprintf(
                '%s recorded %s Category A sales in the current street dataset for %s.',
                $streetLabel,
                number_format((int) ($summary['total_sales'] ?? 0)),
                $outcode
            ),
        ];

        if (! empty($summary['most_common_property_type'])) {
            $faqItems[] = [
                'question' => "What types of properties sell on {$streetLabel}?",
                'answer' => sprintf(
                    'The most common recorded property type on %s, %s is %s.',
                    $streetLabel,
                    $outcode,
                    $summary['most_common_property_type']
                ),
            ];
        }

        if (($summary['average_sale_price'] ?? null) !== null && ($outcodeComparison['outcode']['average_sale_price'] ?? null) !== null) {
            $faqItems[] = [
                'question' => "How does {$streetLabel} compare with {$outcode}?",
                'answer' => sprintf(
                    '%s has an average sale price of %s versus %s across %s overall.',
                    $streetLabel,
                    $this->formatPrice($summary['average_sale_price']),
                    $this->formatPrice($outcodeComparison['outcode']['average_sale_price']),
                    $outcode
                ),
            ];
        }

        return $faqItems;
    }

    private function titleCaseStreetName(string $streetName): string
    {
        return Str::title(Str::lower($streetName));
    }

    private function formatPrice(int|string|null $price): string
    {
        return is_numeric($price) ? '£'.number_format((int) $price) : 'N/A';
    }

    /**
     * @param  array<int, array<string, mixed>>  $sales
     */
    private function paginateSales(array $sales, Request $request): LengthAwarePaginator
    {
        $perPage = 25;
        $currentPage = max(1, (int) $request->query('page', 1));
        $offset = ($currentPage - 1) * $perPage;

        return new LengthAwarePaginator(
            array_slice($sales, $offset, $perPage),
            count($sales),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    /**
     * @param  array<int, int>  $values
     */
    private function medianValue(array $values): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values, SORT_NUMERIC);

        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return $values[$middle];
        }

        return (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }

    private function formatAddress(string $paon, string $saon): string
    {
        return collect([$paon, $saon])
            ->filter(fn (string $part): bool => $part !== '')
            ->implode(', ');
    }

    private function normalizeOutcode(string $outcode): ?string
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', trim($outcode)) ?? '');

        return $normalized !== '' ? $normalized : null;
    }

    private function canonicalPostcode(string $postcode): string
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', trim($postcode)) ?? '');

        if ($normalized === '' || strlen($normalized) < 5) {
            return strtoupper(trim($postcode));
        }

        return substr($normalized, 0, -3).' '.substr($normalized, -3);
    }

    private function outcodeExpression(): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return 'UPPER(SPLIT_PART("Postcode", \' \', 1))';
        }

        return 'UPPER(TRIM(SUBSTR("Postcode", 1, CASE WHEN INSTR("Postcode", \' \') = 0 THEN LENGTH("Postcode") ELSE INSTR("Postcode", \' \') - 1 END)))';
    }

    private function propertyTypeLabel(?string $code): string
    {
        return match ($code) {
            'D' => 'Detached',
            'S' => 'Semi-detached',
            'T' => 'Terraced',
            'F' => 'Flat',
            'O' => 'Other',
            default => 'Unknown',
        };
    }

    private function tenureLabel(?string $code): string
    {
        return match ($code) {
            'F' => 'Freehold',
            'L' => 'Leasehold',
            default => 'Unknown',
        };
    }

    private function buildStatusLabel(?string $code): string
    {
        return match ($code) {
            'Y' => 'New build',
            'N' => 'Existing',
            default => 'Unknown',
        };
    }

    private function buildPropertySlug(string $postcode, string $paon, string $street, ?string $saon = null): string
    {
        $parts = [
            $this->normalizeSlugPart($postcode),
            $this->normalizeSlugPart($paon),
            $this->normalizeSlugPart($street),
        ];

        if ($saon !== null && trim($saon) !== '') {
            $parts[] = $this->normalizeSlugPart($saon);
        }

        $parts = array_values(array_filter($parts, fn (string $part): bool => $part !== ''));

        return preg_replace('/-+/', '-', implode('-', $parts)) ?? '';
    }

    private function normalizeSlugPart(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(',', '', $normalized);
        $normalized = preg_replace('/\s+/', '-', $normalized) ?? '';
        $normalized = preg_replace('/-+/', '-', $normalized) ?? '';

        return trim($normalized, '-');
    }
}
