<?php

namespace Tests\Unit;

use App\Services\Property\NationalPropertyDashboard;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NationalPropertyDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_preserves_missing_breakdowns_as_null_and_excludes_category_b(): void
    {
        DB::table('land_registry')->insert([
            [
                'TransactionID' => str_repeat('a', 36),
                'Price' => 250000,
                'Date' => '2025-05-10',
                'Postcode' => 'AB1 1AA',
                'PropertyType' => 'D',
                'NewBuild' => 'N',
                'Duration' => 'F',
                'PPDCategoryType' => 'A',
            ],
            [
                'TransactionID' => str_repeat('b', 36),
                'Price' => 999999,
                'Date' => '2025-05-11',
                'Postcode' => 'AB1 1AB',
                'PropertyType' => 'F',
                'NewBuild' => 'Y',
                'Duration' => 'L',
                'PPDCategoryType' => 'B',
            ],
        ]);

        $data = app(NationalPropertyDashboard::class)->build(Carbon::parse('2025-05-01'));

        $this->assertSame(1, $data['summary']['sales']);
        $this->assertSame(250000, $data['summary']['median_price']);
        $this->assertSame(1, $data['property_types'][0]['detached']['sales']);
        $this->assertNull($data['property_types'][0]['flat']['sales']);
        $this->assertNull($data['stock_mix'][0]['new_build']);
        $this->assertSame(1, $data['stock_mix'][0]['existing']);
        $this->assertNull($data['tenure_mix'][0]['leasehold']);
        $this->assertNull($data['year_on_year'][0]['sales']);
    }
}
