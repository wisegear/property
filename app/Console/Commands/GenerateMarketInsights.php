<?php

namespace App\Console\Commands;

use App\Models\MarketInsight;
use App\Services\InsightWriter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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

    private const PRICE_COLLAPSE_THRESHOLD = -15.0;

    private const DEMAND_COLLAPSE_THRESHOLD = -30.0;

    private const LIQUIDITY_SURGE_THRESHOLD = 50.0;

    private const MARKET_FREEZE_THRESHOLD = -50.0;

    private const HOTSPOT_OUTPERFORMANCE_MARGIN = 12.0;

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

        Cache::flush();
        Cache::forever('insights:cache_version', (int) Cache::get('insights:cache_version', 1) + 1);

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

        $sectors = DB::table('market_insights')
            ->select('area_code')
            ->distinct()
            ->orderBy('area_code')
            ->pluck('area_code');

        Cache::put('insights:sectors', $sectors, now()->addDays(45));
        $this->call('insights:warm');

        return self::SUCCESS;
    }

    protected function runAnomalyQueries(): Collection
    {
        return $this->detectPriceSpikes()
            ->concat($this->detectPriceCollapses())
            ->concat($this->detectDemandCollapses())
            ->concat($this->detectLiquiditySurges())
            ->concat($this->detectMarketFreezes())
            ->concat($this->detectSectorOutperformance())
            ->concat($this->detectMomentumReversal())
            ->concat($this->detectUnexpectedHotspots())
            ->values();
    }

    protected function detectPriceSpikes(): Collection
    {
        return $this->priceSpikeRows()
            ->map(function (array $row): array {
                $periodLabel = $this->periodLabel($row['period_start'], $row['period_end']);

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
                            'period_label' => $periodLabel,
                        ])
                    ),
                ];
            });
    }

    protected function priceSpikeRows(): Collection
    {
        return $this->priceTrendRows('>=', self::PRICE_SPIKE_THRESHOLD, 5);
    }

    protected function detectDemandCollapses(): Collection
    {
        return $this->demandCollapseRows()
            ->map(function (array $row): array {
                $periodLabel = $this->periodLabel($row['period_start'], $row['period_end']);

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
                        'period_label' => $periodLabel,
                    ]),
                ];
            });
    }

    protected function detectPriceCollapses(): Collection
    {
        return $this->priceCollapseRows()
            ->map(function (array $row): array {
                return [
                    'area_type' => 'postcode',
                    'area_code' => $row['area_code'],
                    'insight_type' => 'price_collapse',
                    'metric_value' => round($row['growth'], 2),
                    'transactions' => $row['sales'],
                    'period_start' => $row['period_start'],
                    'period_end' => $row['period_end'],
                    'supporting_data' => $row,
                    'insight_text' => $this->insightWriter->priceCollapse([
                        'area_code' => $row['area_code'],
                        'growth' => number_format(abs($row['growth']), 1, '.', ''),
                        'previous_price' => number_format($row['previous_median_price'], 0, '.', ','),
                        'current_price' => number_format($row['current_median_price'], 0, '.', ','),
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
        $newBuildColumn = $this->column('NewBuild');
        $areaCodeExpression = "regexp_replace(TRIM({$postcodeColumn}), '\\s.*$', '')";
        $salesChangeExpression = '(100.0 * (current_period.sales - previous_period.sales) / NULLIF(previous_period.sales, 0))';

        $sql = <<<SQL
WITH current_period AS (
    SELECT
        {$areaCodeExpression} AS postcode,
        COUNT(*) AS sales
    FROM land_registry
    WHERE {$categoryColumn} = ?
      AND {$newBuildColumn} = ?
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
      AND {$newBuildColumn} = ?
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
            'N',
            $periods['current_start']->toDateString(),
            $periods['current_end']->toDateString(),
            'A',
            'N',
            $periods['previous_start']->toDateString(),
            $periods['previous_end']->toDateString(),
            $this->minSectorTransactions(),
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

    protected function detectLiquiditySurges(): Collection
    {
        return $this->liquiditySurgeRows()
            ->map(function (array $row): array {
                $periodLabel = $this->periodLabel($row['period_start'], $row['period_end']);

                return [
                    'area_type' => 'postcode',
                    'area_code' => $row['area_code'],
                    'insight_type' => 'liquidity_surge',
                    'metric_value' => round($row['sales_change'], 2),
                    'transactions' => $row['sales'],
                    'period_start' => $row['period_start'],
                    'period_end' => $row['period_end'],
                    'supporting_data' => $row,
                    'insight_text' => $this->insightWriter->liquiditySurge([
                        'area_code' => $row['area_code'],
                        'sales_change' => number_format($row['sales_change'], 1, '.', ''),
                        'period_label' => $periodLabel,
                    ]),
                ];
            });
    }

    protected function detectMarketFreezes(): Collection
    {
        return $this->marketFreezeRows()
            ->map(function (array $row): array {
                $periodLabel = $this->periodLabel($row['period_start'], $row['period_end']);

                return [
                    'area_type' => 'postcode',
                    'area_code' => $row['area_code'],
                    'insight_type' => 'market_freeze',
                    'metric_value' => round($row['sales_change'], 2),
                    'transactions' => $row['sales'],
                    'period_start' => $row['period_start'],
                    'period_end' => $row['period_end'],
                    'supporting_data' => $row,
                    'insight_text' => $this->insightWriter->marketFreeze([
                        'area_code' => $row['area_code'],
                        'sales_change' => number_format(abs($row['sales_change']), 1, '.', ''),
                        'period_label' => $periodLabel,
                    ]),
                ];
            });
    }

    protected function detectSectorOutperformance(): Collection
    {
        return $this->sectorOutperformanceRows()
            ->map(function (array $row): array {
                $periodLabel = $this->periodLabel($row['period_start'], $row['period_end']);

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
                        'period_label' => $periodLabel,
                    ]),
                ];
            });
    }

    protected function detectMomentumReversal(): Collection
    {
        return $this->momentumReversalRows()
            ->map(function (array $row): array {
                $periodLabel = $this->periodLabel($row['period_start'], $row['period_end']);
                $previousPeriodLabel = $this->periodLabel($row['previous_period_start'], $row['previous_period_end']);

                return [
                    'area_type' => 'postcode_sector',
                    'area_code' => $row['area_code'],
                    'insight_type' => 'momentum_reversal',
                    'metric_value' => round($row['current_growth'], 2),
                    'transactions' => $row['sales'],
                    'period_start' => $row['period_start'],
                    'period_end' => $row['period_end'],
                    'supporting_data' => $row,
                    'insight_text' => $this->insightWriter->momentumReversal([
                        'area_code' => $row['area_code'],
                        'sales' => $row['sales'],
                        'current_period_label' => $periodLabel,
                        'previous_period_label' => $previousPeriodLabel,
                    ]),
                ];
            });
    }

    protected function detectUnexpectedHotspots(): Collection
    {
        return $this->unexpectedHotspotRows()
            ->map(function (array $row): array {
                return [
                    'area_type' => 'postcode_sector',
                    'area_code' => $row['area_code'],
                    'insight_type' => 'unexpected_hotspot',
                    'metric_value' => round($row['sector_growth'], 2),
                    'transactions' => $row['sales'],
                    'period_start' => $row['period_start'],
                    'period_end' => $row['period_end'],
                    'supporting_data' => $row,
                    'insight_text' => $this->insightWriter->unexpectedHotspot([
                        'area_code' => $row['area_code'],
                        'sector_growth' => number_format($row['sector_growth'], 1, '.', ''),
                        'uk_growth' => number_format($row['uk_growth'], 1, '.', ''),
                    ]),
                ];
            });
    }

    protected function priceCollapseRows(): Collection
    {
        return $this->priceTrendRows('<=', self::PRICE_COLLAPSE_THRESHOLD, $this->minSectorTransactions());
    }

    protected function liquiditySurgeRows(): Collection
    {
        return $this->salesChangeRows('>=', self::LIQUIDITY_SURGE_THRESHOLD, false);
    }

    protected function marketFreezeRows(): Collection
    {
        return $this->salesChangeRows('<=', self::MARKET_FREEZE_THRESHOLD, false);
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
        $currentSectorGrowthExpression = '(100.0 * (current_period.avg_price - previous_period.avg_price) / NULLIF(previous_period.avg_price, 0))';
        $previousSectorGrowthExpression = '(100.0 * (previous_period.avg_price - earlier_period.avg_price) / NULLIF(earlier_period.avg_price, 0))';
        $currentDifferenceExpression = '(sector_growth.current_sector_growth - uk_growth.current_uk_growth)';
        $previousDifferenceExpression = '(sector_growth.previous_sector_growth - uk_growth.previous_uk_growth)';

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
earlier_period AS (
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
        {$currentSectorGrowthExpression} AS current_sector_growth,
        {$previousSectorGrowthExpression} AS previous_sector_growth
    FROM current_period
    INNER JOIN previous_period
        ON previous_period.sector = current_period.sector
    INNER JOIN earlier_period
        ON earlier_period.sector = current_period.sector
    WHERE previous_period.avg_price > 0
      AND earlier_period.avg_price > 0
),
current_uk_anchor AS (
    SELECT MAX({$hpiDateColumn}) AS hpi_date
    FROM hpi_monthly
    WHERE {$hpiRegionColumn} = ?
      AND {$hpiIndexColumn} IS NOT NULL
      AND {$hpiDateColumn} IS NOT NULL
      AND {$hpiDateColumn} <= ?
),
uk_growth AS (
    SELECT
        (100.0 * (current_hpi.uk_index - previous_hpi.uk_index) / NULLIF(previous_hpi.uk_index, 0)) AS current_uk_growth,
        (100.0 * (anchored_hpi.uk_index - earlier_hpi.uk_index) / NULLIF(earlier_hpi.uk_index, 0)) AS previous_uk_growth
    FROM current_uk_anchor
    INNER JOIN (
        SELECT {$hpiDateColumn} AS hpi_date, {$hpiIndexColumn} AS uk_index
        FROM hpi_monthly
        WHERE {$hpiRegionColumn} = ?
          AND {$hpiIndexColumn} IS NOT NULL
          AND {$hpiDateColumn} IS NOT NULL
    ) AS current_hpi
        ON current_hpi.hpi_date = current_uk_anchor.hpi_date
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
              AND previous_lookup.{$hpiDateColumn} <= (current_uk_anchor.hpi_date - INTERVAL '1 year')
        )
    INNER JOIN (
        SELECT {$hpiDateColumn} AS hpi_date, {$hpiIndexColumn} AS uk_index
        FROM hpi_monthly
        WHERE {$hpiRegionColumn} = ?
          AND {$hpiIndexColumn} IS NOT NULL
          AND {$hpiDateColumn} IS NOT NULL
    ) AS anchored_hpi
        ON anchored_hpi.hpi_date = previous_hpi.hpi_date
    INNER JOIN (
        SELECT {$hpiDateColumn} AS hpi_date, {$hpiIndexColumn} AS uk_index
        FROM hpi_monthly
        WHERE {$hpiRegionColumn} = ?
          AND {$hpiIndexColumn} IS NOT NULL
          AND {$hpiDateColumn} IS NOT NULL
    ) AS earlier_hpi
        ON earlier_hpi.hpi_date = (
            SELECT MAX(earlier_lookup.{$hpiDateColumn})
            FROM hpi_monthly AS earlier_lookup
            WHERE earlier_lookup.{$hpiRegionColumn} = {$this->quote('United Kingdom')}
              AND earlier_lookup.{$hpiIndexColumn} IS NOT NULL
              AND earlier_lookup.{$hpiDateColumn} <= (previous_hpi.hpi_date - INTERVAL '1 year')
        )
    WHERE current_uk_anchor.hpi_date IS NOT NULL
      AND previous_hpi.uk_index > 0
      AND earlier_hpi.uk_index > 0
)
SELECT
    sector_growth.sector,
    sector_growth.sales,
    sector_growth.current_sector_growth AS sector_growth,
    sector_growth.previous_sector_growth,
    uk_growth.current_uk_growth AS uk_growth,
    uk_growth.previous_uk_growth,
    {$currentDifferenceExpression} AS current_diff,
    {$previousDifferenceExpression} AS previous_diff
FROM sector_growth
CROSS JOIN uk_growth
WHERE sector_growth.sales >= ?
  AND {$currentDifferenceExpression} >= ?
  AND {$previousDifferenceExpression} >= ?
ORDER BY current_diff DESC, sector_growth.sector ASC
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
            'A',
            'N',
            $periods['earlier_start']->toDateString(),
            $periods['earlier_end']->toDateString(),
            'United Kingdom',
            $periods['current_end']->toDateString(),
            'United Kingdom',
            'United Kingdom',
            'United Kingdom',
            'United Kingdom',
            $this->minSectorTransactions(),
            self::OUTPERFORMANCE_THRESHOLD,
            self::OUTPERFORMANCE_THRESHOLD,
        ]))->map(function (object $row) use ($periods): array {
            return [
                'area_code' => (string) $row->sector,
                'sales' => (int) $row->sales,
                'sector_growth' => (float) $row->sector_growth,
                'previous_sector_growth' => (float) $row->previous_sector_growth,
                'uk_growth' => (float) $row->uk_growth,
                'previous_uk_growth' => (float) $row->previous_uk_growth,
                'period_start' => $periods['current_start']->copy(),
                'period_end' => $periods['current_end']->copy(),
            ];
        })->values();
    }

    protected function unexpectedHotspotRows(): Collection
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
        $sectorExpression = $this->sectorExpression($postcodeColumn);
        $currentSectorGrowthExpression = '(100.0 * (current_period.median_price - previous_period.median_price) / NULLIF(previous_period.median_price, 0))';
        $previousSectorGrowthExpression = '(100.0 * (previous_period.median_price - earlier_period.median_price) / NULLIF(earlier_period.median_price, 0))';
        $currentUkGrowthExpression = '(100.0 * (uk_current.median_price - uk_previous.median_price) / NULLIF(uk_previous.median_price, 0))';
        $previousUkGrowthExpression = '(100.0 * (uk_previous.median_price - uk_earlier.median_price) / NULLIF(uk_earlier.median_price, 0))';

        $sql = <<<SQL
