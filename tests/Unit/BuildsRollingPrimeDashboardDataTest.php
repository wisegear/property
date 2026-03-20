<?php

namespace Tests\Unit;

use App\Http\Controllers\Concerns\BuildsRollingPrimeDashboardData;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BuildsRollingPrimeDashboardDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_sale_series_uses_yearly_max_and_top_three_series_keeps_ranked_sales(): void
    {
        $this->ensureLandRegistryTable();

        DB::table('land_registry')->insert([
            $this->landRegistryRow('tx-1', 2000000, '2024-01-10 00:00:00', 'AB1 1AA'),
            $this->landRegistryRow('tx-2', 4500000, '2024-02-10 00:00:00', 'AB1 2AA'),
            $this->landRegistryRow('tx-3', 3200000, '2024-03-10 00:00:00', 'AB1 3AA'),
            $this->landRegistryRow('tx-4', 2800000, '2024-04-10 00:00:00', 'AB1 4AA'),
        ]);

        $subject = new BuildsRollingPrimeDashboardDataTestHarness;
        $baseQuery = DB::table('land_registry')->where('PPDCategoryType', 'A');
        $endMonths = collect([Carbon::create(2024, 12, 1)]);

        $topSaleSeries = $subject->callBuildRollingTopSaleSeries($baseQuery, $endMonths);
        $topThreeSeries = $subject->callBuildRollingTop3Series($baseQuery, $endMonths);

        $this->assertSame(4500000, (int) $topSaleSeries->first()->top_sale);
        $this->assertSame([4500000, 3200000, 2800000], $topThreeSeries->pluck('Price')->map(fn ($price): int => (int) $price)->all());
        $this->assertSame([1, 2, 3], $topThreeSeries->pluck('rn')->map(fn ($rank): int => (int) $rank)->all());
    }

    private function ensureLandRegistryTable(): void
    {
        if (Schema::hasTable('land_registry')) {
            return;
        }

        Schema::create('land_registry', function (Blueprint $table): void {
            $table->string('TransactionID')->nullable();
            $table->unsignedBigInteger('Price')->nullable();
            $table->dateTime('Date')->nullable();
            $table->string('Postcode')->nullable();
            $table->string('PPDCategoryType')->nullable();
        });
    }

    /**
     * @return array<string, int|string>
     */
    private function landRegistryRow(string $transactionId, int $price, string $date, string $postcode): array
    {
        return [
            'TransactionID' => $transactionId,
            'Price' => $price,
            'Date' => $date,
            'Postcode' => $postcode,
            'PPDCategoryType' => 'A',
        ];
    }
}

class BuildsRollingPrimeDashboardDataTestHarness
{
    use BuildsRollingPrimeDashboardData;

    public function callBuildRollingTopSaleSeries(Builder $baseQuery, $endMonths)
    {
        return $this->buildRollingTopSaleSeries($baseQuery, $endMonths);
    }

    public function callBuildRollingTop3Series(Builder $baseQuery, $endMonths)
    {
        return $this->buildRollingTop3Series($baseQuery, $endMonths);
    }

    protected function medianPriceExpression(string $columnExpression = 'Price'): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return "PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY {$columnExpression})";
        }

        return "AVG({$columnExpression})";
    }

    protected function quotedColumn(string $column): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return '"'.$column.'"';
        }

        return $column;
    }
}
