<?php

namespace App\Console\Commands;

use App\Models\MarketInsight;
use App\Services\InsightWriter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenerateMarketInsights extends Command
{
    /**
     * Future enhancements:
     * - AI rewriting of insights
     * - district/county detection
     * - maps and visualisation
     * - additional anomaly detectors
     * - automated monthly scheduler
     */
    protected $signature = 'insights:generate';

    protected $description = 'Run anomaly detection and store market insights.';

    private const PRICE_SPIKE_THRESHOLD = 15.0;

    private const DEMAND_COLLAPSE_THRESHOLD = -30.0;

    private const OUTPERFORMANCE_THRESHOLD = 20.0;

    public function __construct(private InsightWriter $insightWriter)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! Schema::hasTable('land_registry') || ! Schema::hasTable('market_insights')) {
            $this->warn('Required tables are not available.');

            return self::SUCCESS;
        }

        $inserted = 0;
        $skipped = 0;

        foreach ($this->runAnomalyQueries() as $row) {
            if ($this->insightExists($row)) {
                $skipped++;

                continue;
            }

            MarketInsight::create([
                'area_type' => $row['area_type'],
                'area_code' => $row['area_code'],
                'insight_type' => $row['insight_type'],
                'metric_value' => $row['metric_value'],
                'transactions' => $row['transactions'],
                'period_start' => $row['period_start'],
                'period_end' => $row['period_end'],
                'supporting_data' => $row['supporting_data'],
                'insight_text' => $row['insight_text'],
            ]);

            $inserted++;
        }

        $this->info("Generated {$inserted} insights.");
        $this->info("Skipped {$skipped} duplicates.");

        return self::SUCCESS;
    }

    protected function runAnomalyQueries(): Collection
    {
        return $this->detectPriceSpikes()
            ->concat($this->detectDemandCollapses())
            ->concat($this->detectSectorOutperformance())
            ->concat($this->detectMomentumReversal())
            ->values();
    }

    protected function detectPriceSpikes(): Collection
    {
        return $this->priceSpikeRows()
            ->map(function (array $row): array {
                return [
                    'area_type' => 'postcode',
                    'area_code' => $row['area_code'],
                    'insight_type' => 'price_spike',
                    'metric_value' => round($row['growth'], 2),
                    'transactions' => $row['sales'],
                    'period_start' => $row['period_start'],
                    'period_end' => $row['period_end'],
                    'supporting_data' => $row,
                    'insight_text' => str_replace(
                        'Average property prices',
                        'Median property prices',
                        $this->insightWriter->priceSpike([
                            'area_code' => $row['area_code'],
                            'growth' => number_format($row['growth'], 1, '.', ''),
                            'sales' => $row['sales'],
                        ])
                    ),
                ];
            });
    }

    protected function priceSpikeRows(): Collection
    {
        $periods = $this->rollingPeriods();
        if ($periods === null) {
            return collect();
        }

        $dateColumn = $this->column('Date');
        $postcodeColumn = $this->column('Postcode');
        $priceColumn = $this->column('Price');
        $categoryColumn = $this->column('PPDCategoryType');
        $newBuildColumn = $this->column('NewBuild');
        $areaCodeExpression = "regexp_replace(TRIM({$postcodeColumn}), '\\s.*$', '')";
        $growthExpression = '(100.0 * (current_period.median_price - previous_period.median_price) / NULLIF(previous_period.median_price, 0))';

        $sql = <<<SQL
WITH current_period AS (
    SELECT
        {$areaCodeExpression} AS postcode,
        COUNT(*) AS sales,
        percentile_cont(0.5) WITHIN GROUP (ORDER BY {$priceColumn}) AS median_price
    FROM land_registry
    WHERE {$categoryColumn} = ?
      AND {$postcodeColumn} IS NOT NULL
      AND TRIM({$postcodeColumn}) <> ''
      AND {$dateColumn} IS NOT NULL
      AND {$priceColumn} IS NOT NULL
      AND {$priceColumn} > 0
      AND {$newBuildColumn} = ?
      AND {$dateColumn} BETWEEN ? AND ?
    GROUP BY {$areaCodeExpression}
),
previous_period AS (
    SELECT
        {$areaCodeExpression} AS postcode,
        COUNT(*) AS sales,
        percentile_cont(0.5) WITHIN GROUP (ORDER BY {$priceColumn}) AS median_price
    FROM land_registry
    WHERE {$categoryColumn} = ?
      AND {$postcodeColumn} IS NOT NULL
      AND TRIM({$postcodeColumn}) <> ''
      AND {$dateColumn} IS NOT NULL
      AND {$priceColumn} IS NOT NULL
      AND {$priceColumn} > 0
      AND {$newBuildColumn} = ?
      AND {$dateColumn} BETWEEN ? AND ?
    GROUP BY {$areaCodeExpression}
)
SELECT
    current_period.postcode,
    current_period.sales,
    current_period.median_price,
    previous_period.median_price AS previous_median_price,
    {$growthExpression} AS growth
FROM current_period
INNER JOIN previous_period
    ON previous_period.postcode = current_period.postcode
WHERE current_period.sales >= ?
  AND previous_period.median_price > 0
  AND {$growthExpression} > ?
ORDER BY growth DESC, current_period.postcode ASC
SQL;

        return collect(DB::select($sql, [
            'A',
            'N',
            $periods['current_start']->toDateString(),
            $periods['current_end']->toDateString(),
            'A',
            'N',
            $periods['previous_start']->toDateString(),
            $periods['previous_end']->toDateString(),
            5,
            self::PRICE_SPIKE_THRESHOLD,
        ]))->map(function (object $row) use ($periods): array {
            return [
                'area_code' => (string) $row->postcode,
                'sales' => (int) $row->sales,
                'current_median_price' => (float) $row->median_price,
                'previous_median_price' => (float) $row->previous_median_price,
                'growth' => (float) $row->growth,
                'period_start' => $periods['current_start']->copy(),
                'period_end' => $periods['current_end']->copy(),
            ];
        })->values();
    }

    protected function detectDemandCollapses(): Collection
    {
        return $this->demandCollapseRows()
            ->map(function (array $row): array {
                return [
                    'area_type' => 'postcode',
                    'area_code' => $row['area_code'],
                    'insight_type' => 'demand_collapse',
                    'metric_value' => round($row['sales_change'], 2),
                    'transactions' => $row['sales'],
                    'period_start' => $row['period_start'],
                    'period_end' => $row['period_end'],
                    'supporting_data' => $row,
                    'insight_text' => $this->insightWriter->demandCollapse([
                        'area_code' => $row['area_code'],
                        'sales_change' => number_format(abs($row['sales_change']), 1, '.', ''),
                        'sales' => $row['sales'],
                    ]),
                ];
            });
    }

    protected function demandCollapseRows(): Collection
    {
        $periods = $this->rollingPeriods();
        if ($periods === null) {
            return collect();
        }

        $dateColumn = $this->column('Date');
        $postcodeColumn = $this->column('Postcode');
        $categoryColumn = $this->column('PPDCategoryType');
        $areaCodeExpression = "regexp_replace(TRIM({$postcodeColumn}), '\\s.*$', '')";
        $salesChangeExpression = '(100.0 * (current_period.sales - previous_period.sales) / NULLIF(previous_period.sales, 0))';

        $sql = <<<SQL
WITH current_period AS (
    SELECT
        {$areaCodeExpression} AS postcode,
        COUNT(*) AS sales
    FROM land_registry
    WHERE {$categoryColumn} = ?
      AND {$postcodeColumn} IS NOT NULL
      AND {$dateColumn} IS NOT NULL
      AND {$dateColumn} BETWEEN ? AND ?
    GROUP BY {$areaCodeExpression}
),
previous_period AS (
    SELECT
        {$areaCodeExpression} AS postcode,
        COUNT(*) AS sales
    FROM land_registry
    WHERE {$categoryColumn} = ?
      AND {$postcodeColumn} IS NOT NULL
      AND {$dateColumn} IS NOT NULL
      AND {$dateColumn} BETWEEN ? AND ?
    GROUP BY {$areaCodeExpression}
)
SELECT
    current_period.postcode,
    current_period.sales,
    previous_period.sales AS previous_sales,
    {$salesChangeExpression} AS sales_change
FROM current_period
INNER JOIN previous_period
    ON previous_period.postcode = current_period.postcode
WHERE current_period.sales >= ?
  AND previous_period.sales > 0
  AND {$salesChangeExpression} < ?
ORDER BY sales_change ASC, current_period.postcode ASC
SQL;

        return collect(DB::select($sql, [
            'A',
            $periods['current_start']->toDateString(),
            $periods['current_end']->toDateString(),
            'A',
            $periods['previous_start']->toDateString(),
            $periods['previous_end']->toDateString(),
            20,
            self::DEMAND_COLLAPSE_THRESHOLD,
        ]))->map(function (object $row) use ($periods): array {
            return [
                'area_code' => (string) $row->postcode,
                'sales' => (int) $row->sales,
                'previous_sales' => (int) $row->previous_sales,
                'sales_change' => (float) $row->sales_change,
                'period_start' => $periods['current_start']->copy(),
                'period_end' => $periods['current_end']->copy(),
            ];
        })->values();
    }

    protected function detectSectorOutperformance(): Collection
    {
        return $this->sectorOutperformanceRows()
            ->map(function (array $row): array {
                return [
                    'area_type' => 'postcode_sector',
                    'area_code' => $row['area_code'],
                    'insight_type' => 'sector_outperformance',
                    'metric_value' => round($row['sector_growth'], 2),
                    'transactions' => $row['sales'],
                    'period_start' => $row['period_start'],
                    'period_end' => $row['period_end'],
                    'supporting_data' => $row,
                    'insight_text' => $this->insightWriter->sectorOutperformance([
                        'area_code' => $row['area_code'],
                        'sector_growth' => number_format($row['sector_growth'], 1, '.', ''),
                        'uk_growth' => number_format($row['uk_growth'], 1, '.', ''),
                        'sales' => $row['sales'],
                    ]),
                ];
            });
    }

    protected function detectMomentumReversal(): Collection
    {
        return $this->momentumReversalRows()
            ->map(function (array $row): array {
                $periodStart = Carbon::create($row['period_year'], 1, 1)->startOfDay();
                $periodEnd = Carbon::create($row['period_year'], 12, 31)->startOfDay();

                return [
                    'area_type' => 'postcode_sector',
                    'area_code' => $row['area_code'],
                    'insight_type' => 'momentum_reversal',
                    'metric_value' => round($row['current_growth'], 2),
                    'transactions' => $row['sales'],
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'supporting_data' => $row,
                    'insight_text' => $this->insightWriter->momentumReversal([
                        'area_code' => $row['area_code'],
                        'sales' => $row['sales'],
                        'current_year' => $row['period_year'],
                        'previous_year' => $row['previous_year'],
                    ]),
                ];
            });
    }

    protected function sectorOutperformanceRows(): Collection
    {
        if (! Schema::hasTable('hpi_monthly')) {
            return collect();
        }

        $periods = $this->rollingPeriods();
        if ($periods === null) {
            return collect();
        }

        $dateColumn = $this->column('Date');
        $postcodeColumn = $this->column('Postcode');
        $priceColumn = $this->column('Price');
        $categoryColumn = $this->column('PPDCategoryType');
        $newBuildColumn = $this->column('NewBuild');
        $hpiDateColumn = $this->column('Date');
        $hpiRegionColumn = $this->column('RegionName');
        $hpiIndexColumn = $this->column('Index');
        $sectorExpression = $this->sectorExpression($postcodeColumn);
        $sectorGrowthExpression = '(100.0 * (current_period.avg_price - previous_period.avg_price) / NULLIF(previous_period.avg_price, 0))';
        $differenceExpression = '(sector_growth.sector_growth - uk_growth.uk_growth)';

        $sql = <<<SQL
WITH current_period AS (
    SELECT
        {$sectorExpression} AS sector,
        COUNT(*) AS sales,
        AVG({$priceColumn}) AS avg_price
    FROM land_registry
    WHERE {$categoryColumn} = ?
      AND {$newBuildColumn} = ?
      AND {$postcodeColumn} IS NOT NULL
      AND TRIM({$postcodeColumn}) <> ''
      AND {$dateColumn} IS NOT NULL
      AND {$priceColumn} IS NOT NULL
      AND {$priceColumn} > 0
      AND {$dateColumn} BETWEEN ? AND ?
    GROUP BY {$sectorExpression}
),
previous_period AS (
    SELECT
        {$sectorExpression} AS sector,
        COUNT(*) AS sales,
        AVG({$priceColumn}) AS avg_price
    FROM land_registry
    WHERE {$categoryColumn} = ?
      AND {$newBuildColumn} = ?
      AND {$postcodeColumn} IS NOT NULL
      AND TRIM({$postcodeColumn}) <> ''
      AND {$dateColumn} IS NOT NULL
      AND {$priceColumn} IS NOT NULL
      AND {$priceColumn} > 0
      AND {$dateColumn} BETWEEN ? AND ?
    GROUP BY {$sectorExpression}
),
sector_growth AS (
    SELECT
        current_period.sector,
        current_period.sales,
        {$sectorGrowthExpression} AS sector_growth
    FROM current_period
    INNER JOIN previous_period
        ON previous_period.sector = current_period.sector
    WHERE previous_period.avg_price > 0
),
uk_anchor AS (
    SELECT MAX({$hpiDateColumn}) AS current_hpi_date
    FROM hpi_monthly
    WHERE {$hpiRegionColumn} = ?
      AND {$hpiIndexColumn} IS NOT NULL
      AND {$hpiDateColumn} IS NOT NULL
      AND {$hpiDateColumn} <= ?
),
uk_growth AS (
    SELECT
        (100.0 * (current_hpi.uk_index - previous_hpi.uk_index) / NULLIF(previous_hpi.uk_index, 0)) AS uk_growth
    FROM uk_anchor
    INNER JOIN (
        SELECT {$hpiDateColumn} AS hpi_date, {$hpiIndexColumn} AS uk_index
        FROM hpi_monthly
        WHERE {$hpiRegionColumn} = ?
          AND {$hpiIndexColumn} IS NOT NULL
          AND {$hpiDateColumn} IS NOT NULL
    ) AS current_hpi
        ON current_hpi.hpi_date = uk_anchor.current_hpi_date
    INNER JOIN (
        SELECT {$hpiDateColumn} AS hpi_date, {$hpiIndexColumn} AS uk_index
        FROM hpi_monthly
        WHERE {$hpiRegionColumn} = ?
          AND {$hpiIndexColumn} IS NOT NULL
          AND {$hpiDateColumn} IS NOT NULL
    ) AS previous_hpi
        ON previous_hpi.hpi_date = (
            SELECT MAX(previous_lookup.{$hpiDateColumn})
            FROM hpi_monthly AS previous_lookup
            WHERE previous_lookup.{$hpiRegionColumn} = {$this->quote('United Kingdom')}
              AND previous_lookup.{$hpiIndexColumn} IS NOT NULL
              AND previous_lookup.{$hpiDateColumn} <= (uk_anchor.current_hpi_date - INTERVAL '1 year')
        )
    WHERE uk_anchor.current_hpi_date IS NOT NULL
      AND previous_hpi.uk_index > 0
)
SELECT
    sector_growth.sector,
    sector_growth.sales,
    sector_growth.sector_growth,
    uk_growth.uk_growth,
    {$differenceExpression} AS diff
FROM sector_growth
CROSS JOIN uk_growth
WHERE sector_growth.sales >= ?
  AND {$differenceExpression} >= ?
ORDER BY diff DESC, sector_growth.sector ASC
SQL;

        return collect(DB::select($sql, [
            'A',
            'N',
            $periods['current_start']->toDateString(),
            $periods['current_end']->toDateString(),
            'A',
            'N',
            $periods['previous_start']->toDateString(),
            $periods['previous_end']->toDateString(),
            'United Kingdom',
            $periods['current_end']->toDateString(),
            'United Kingdom',
            'United Kingdom',
            20,
            self::OUTPERFORMANCE_THRESHOLD,
        ]))->map(function (object $row) use ($periods): array {
            return [
                'area_code' => (string) $row->sector,
                'sales' => (int) $row->sales,
                'sector_growth' => (float) $row->sector_growth,
                'uk_growth' => (float) $row->uk_growth,
                'period_start' => $periods['current_start']->copy(),
                'period_end' => $periods['current_end']->copy(),
            ];
        })->values();
    }

    protected function momentumReversalRows(): Collection
    {
        $latestYear = DB::table('land_registry')
            ->selectRaw('MAX(EXTRACT(YEAR FROM "Date")) as yr')
            ->value('yr');

        if ($latestYear === null) {
            return collect();
        }

        $targetYear = (int) $latestYear - 1;
        $earliestYear = $targetYear - 2;
        $dateColumn = $this->column('Date');
        $postcodeColumn = $this->column('Postcode');
        $priceColumn = $this->column('Price');
        $categoryColumn = $this->column('PPDCategoryType');
        $newBuildColumn = $this->column('NewBuild');
        $sectorExpression = $this->sectorExpression($postcodeColumn);

        $sql = <<<SQL
WITH yearly_prices AS (
    SELECT
        {$sectorExpression} AS sector,
        EXTRACT(YEAR FROM {$dateColumn}) AS yr,
        COUNT(*) AS sales,
        percentile_cont(0.5) WITHIN GROUP (ORDER BY {$priceColumn}) AS median_price
    FROM land_registry
    WHERE {$categoryColumn} = ?
      AND {$newBuildColumn} = ?
      AND {$postcodeColumn} IS NOT NULL
      AND TRIM({$postcodeColumn}) <> ''
      AND {$dateColumn} IS NOT NULL
      AND {$priceColumn} IS NOT NULL
      AND {$priceColumn} > 0
      AND EXTRACT(YEAR FROM {$dateColumn}) >= ?
    GROUP BY {$sectorExpression}, EXTRACT(YEAR FROM {$dateColumn})
),
growth AS (
    SELECT
        sector,
        yr,
        sales,
        median_price,
        LAG(median_price) OVER (PARTITION BY sector ORDER BY yr) AS prev_price,
        LAG(median_price, 2) OVER (PARTITION BY sector ORDER BY yr) AS prev2_price
    FROM yearly_prices
)
SELECT
    sector,
    yr,
    sales,
    median_price,
    prev_price,
    prev2_price,
    (100.0 * (prev_price - prev2_price) / NULLIF(prev2_price, 0)) AS previous_growth,
    (100.0 * (median_price - prev_price) / NULLIF(prev_price, 0)) AS current_growth
FROM growth
WHERE yr = ?
  AND sales >= ?
  AND prev_price IS NOT NULL
  AND prev2_price IS NOT NULL
  AND (100.0 * (prev_price - prev2_price) / NULLIF(prev2_price, 0)) > ?
  AND (100.0 * (median_price - prev_price) / NULLIF(prev_price, 0)) < ?
ORDER BY sector ASC
SQL;

        return collect(DB::select($sql, [
            'A',
            'N',
            $earliestYear,
            $targetYear,
            30,
            10,
            -5,
        ]))->map(function (object $row): array {
            return [
                'area_code' => (string) $row->sector,
                'sales' => (int) $row->sales,
                'period_year' => (int) $row->yr,
                'previous_year' => (int) $row->yr - 1,
                'median_price' => (float) $row->median_price,
                'previous_median_price' => (float) $row->prev_price,
                'previous_two_year_median_price' => (float) $row->prev2_price,
                'previous_growth' => (float) $row->previous_growth,
                'current_growth' => (float) $row->current_growth,
            ];
        })->values();
    }

    protected function insightExists(array $row): bool
    {
        return MarketInsight::query()
            ->where('area_code', $row['area_code'])
            ->where('insight_type', $row['insight_type'])
            ->whereDate('period_end', $row['period_end'])
            ->exists();
    }

    protected function column(string $name): string
    {
        return DB::connection()->getQueryGrammar()->wrap($name);
    }

    protected function sectorExpression(string $postcodeColumn): string
    {
        return "regexp_replace(upper(regexp_replace(TRIM({$postcodeColumn}), '\\s+', '', 'g')), '[A-Z]{2}$', '')";
    }

    protected function quote(string $value): string
    {
        return DB::getPdo()->quote($value);
    }

    /**
     * @return array{current_start: Carbon, current_end: Carbon, previous_start: Carbon, previous_end: Carbon}|null
     */
    protected function rollingPeriods(): ?array
    {
        $latestDate = DB::table('land_registry')
            ->whereNotNull('Date')
            ->max('Date');

        if (! is_string($latestDate) || $latestDate === '') {
            return null;
        }

        $currentEnd = Carbon::parse($latestDate)->startOfDay();
        $currentStart = $currentEnd->copy()->addDay()->subYear()->startOfDay();
        $previousEnd = $currentStart->copy()->subDay()->startOfDay();
        $previousStart = $currentStart->copy()->subYear()->startOfDay();

        return [
            'current_start' => $currentStart,
            'current_end' => $currentEnd,
            'previous_start' => $previousStart,
            'previous_end' => $previousEnd,
        ];
    }
}