WITH current_period AS (
    SELECT
        {$sectorExpression} AS sector,
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
      AND {$dateColumn} BETWEEN ? AND ?
    GROUP BY {$sectorExpression}
),
previous_period AS (
    SELECT
        {$sectorExpression} AS sector,
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
      AND {$dateColumn} BETWEEN ? AND ?
    GROUP BY {$sectorExpression}
),
earlier_period AS (
    SELECT
        {$sectorExpression} AS sector,
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
      AND {$dateColumn} BETWEEN ? AND ?
    GROUP BY {$sectorExpression}
),
uk_current AS (
    SELECT percentile_cont(0.5) WITHIN GROUP (ORDER BY {$priceColumn}) AS median_price
    FROM land_registry
    WHERE {$categoryColumn} = ?
      AND {$newBuildColumn} = ?
      AND {$dateColumn} IS NOT NULL
      AND {$priceColumn} IS NOT NULL
      AND {$priceColumn} > 0
      AND {$dateColumn} BETWEEN ? AND ?
),
uk_previous AS (
    SELECT percentile_cont(0.5) WITHIN GROUP (ORDER BY {$priceColumn}) AS median_price
    FROM land_registry
    WHERE {$categoryColumn} = ?
      AND {$newBuildColumn} = ?
      AND {$dateColumn} IS NOT NULL
      AND {$priceColumn} IS NOT NULL
      AND {$priceColumn} > 0
      AND {$dateColumn} BETWEEN ? AND ?
),
uk_earlier AS (
    SELECT percentile_cont(0.5) WITHIN GROUP (ORDER BY {$priceColumn}) AS median_price
    FROM land_registry
    WHERE {$categoryColumn} = ?
      AND {$newBuildColumn} = ?
      AND {$dateColumn} IS NOT NULL
      AND {$priceColumn} IS NOT NULL
      AND {$priceColumn} > 0
      AND {$dateColumn} BETWEEN ? AND ?
)
SELECT
    current_period.sector,
    current_period.sales,
    current_period.median_price AS sector_median_price,
    previous_period.median_price AS previous_sector_median_price,
    earlier_period.median_price AS earlier_sector_median_price,
    uk_current.median_price AS uk_median_price,
    uk_previous.median_price AS previous_uk_median_price,
    {$currentSectorGrowthExpression} AS sector_growth,
    {$previousSectorGrowthExpression} AS previous_sector_growth,
    {$currentUkGrowthExpression} AS uk_growth,
    {$previousUkGrowthExpression} AS previous_uk_growth
