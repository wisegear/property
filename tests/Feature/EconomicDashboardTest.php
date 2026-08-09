<?php

namespace Tests\Feature;

use App\Models\MortgageApproval;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EconomicDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->forgetDashboardCache();
        $this->ensureDashboardTablesExist();
    }

    protected function forgetDashboardCache(): void
    {
        foreach ([
            'eco:last_interest',
            'eco:last_inflation',
            'eco:last_wages',
            'eco:last_unemployment',
            'eco:last_approvals',
            'eco:last_reposs_v2',
            'eco:last_hpi',
            'eco:total_stress',
            'eco:total_stress_persist',
        ] as $key) {
            Cache::forget($key);
        }
    }

    protected function ensureDashboardTablesExist(): void
    {
        if (! Schema::hasTable('interest_rates')) {
            Schema::create('interest_rates', function (Blueprint $table) {
                $table->id();
                $table->date('effective_date');
                $table->decimal('rate', 6, 3)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inflation_cpih_monthly')) {
            Schema::create('inflation_cpih_monthly', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->decimal('value', 8, 3)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wage_growth_monthly')) {
            Schema::create('wage_growth_monthly', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->decimal('three_month_avg_yoy', 8, 3)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('unemployment_monthly')) {
            Schema::create('unemployment_monthly', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->unsignedInteger('single_month')->nullable();
                $table->decimal('single', 5, 2)->nullable();
                $table->decimal('three_month', 5, 2)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mortgage_approvals')) {
            Schema::create('mortgage_approvals', function (Blueprint $table) {
                $table->id();
                $table->string('series_code', 32);
                $table->date('period');
                $table->unsignedInteger('value')->nullable();
                $table->string('unit', 16)->nullable();
                $table->string('source', 64)->default('BoE');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mlar_arrears')) {
            Schema::create('mlar_arrears', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('year')->nullable();
                $table->string('quarter', 2)->nullable();
                $table->string('description')->nullable();
                $table->decimal('value', 8, 3)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hpi_monthly')) {
            Schema::create('hpi_monthly', function (Blueprint $table) {
                $table->id();
                $table->string('AreaCode', 16)->nullable();
                $table->date('Date');
                $table->unsignedInteger('AveragePrice')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_mortgage_approvals_sparkline_uses_house_purchase_series_only(): void
    {
        MortgageApproval::query()->create([
            'series_code' => 'LPMVTVX',
            'period' => '2025-01-01',
            'value' => 100,
            'unit' => 'count',
            'source' => 'BoE',
        ]);

        MortgageApproval::query()->create([
            'series_code' => 'LPMVTVX',
            'period' => '2025-02-01',
            'value' => 110,
            'unit' => 'count',
            'source' => 'BoE',
        ]);

        MortgageApproval::query()->create([
            'series_code' => 'LPMVTVX',
            'period' => '2025-03-01',
            'value' => 120,
            'unit' => 'count',
            'source' => 'BoE',
        ]);

        MortgageApproval::query()->create([
            'series_code' => 'LPMB4B3',
            'period' => '2025-03-01',
            'value' => 200,
            'unit' => 'count',
            'source' => 'BoE',
        ]);

        MortgageApproval::query()->create([
            'series_code' => 'LPMB4B4',
            'period' => '2025-03-01',
            'value' => 300,
            'unit' => 'count',
            'source' => 'BoE',
        ]);

        MortgageApproval::query()->create([
            'series_code' => 'LPMB3C8',
            'period' => '2025-04-01',
            'value' => 999,
            'unit' => 'count',
            'source' => 'BoE',
        ]);

        $response = $this->get(route('economic.dashboard', absolute: false));

        $response->assertOk();
        $response->assertDontSee('sticky top-0 z-40 backdrop-blur-sm bg-white/95', false);
        $response->assertSee('-mx-6 -mt-6', false);
        $response->assertSee('rounded-sm border border-zinc-200', false);

        $sparklines = $response->viewData('sparklines');
        $approvals = $response->viewData('approvals');

        $this->assertSame([100.0, 110.0, 120.0], $sparklines['approvals']['values']);
        $this->assertSame(120.0, (float) $approvals->value);
        $this->assertSame('Mar 2025', $approvals->period);
    }

    public function test_hpi_panel_uses_latest_normalized_label_for_legacy_y_d_m_date_format(): void
    {
        DB::table('hpi_monthly')->insert([
            [
                'AreaCode' => 'K02000001',
                'Date' => '2025-01-11',
                'AveragePrice' => 300000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'AreaCode' => 'K02000001',
                'Date' => '2025-01-12',
                'AveragePrice' => 301000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->get(route('economic.dashboard', absolute: false));

        $response->assertOk();
        $response->assertSee('Dec 2025');
        $this->assertSame('Dec 2025', $response->viewData('hpiDateLabel'));
    }

    public function test_repossessions_only_warns_when_rises_are_separated_by_flat_quarters(): void
    {
        DB::table('mlar_arrears')->insert([
            [
                'year' => 2024,
                'quarter' => 'Q4',
                'description' => 'In possession',
                'value' => 0.060,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'year' => 2025,
                'quarter' => 'Q1',
                'description' => 'In possession',
                'value' => 0.060,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'year' => 2025,
                'quarter' => 'Q2',
                'description' => 'In possession',
                'value' => 0.070,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'year' => 2025,
                'quarter' => 'Q3',
                'description' => 'In possession',
                'value' => 0.070,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'year' => 2025,
                'quarter' => 'Q4',
                'description' => 'In possession',
                'value' => 0.080,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->get(route('economic.dashboard', absolute: false));

        $response->assertOk();
        $this->assertSame(2, $response->viewData('repossDirection'));
        $response->assertSee('Repossessions are rising.');
        $response->assertSee('Repossessions are still a very small share of mortgages, but the direction matters.');
    }

    public function test_dashboard_uses_positive_summary_when_most_signals_are_positive_or_neutral(): void
    {
        DB::table('interest_rates')->insert([
            ['effective_date' => '2025-10-01', 'rate' => 3.20, 'created_at' => now(), 'updated_at' => now()],
            ['effective_date' => '2025-11-01', 'rate' => 3.10, 'created_at' => now(), 'updated_at' => now()],
            ['effective_date' => '2025-12-01', 'rate' => 3.00, 'created_at' => now(), 'updated_at' => now()],
            ['effective_date' => '2026-01-01', 'rate' => 3.00, 'created_at' => now(), 'updated_at' => now()],
            ['effective_date' => '2026-02-01', 'rate' => 2.90, 'created_at' => now(), 'updated_at' => now()],
            ['effective_date' => '2026-03-01', 'rate' => 2.80, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('inflation_cpih_monthly')->insert([
            ['date' => '2025-10-01', 'value' => 2.6, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2025-11-01', 'value' => 2.5, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2025-12-01', 'value' => 2.4, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-01-01', 'value' => 2.3, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-02-01', 'value' => 2.2, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-03-01', 'value' => 2.1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('wage_growth_monthly')->insert([
            ['date' => '2025-10-01', 'three_month_avg_yoy' => 4.0, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2025-11-01', 'three_month_avg_yoy' => 4.1, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2025-12-01', 'three_month_avg_yoy' => 4.2, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-01-01', 'three_month_avg_yoy' => 4.3, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-02-01', 'three_month_avg_yoy' => 4.4, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-03-01', 'three_month_avg_yoy' => 4.5, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('unemployment_monthly')->insert([
            ['date' => '2025-10-01', 'single_month' => 0, 'single' => 4.4, 'three_month' => 4.4, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2025-11-01', 'single_month' => 0, 'single' => 4.3, 'three_month' => 4.3, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2025-12-01', 'single_month' => 0, 'single' => 4.3, 'three_month' => 4.3, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-01-01', 'single_month' => 0, 'single' => 4.2, 'three_month' => 4.2, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-02-01', 'single_month' => 0, 'single' => 4.2, 'three_month' => 4.2, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-03-01', 'single_month' => 0, 'single' => 4.1, 'three_month' => 4.1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        MortgageApproval::query()->create([
            'series_code' => 'LPMVTVX',
            'period' => '2025-10-01',
            'value' => 62000,
            'unit' => 'count',
            'source' => 'BoE',
        ]);

        MortgageApproval::query()->create([
            'series_code' => 'LPMVTVX',
            'period' => '2025-11-01',
            'value' => 63000,
            'unit' => 'count',
            'source' => 'BoE',
        ]);

        MortgageApproval::query()->create([
            'series_code' => 'LPMVTVX',
            'period' => '2025-12-01',
            'value' => 64000,
            'unit' => 'count',
            'source' => 'BoE',
        ]);

        MortgageApproval::query()->create([
            'series_code' => 'LPMVTVX',
            'period' => '2026-01-01',
            'value' => 65000,
            'unit' => 'count',
            'source' => 'BoE',
        ]);

        MortgageApproval::query()->create([
            'series_code' => 'LPMVTVX',
            'period' => '2026-02-01',
            'value' => 66000,
            'unit' => 'count',
            'source' => 'BoE',
        ]);

        MortgageApproval::query()->create([
            'series_code' => 'LPMVTVX',
            'period' => '2026-03-01',
            'value' => 67000,
            'unit' => 'count',
            'source' => 'BoE',
        ]);

        DB::table('mlar_arrears')->insert([
            ['year' => 2025, 'quarter' => 'Q4', 'description' => 'In possession', 'value' => 0.050, 'created_at' => now(), 'updated_at' => now()],
            ['year' => 2026, 'quarter' => 'Q1', 'description' => 'In possession', 'value' => 0.080, 'created_at' => now(), 'updated_at' => now()],
            ['year' => 2025, 'quarter' => 'Q4', 'description' => '2.5% to 5% in arrears', 'value' => 0.630, 'created_at' => now(), 'updated_at' => now()],
            ['year' => 2026, 'quarter' => 'Q1', 'description' => '2.5% to 5% in arrears', 'value' => 0.640, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('hpi_monthly')->insert([
            ['AreaCode' => 'K02000001', 'Date' => '2025-10-01', 'AveragePrice' => 299000, 'created_at' => now(), 'updated_at' => now()],
            ['AreaCode' => 'K02000001', 'Date' => '2025-11-01', 'AveragePrice' => 300000, 'created_at' => now(), 'updated_at' => now()],
            ['AreaCode' => 'K02000001', 'Date' => '2025-12-01', 'AveragePrice' => 301000, 'created_at' => now(), 'updated_at' => now()],
            ['AreaCode' => 'K02000001', 'Date' => '2026-01-01', 'AveragePrice' => 302000, 'created_at' => now(), 'updated_at' => now()],
            ['AreaCode' => 'K02000001', 'Date' => '2026-02-01', 'AveragePrice' => 303000, 'created_at' => now(), 'updated_at' => now()],
            ['AreaCode' => 'K02000001', 'Date' => '2026-03-01', 'AveragePrice' => 304000, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->get(route('economic.dashboard', absolute: false));

        $response->assertOk();
        $response->assertSee('Market signals summary');
        $response->assertSee('Mortgage approvals');
        $response->assertSee('Buyer demand improved in the latest release.');
        $response->assertSee('What this means');
        $response->assertSee('The wider housing market currently looks');
        $response->assertSee('broadly positive');
        $response->assertDontSee('under pressure');
        $response->assertSee('Latest published figure');
        $response->assertSee('Q1 2026');
        $response->assertSee('Q4 2025');
        $response->assertSee('Mar 2026');
        $response->assertSee('Feb 2026');
    }

    public function test_bank_rate_uses_latest_and_previous_published_rates(): void
    {
        $this->travelTo(Carbon::create(2026, 3, 15));

        DB::table('interest_rates')->insert([
            ['effective_date' => '2025-08-07', 'rate' => 4.00, 'created_at' => now(), 'updated_at' => now()],
            ['effective_date' => '2025-09-01', 'rate' => 4.00, 'created_at' => now(), 'updated_at' => now()],
            ['effective_date' => '2025-10-01', 'rate' => 4.00, 'created_at' => now(), 'updated_at' => now()],
            ['effective_date' => '2025-11-01', 'rate' => 4.00, 'created_at' => now(), 'updated_at' => now()],
            ['effective_date' => '2025-12-18', 'rate' => 3.75, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->get(route('economic.dashboard', absolute: false));

        $response->assertOk();

        $bankRateCard = collect($response->viewData('cards'))
            ->firstWhere('title', 'Bank rate');

        $this->assertNotNull($bankRateCard);
        $this->assertSame('Current rate', $bankRateCard['current_heading']);
        $this->assertSame('Last month', $bankRateCard['previous_heading']);
        $this->assertSame('Monthly movement', $bankRateCard['change_heading']);
        $this->assertSame('Effective since 18 Dec 2025', $bankRateCard['current_label']);
        $this->assertSame('Feb 2026', $bankRateCard['previous_label']);
        $this->assertSame('3.75%', $bankRateCard['current_value']);
        $this->assertSame('3.75%', $bankRateCard['previous_value']);
        $this->assertSame('Unchanged vs Feb 2026', $bankRateCard['change']);
        $this->assertSame('Neutral', $bankRateCard['status']['label']);
        $this->assertSame('Bank rate is unchanged from last month.', $bankRateCard['signal']);
        $this->assertSame('monthly', $bankRateCard['debug']['frequency']);
        $this->assertSame([4.0, 4.0, 4.0, 4.0, 3.75], $response->viewData('sparklines')['interest']['values']);

        $this->travelBack();
    }

    public function test_falling_inflation_is_treated_as_positive_for_consumers(): void
    {
        DB::table('inflation_cpih_monthly')->insert([
            ['date' => '2025-10-01', 'value' => 3.6, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2025-11-01', 'value' => 3.6, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2025-12-01', 'value' => 3.6, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-01-01', 'value' => 3.2, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-02-01', 'value' => 3.2, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-03-01', 'value' => 3.0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->get(route('economic.dashboard', absolute: false));

        $response->assertOk();

        $inflationCard = collect($response->viewData('cards'))
            ->firstWhere('title', 'Inflation');

        $this->assertNotNull($inflationCard);
        $this->assertSame('Positive', $inflationCard['status']['label']);
        $this->assertSame('Inflation fell in the latest release.', $inflationCard['signal']);
        $this->assertSame('-0.2 pts vs Feb 2026', $inflationCard['change']);
    }

    public function test_three_consecutive_adverse_movements_create_stress_in_each_direction(): void
    {
        DB::table('inflation_cpih_monthly')->insert([
            ['date' => '2025-12-01', 'value' => 2.5, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-01-01', 'value' => 2.6, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-02-01', 'value' => 2.7, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-03-01', 'value' => 2.8, 'created_at' => now(), 'updated_at' => now()],
        ]);

        foreach ([67000, 66000, 65000, 64000] as $index => $value) {
            MortgageApproval::query()->create([
                'series_code' => 'LPMVTVX',
                'period' => Carbon::create(2025, 12, 1)->addMonths($index),
                'value' => $value,
                'unit' => 'count',
                'source' => 'BoE',
            ]);
        }

        DB::table('unemployment_monthly')->insert([
            ['date' => '2026-01-01', 'single_month' => 0, 'single' => 4.8, 'three_month' => 4.8, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-02-01', 'single_month' => 0, 'single' => 4.9, 'three_month' => 4.9, 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-03-01', 'single_month' => 0, 'single' => 5.0, 'three_month' => 5.0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->get(route('economic.dashboard', absolute: false));

        $response->assertOk();

        $cards = collect($response->viewData('cards'));

        $this->assertSame('Stress', $cards->firstWhere('title', 'Inflation')['status']['label']);
        $this->assertSame('Stress', $cards->firstWhere('title', 'Mortgage approvals')['status']['label']);
        $this->assertSame('Warning', $cards->firstWhere('title', 'Unemployment')['status']['label']);
    }
}
