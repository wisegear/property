<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PropertyHomeWarmCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_warms_sales_cache_using_yeardate_column(): void
    {
        DB::table('land_registry')->insert([
            $this->landRegistryRow('11111111-1111-1111-1111-11111111111111', 250000, '2024-01-15 00:00:00'),
            $this->landRegistryRow('22222222-2222-2222-2222-22222222222222', 350000, '2025-01-15 00:00:00'),
        ]);

        $this->artisan('property:home-warm', ['--task' => 'sales'])->assertExitCode(0);

        $cached = Cache::get('land_registry_sales_by_year:catA:v2');
        $this->assertNotNull($cached);
        $this->assertSame(2, $cached->count());
        $this->assertSame(2024, (int) $cached->first()->year);
    }

    public function test_it_warms_monthly24_cache_with_portable_month_expression(): void
    {
        $currentMonthDate = Carbon::now()->startOfMonth()->addDay()->format('Y-m-d H:i:s');
        $previousMonthDate = Carbon::now()->startOfMonth()->subMonth()->addDay()->format('Y-m-d H:i:s');

        DB::table('land_registry')->insert([
            $this->landRegistryRow('33333333-3333-3333-3333-33333333333333', 210000, $currentMonthDate),
            $this->landRegistryRow('44444444-4444-4444-4444-44444444444444', 220000, $previousMonthDate),
        ]);

        $this->artisan('property:home-warm', ['--task' => 'monthly24'])->assertExitCode(0);

        $cached = Cache::get('dashboard:sales_last_24m:EW:catA:v2');
        $this->assertIsArray($cached);
        $this->assertCount(2, $cached);
        $this->assertCount(24, $cached[0]);
        $this->assertCount(24, $cached[1]);
        $this->assertContains(1, $cached[1]);
    }

    private function landRegistryRow(string $transactionId, int $price, string $date): array
    {
        $row = [
            'TransactionID' => $transactionId,
            'Price' => $price,
            'Date' => $date,
            'PPDCategoryType' => 'A',
        ];

        if (Schema::hasColumn('land_registry', 'YearDate')) {
            $row['YearDate'] = (int) Carbon::parse($date)->format('Y');
        }

        return $row;
    }
}
