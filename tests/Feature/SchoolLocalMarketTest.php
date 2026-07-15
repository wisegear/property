<?php

namespace Tests\Feature;

use App\Services\PropertyResearch\SchoolLocalMarketService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchoolLocalMarketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->ensureLandRegistryTable();

        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('property_school_establishments', function (Blueprint $table): void {
                $table->decimal('location_latitude', 10, 7)->nullable();
                $table->decimal('location_longitude', 10, 7)->nullable();
            });
        }

        DB::table('property_school_establishments')->insert([
            'urn' => '100491',
            'establishment_name' => 'Oratory Roman Catholic Primary School',
            'postcode' => 'SW6 1RX',
        ]);

        DB::table('land_registry')->insert([
            $this->sale('TX-1', 500000, '2026-05-10', 'SW6 1RX', '10', 'SEAGRAVE ROAD', 'T'),
            $this->sale('TX-2', 550000, '2026-04-10', 'SW6 2AB', '12', 'SEAGRAVE ROAD', 'T'),
            $this->sale('TX-3', 600000, '2026-03-10', 'SW6 3CD', '14', 'SEAGRAVE ROAD', 'S'),
            $this->sale('TX-4', 700000, '2026-02-10', 'SW6 4EF', '1', 'BISHOPS ROAD', 'F'),
            $this->sale('TX-5', 800000, '2026-01-10', 'SW7 1AA', '1', 'EXCLUDED ROAD', 'D'),
            $this->sale('TX-6', 300000, '2024-05-10', 'SW6 1RX', '1', 'OLD ROAD', 'T'),
            $this->sale('TX-7', 310000, '2024-04-10', 'SW6 1RX', '2', 'OLD ROAD', 'T'),
            $this->sale('TX-8', 320000, '2024-03-10', 'SW6 1RX', '3', 'OLD ROAD', 'T'),
        ]);
    }

    public function test_local_market_panel_returns_shared_outcode_snapshot(): void
    {
        $this->get('/school-local-market/100491')
            ->assertOk()
            ->assertSee('Nearby streets')
            ->assertSee('Seagrave Road')
            ->assertSee('Average sold price over the past 12 months.')
            ->assertSee('£550,000')
            ->assertDontSee('£550,000 avg.')
            ->assertSee('View street')
            ->assertSee('Latest sold prices')
            ->assertSee('The most recent sales recorded on nearby streets.')
            ->assertSee('£500,000')
            ->assertSee('View property')
            ->assertSee('/property/sw6-1rx-10-seagrave-road', false)
            ->assertDontSee('/property/street/sw6/old-road', false)
            ->assertDontSee('Excluded Road');

        $this->assertTrue(Cache::has(SchoolLocalMarketService::cacheKey('SW6 1RX')));
    }

    public function test_school_page_only_contains_the_async_market_placeholder(): void
    {
        $this->get('/school/oratory-roman-catholic-primary-school')
            ->assertOk()
            ->assertSee('Nearby streets and sold property prices')
            ->assertSee('/school-local-market/100491', false)
            ->assertDontSee('Seagrave Road');

        $this->assertFalse(Cache::has(SchoolLocalMarketService::cacheKey('SW6 1RX')));
    }

    public function test_nearby_streets_section_is_hidden_without_three_sales_in_twelve_months(): void
    {
        DB::table('property_school_establishments')->insert([
            'urn' => '100493',
            'establishment_name' => 'Quiet Area School',
            'postcode' => 'CF1 1AA',
        ]);
        DB::table('land_registry')->insert(
            $this->sale('TX-QUIET', 250000, '2026-06-10', 'CF1 1AA', '1', 'QUIET ROAD', 'T')
        );

        $this->get('/school-local-market/100493')
            ->assertOk()
            ->assertDontSee('Nearby streets')
            ->assertSee('Latest sold prices')
            ->assertSee('Quiet Road');
    }

    public function test_separate_warmer_populates_one_snapshot_per_outcode(): void
    {
        DB::table('property_school_establishments')->insert([
            'urn' => '100492',
            'establishment_name' => 'Another School',
            'postcode' => 'SW6 2AB',
        ]);

        $this->artisan('property:school-property-warm', ['--limit' => 1])
            ->expectsOutputToContain('Warming 1 shared school property snapshots')
            ->assertSuccessful();

        $this->assertTrue(Cache::has(SchoolLocalMarketService::cacheKey('SW6 1RX')));
    }

    /**
     * @return array<string, mixed>
     */
    private function sale(string $transactionId, int $price, string $date, string $postcode, string $paon, string $street, string $propertyType): array
    {
        return [
            'TransactionID' => $transactionId,
            'Price' => $price,
            'Date' => $date,
            'Postcode' => $postcode,
            'PropertyType' => $propertyType,
            'PAON' => $paon,
            'Street' => $street,
            'PPDCategoryType' => 'A',
        ];
    }

    private function ensureLandRegistryTable(): void
    {
        if (Schema::hasTable('land_registry')) {
            return;
        }

        Schema::create('land_registry', function (Blueprint $table): void {
            $table->string('TransactionID')->primary();
            $table->integer('Price')->nullable();
            $table->dateTime('Date')->nullable();
            $table->string('Postcode')->nullable();
            $table->string('PropertyType')->nullable();
            $table->string('PAON')->nullable();
            $table->string('SAON')->nullable();
            $table->string('Street')->nullable();
            $table->string('PPDCategoryType')->nullable();
        });
    }
}