FROM current_period
INNER JOIN previous_period
    ON previous_period.sector = current_period.sector
INNER JOIN earlier_period
    ON earlier_period.sector = current_period.sector
CROSS JOIN uk_current
CROSS JOIN uk_previous
CROSS JOIN uk_earlier
WHERE current_period.sales >= ?
  AND previous_period.sales >= ?
  AND previous_period.median_price > 0
  AND earlier_period.median_price > 0
  AND uk_current.median_price IS NOT NULL
  AND uk_previous.median_price > 0
  AND uk_earlier.median_price > 0
  AND current_period.median_price < uk_current.median_price
  AND previous_period.median_price < uk_previous.median_price
  AND {$currentSectorGrowthExpression} >= ({$currentUkGrowthExpression} + ?)
  AND {$previousSectorGrowthExpression} >= ({$previousUkGrowthExpression} + ?)
ORDER BY sector_growth DESC, current_period.sector ASC
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
            'A',
            'N',
            $periods['earlier_start']->toDateString(),
            $periods['earlier_end']->toDateString(),
            'A',
            'N',
            $periods['current_start']->toDateString(),
            $periods['current_end']->toDateString(),
            'A',
            'N',
            $periods['previous_start']->toDateString(),
            $periods['previous_end']->toDateString(),
            'A',
            'N',
            $periods['earlier_start']->toDateString(),
            $periods['earlier_end']->toDateString(),
            $this->minSectorTransactions(),
            $this->minSectorTransactions(),
            self::HOTSPOT_OUTPERFORMANCE_MARGIN,
            self::HOTSPOT_OUTPERFORMANCE_MARGIN,
        ]))->map(function (object $row) use ($periods): array {
            return [
                'area_code' => (string) $row->sector,
                'sales' => (int) $row->sales,
                'sector_median_price' => (float) $row->sector_median_price,
                'previous_sector_median_price' => (float) $row->previous_sector_median_price,
                'earlier_sector_median_price' => (float) $row->earlier_sector_median_price,
                'uk_median_price' => (float) $row->uk_median_price,
                'previous_uk_median_price' => (float) $row->previous_uk_median_price,
                'sector_growth' => (float) $row->sector_growth,
                'previous_sector_growth' => (float) $row->previous_sector_growth,
                'uk_growth' => (float) $row->uk_growth,
                'previous_uk_growth' => (float) $row->previous_uk_growth,
                'period_start' => $periods['current_start']->copy(),
                'period_end' => $periods['current_end']->copy(),
            ];
        })->values();
    }

    protected function momentumReversalRows(): Collection
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
        $sectorExpression = $this->sectorExpression($postcodeColumn);
        $earlierGrowthExpression = '(100.0 * (previous_period.median_price - earlier_period.median_price) / NULLIF(earlier_period.median_price, 0))';
        $currentGrowthExpression = '(100.0 * (current_period.median_price - previous_period.median_price) / NULLIF(previous_period.median_price, 0))';

        $sql = <<<SQL
WITH current_period AS (
    SELECT
        {$sectorExpression} AS sector,
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
      AND {$dateColumn} BETWEEN ? AND ?
    GROUP BY {$sectorExpression}
),
previous_period AS (
    SELECT
        {$sectorExpression} AS sector,
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
      AND {$dateColumn} BETWEEN ? AND ?
    GROUP BY {$sectorExpression}
),
earlier_period AS (
    SELECT
        {$sectorExpression} AS sector,
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
      AND {$dateColumn} BETWEEN ? AND ?
    GROUP BY {$sectorExpression}
)
SELECT
    current_period.sector,
    current_period.sales,
    current_period.median_price,
    previous_period.median_price AS previous_median_price,
    earlier_period.median_price AS earlier_median_price,
    {$earlierGrowthExpression} AS previous_growth,
    {$currentGrowthExpression} AS current_growth
FROM current_period
INNER JOIN previous_period
    ON previous_period.sector = current_period.sector
INNER JOIN earlier_period
    ON earlier_period.sector = current_period.sector
WHERE current_period.sales >= ?
  AND previous_period.median_price > 0
  AND earlier_period.median_price > 0
  AND {$earlierGrowthExpression} > ?
  AND {$currentGrowthExpression} < ?
ORDER BY sector ASC
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
            'A',
            'N',
            $periods['earlier_start']->toDateString(),
            $periods['earlier_end']->toDateString(),
            $this->minSectorTransactions(),
            15,
            -10,
        ]))->map(function (object $row) use ($periods): array {
            return [
                'area_code' => (string) $row->sector,
                'sales' => (int) $row->sales,
                'period_start' => $periods['current_start']->copy(),
                'period_end' => $periods['current_end']->copy(),
                'previous_period_start' => $periods['previous_start']->copy(),
                'previous_period_end' => $periods['previous_end']->copy(),
                'earlier_period_start' => $periods['earlier_start']->copy(),
                'earlier_period_end' => $periods['earlier_end']->copy(),
                'median_price' => (float) $row->median_price,
                'previous_median_price' => (float) $row->previous_median_price,
                'previous_two_period_median_price' => (float) $row->earlier_median_price,
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

    protected function periodLabel(Carbon $start, Carbon $end): string
    {
        return $start->format('d M Y').' to '.$end->format('d M Y');
    }

    /**
     * @return Collection<int, array{area_code:string,sales:int,previous_sales:int,sales_change:float,period_start:Carbon,period_end:Carbon}>
     */
    protected function salesChangeRows(string $operator, float $threshold, bool $requiresPersistence = false): Collection
    {
        $periods = $this->rollingPeriods();
        if ($periods === null) {
            return collect();
        }

        $dateColumn = $this->column('Date');
        $postcodeColumn = $this->column('Postcode');
        $categoryColumn = $this->column('PPDCategoryType');
        $newBuildColumn = $this->column('NewBuild');
        $areaCodeExpression = "regexp_replace(TRIM({$postcodeColumn}), '\\s.*$', '')";
        $currentSalesChangeExpression = '(100.0 * (current_period.sales - previous_period.sales) / NULLIF(previous_period.sales, 0))';
        $previousSalesChangeExpression = '(100.0 * (previous_period.sales - earlier_period.sales) / NULLIF(earlier_period.sales, 0))';

        $sql = <<<SQL
WITH current_period AS (
    SELECT
        {$areaCodeExpression} AS postcode,
        COUNT(*) AS sales
    FROM land_registry
    WHERE {$categoryColumn} = ?
      AND {$newBuildColumn} = ?
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
      AND {$newBuildColumn} = ?
      AND {$postcodeColumn} IS NOT NULL
      AND {$dateColumn} IS NOT NULL
      AND {$dateColumn} BETWEEN ? AND ?
    GROUP BY {$areaCodeExpression}
),
earlier_period AS (
    SELECT
        {$areaCodeExpression} AS postcode,
        COUNT(*) AS sales
    FROM land_registry
    WHERE {$categoryColumn} = ?
      AND {$newBuildColumn} = ?
      AND {$postcodeColumn} IS NOT NULL
      AND {$dateColumn} IS NOT NULL
      AND {$dateColumn} BETWEEN ? AND ?
    GROUP BY {$areaCodeExpression}
)
SELECT
    current_period.postcode,
    current_period.sales,
    previous_period.sales AS previous_sales,
    earlier_period.sales AS earlier_sales,
    {$currentSalesChangeExpression} AS sales_change,
    {$previousSalesChangeExpression} AS previous_sales_change
FROM current_period
INNER JOIN previous_period
    ON previous_period.postcode = current_period.postcode
INNER JOIN earlier_period
    ON earlier_period.postcode = current_period.postcode
WHERE current_period.sales >= ?
  AND previous_period.sales > 0
  AND {$currentSalesChangeExpression} {$operator} ?
SQL;

        if ($requiresPersistence) {
            $sql .= "\n  AND previous_period.sales >= ?\n  AND earlier_period.sales > 0\n  AND {$previousSalesChangeExpression} {$operator} ?";
        }

        $sql .= $operator === '<='
            ? "\nORDER BY sales_change ASC, current_period.postcode ASC"
            : "\nORDER BY sales_change DESC, current_period.postcode ASC";

        $bindings = [
            'A',
            'N',
            $periods['current_start']->toDateString(),
            $periods['current_end']->toDateString(),
            'A',
            'N',
            $periods['previous_start']->toDateString(),
            $periods['previous_end']->toDateString(),
            'A',
            'N',
            $periods['earlier_start']->toDateString(),
            $periods['earlier_end']->toDateString(),
            $this->minSectorTransactions(),
            $threshold,
        ];

        if ($requiresPersistence) {
            $bindings[] = $this->minSectorTransactions();
            $bindings[] = $threshold;
        }

        return collect(DB::select($sql, $bindings))->map(function (object $row) use ($periods): array {
            return [
                'area_code' => (string) $row->postcode,
                'sales' => (int) $row->sales,
                'previous_sales' => (int) $row->previous_sales,
                'earlier_sales' => (int) $row->earlier_sales,
                'sales_change' => (float) $row->sales_change,
                'previous_sales_change' => (float) $row->previous_sales_change,
                'period_start' => $periods['current_start']->copy(),
                'period_end' => $periods['current_end']->copy(),
            ];
        })->values();
    }

    /**
     * @return Collection<int, array{area_code:string,sales:int,previous_sales:int,current_median_price:float,previous_median_price:float,earlier_median_price:float,growth:float,previous_growth:float,period_start:Carbon,period_end:Carbon}>
     */
    protected function priceTrendRows(string $operator, float $threshold, int $minimumSales): Collection
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
        $currentGrowthExpression = '(100.0 * (current_period.median_price - previous_period.median_price) / NULLIF(previous_period.median_price, 0))';
        $previousGrowthExpression = '(100.0 * (previous_period.median_price - earlier_period.median_price) / NULLIF(earlier_period.median_price, 0))';

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
),
earlier_period AS (
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
    previous_period.sales AS previous_sales,
    current_period.median_price,
    previous_period.median_price AS previous_median_price,
    earlier_period.median_price AS earlier_median_price,
    {$currentGrowthExpression} AS growth,
    {$previousGrowthExpression} AS previous_growth
FROM current_period
INNER JOIN previous_period
    ON previous_period.postcode = current_period.postcode
INNER JOIN earlier_period
    ON earlier_period.postcode = current_period.postcode
WHERE current_period.sales >= ?
  AND previous_period.sales >= ?
  AND previous_period.median_price > 0
  AND earlier_period.median_price > 0
  AND {$currentGrowthExpression} {$operator} ?
SQL;

        $sql .= $operator === '<='
            ? "\nORDER BY growth ASC, current_period.postcode ASC"
            : "\nORDER BY growth DESC, current_period.postcode ASC";

        return collect(DB::select($sql, [
            'A',
            'N',
            $periods['current_start']->toDateString(),
            $periods['current_end']->toDateString(),
            'A',
            'N',
            $periods['previous_start']->toDateString(),
            $periods['previous_end']->toDateString(),
            'A',
            'N',
            $periods['earlier_start']->toDateString(),
            $periods['earlier_end']->toDateString(),
            $minimumSales,
            $minimumSales,
            $threshold,
        ]))->map(function (object $row) use ($periods): array {
            return [
                'area_code' => (string) $row->postcode,
                'sales' => (int) $row->sales,
                'previous_sales' => (int) $row->previous_sales,
                'current_median_price' => (float) $row->median_price,
                'previous_median_price' => (float) $row->previous_median_price,
                'earlier_median_price' => (float) $row->earlier_median_price,
                'growth' => (float) $row->growth,
                'previous_growth' => (float) $row->previous_growth,
                'period_start' => $periods['current_start']->copy(),
                'period_end' => $periods['current_end']->copy(),
            ];
        })->values();
    }

    protected function minSectorTransactions(): int
    {
        return max((int) config('insights.min_sector_transactions', 20), 1);
    }

    /**
     * @return array{current_start: Carbon, current_end: Carbon, previous_start: Carbon, previous_end: Carbon, earlier_start: Carbon, earlier_end: Carbon}|null
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
        $earlierEnd = $previousStart->copy()->subDay()->startOfDay();
        $earlierStart = $previousStart->copy()->subYear()->startOfDay();

        return [
            'current_start' => $currentStart,
            'current_end' => $currentEnd,
            'previous_start' => $previousStart,
            'previous_end' => $previousEnd,
            'earlier_start' => $earlierStart,
            'earlier_end' => $earlierEnd,
        ];
    }
}
