<?php

namespace Tests\Feature;

use App\Http\Controllers\PropertyStreetController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PropertyStreetControllerTest extends TestCase
{
    public function test_street_page_renders_summary_table_charts_and_top_sales_for_a_street_and_outcode(): void
    {
        $this->ensureLandRegistryTable();
        $this->ensureOnspdTable();
        $this->ensureCrimeTable();
        $this->ensureImdTables();

        Cache::forget(PropertyStreetController::cacheKey('cromwell-road', 'SW7'));
        DB::table('land_registry')->delete();
        DB::table('onspd_v2')->delete();
        DB::table('crime')->delete();
        DB::table('imd2025')->delete();

        DB::table('land_registry')->insert([
            $this->saleRow('tx-101', 'CROMWELL ROAD', 100000, '2023-01-15 00:00:00', 'SW7 5PH', '1', 'FLAT 1'),
            $this->saleRow('tx-102', 'CROMWELL ROAD', 200000, '2023-06-10 00:00:00', 'SW7 5PH', '2', 'FLAT 2'),
            $this->saleRow('tx-103', 'CROMWELL ROAD', 300000, '2024-02-02 00:00:00', 'SW7 5AA', '3', null),
            $this->saleRow('tx-104', 'CROMWELL ROAD', 400000, '2024-06-10 00:00:00', 'SW7 4ZZ', '4', null),
            $this->saleRow('tx-107', 'ELM ROAD', 500000, '2024-04-01 00:00:00', 'SW7 6AA', '1', null),
            $this->saleRow('tx-108', 'ELM ROAD', 520000, '2024-04-02 00:00:00', 'SW7 6AB', '2', null),
            $this->saleRow('tx-109', 'ELM ROAD', 540000, '2024-04-03 00:00:00', 'SW7 6AC', '3', null),
            $this->saleRow('tx-110', 'ELM ROAD', 560000, '2024-04-04 00:00:00', 'SW7 6AD', '4', null),
            $this->saleRow('tx-111', 'ELM ROAD', 580000, '2024-04-05 00:00:00', 'SW7 6AE', '5', null),
            $this->saleRow('tx-105', 'CROMWELL ROAD', 999999, '2024-07-01 00:00:00', 'SW5 0AA', '5', null),
            $this->saleRow('tx-106', 'CROMWELL ROAD', 150000, '2024-08-01 00:00:00', 'SW7 9AA', '6', null, 'B'),
        ]);

        DB::table('onspd_v2')->insert([
            ['pcds' => 'SW7 5PH', 'lsoa21cd' => 'E01000001', 'lsoa11cd' => 'E01000001', 'lat' => 51.4970000, 'long' => -0.1820000, 'dointr' => '202501'],
            ['pcds' => 'SW7 5AA', 'lsoa21cd' => 'E01000001', 'lsoa11cd' => 'E01000001', 'lat' => 51.4972000, 'long' => -0.1817000, 'dointr' => '202501'],
            ['pcds' => 'SW7 4ZZ', 'lsoa21cd' => 'E01000001', 'lsoa11cd' => 'E01000001', 'lat' => 51.4968000, 'long' => -0.1823000, 'dointr' => '202501'],
        ]);

        DB::table('imd2025')->insert([
            'LSOA_Code_2021' => 'E01000001',
            'LSOA_Name_2021' => 'Knightsbridge and Belgravia 001A',
            'Index_of_Multiple_Deprivation_Rank' => 1200,
            'Index_of_Multiple_Deprivation_Decile' => 8,
        ]);

        DB::table('crime')->insert([
            $this->crimeRow('2024-06-01', 'burglary', 51.4971000, -0.1819000),
            $this->crimeRow('2024-08-01', 'burglary', 51.4971000, -0.1819000),
            $this->crimeRow('2024-10-01', 'criminal damage and arson', 51.4971000, -0.1819000),
            $this->crimeRow('2024-12-01', 'criminal damage and arson', 51.4971000, -0.1819000),
            $this->crimeRow('2025-02-01', 'criminal damage and arson', 51.4971000, -0.1819000),
            $this->crimeRow('2025-04-01', 'possession of weapons', 51.4971000, -0.1819000),
            $this->crimeRow('2025-05-01', 'burglary', 51.4971000, -0.1819000),
        ]);

        $legacyResponse = $this->get('/property/street/cromwell-road?outcode=SW7');
        $legacyResponse->assertRedirect('/property/street/sw7/cromwell-road');

        $response = $this->get('/property/street/sw7/cromwell-road');

        $response->assertOk();
        $response->assertSee('Cromwell Road SW7 Sold Prices &amp; Property Data', false);
        $response->assertSee('Cromwell Road at a glance');
        $response->assertSee('Cromwell Road compared with SW7');
        $response->assertSee('4');
        $response->assertSee('£250,000');
        $response->assertSee('10 Jun 2024');
        $response->assertSee('£400,000');
        $response->assertSee('Closest deprivation area for this street');
        $response->assertSee('Knightsbridge and Belgravia 001A');
        $response->assertSee('Nearest postcode anchor: SW7 5PH');
        $response->assertSee('Crime trends near Cromwell Road, SW7');
        $response->assertSee('Crime profile for this street area');
        $response->assertSee('Recent property sales on Cromwell Road');
        $response->assertSee('Nearby streets in SW7');
        $response->assertSee('Elm Road');
        $response->assertSee('Questions about Cromwell Road');
        $response->assertSee('What is the average house price on Cromwell Road?');
        $response->assertSee(route('property.show.slug', ['slug' => 'sw7-5ph-1-cromwell-road-flat-1'], false), false);
        $response->assertSee('<link rel="canonical" href="http://localhost/property/street/sw7/cromwell-road">', false);
        $response->assertSee('<meta name="robots" content="noindex, follow">', false);
        $response->assertSee('"@type":"BreadcrumbList"', false);
        $response->assertSee('"@type":"FAQPage"', false);
        $response->assertSee('There is limited sales data for this street, so wider postcode district figures may give a better view of the local market.');

        $cached = Cache::get(PropertyStreetController::cacheKey('cromwell-road', 'SW7'));

        $this->assertIsArray($cached);
        $this->assertSame('CROMWELL ROAD', $cached['street_name']);
        $this->assertSame(4, $cached['summary']['total_sales']);
        $this->assertSame('/property/street/sw7/cromwell-road', parse_url($cached['canonical_url'], PHP_URL_PATH));
    }

    public function test_street_page_shows_reliability_warning_for_limited_street_data(): void
    {
        $this->ensureLandRegistryTable();

        Cache::forget(PropertyStreetController::cacheKey('union-street', 'SE1'));
        DB::table('land_registry')->delete();

        DB::table('land_registry')->insert([
            $this->saleRow('tx-201', 'UNION STREET', 120000, '2024-01-01 00:00:00', 'SE1 1AA', '1', null),
            $this->saleRow('tx-202', 'UNION STREET', 180000, '2024-03-01 00:00:00', 'SE1 1AB', '2', null),
        ]);

        $response = $this->get('/property/street/se1/union-street');

        $response->assertOk();
        $response->assertSee('Union Street SE1 Sold Prices &amp; Property Data', false);
        $response->assertSee('There is limited sales data for this street, so wider postcode district figures may give a better view of the local market.');
        $response->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    private function ensureLandRegistryTable(): void
    {
        if (Schema::hasTable('land_registry')) {
            return;
        }

        Schema::create('land_registry', function (Blueprint $table): void {
            $table->char('TransactionID', 36)->nullable();
            $table->unsignedInteger('Price')->nullable();
            $table->dateTime('Date')->nullable();
            $table->string('Postcode', 10)->nullable();
            $table->enum('PropertyType', ['D', 'S', 'T', 'F', 'O'])->nullable();
            $table->enum('NewBuild', ['Y', 'N'])->nullable();
            $table->enum('Duration', ['F', 'L'])->nullable();
            $table->string('PAON', 100)->nullable();
            $table->string('SAON', 100)->nullable();
            $table->string('Street', 100)->nullable();
            $table->string('Locality', 100)->nullable();
            $table->string('TownCity', 100)->nullable();
            $table->string('District', 100)->nullable();
            $table->string('County', 100)->nullable();
            $table->enum('PPDCategoryType', ['A', 'B'])->nullable();
            $table->char('RecordStatus', 1)->nullable();
        });
    }

    private function ensureOnspdTable(): void
    {
        if (Schema::hasTable('onspd_v2')) {
            return;
        }

        Schema::create('onspd_v2', function (Blueprint $table): void {
            $table->string('pcds', 16)->nullable();
            $table->string('lsoa21cd', 16)->nullable();
            $table->string('lsoa11cd', 16)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('long', 10, 7)->nullable();
            $table->string('dointr', 16)->nullable();
        });
    }

    private function ensureCrimeTable(): void
    {
        if (Schema::hasTable('crime')) {
            return;
        }

        Schema::create('crime', function (Blueprint $table): void {
            $table->string('crime_id')->nullable();
            $table->date('month')->nullable();
            $table->string('reported_by')->nullable();
            $table->string('falls_within')->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->string('location')->nullable();
            $table->string('lsoa_code')->nullable();
            $table->string('lsoa_name')->nullable();
            $table->string('crime_type')->nullable();
            $table->string('last_outcome_category')->nullable();
            $table->string('context')->nullable();
        });
    }

    private function ensureImdTables(): void
    {
        if (! Schema::hasTable('imd2025')) {
            Schema::create('imd2025', function (Blueprint $table): void {
                $table->string('LSOA_Code_2021')->nullable();
                $table->string('LSOA_Name_2021')->nullable();
                $table->unsignedInteger('Index_of_Multiple_Deprivation_Rank')->nullable();
                $table->unsignedTinyInteger('Index_of_Multiple_Deprivation_Decile')->nullable();
            });
        }

        if (! Schema::hasTable('wimd2019')) {
            Schema::create('wimd2019', function (Blueprint $table): void {
                $table->string('LSOA_code')->nullable();
                $table->string('LSOA_name')->nullable();
                $table->unsignedInteger('WIMD_2019')->nullable();
            });
        }
    }

    /**
     * @return array<string, int|string|null>
     */
    private function saleRow(
        string $transactionId,
        string $street,
        int $price,
        string $date,
        string $postcode,
        string $paon,
        ?string $saon,
        string $category = 'A'
    ): array {
        return [
            'TransactionID' => $transactionId,
            'Price' => $price,
            'Date' => $date,
            'Postcode' => $postcode,
            'PropertyType' => 'F',
            'NewBuild' => 'N',
            'Duration' => 'L',
            'PAON' => $paon,
            'SAON' => $saon,
            'Street' => $street,
            'Locality' => null,
            'TownCity' => 'London',
            'District' => 'Kensington',
            'County' => 'Greater London',
            'PPDCategoryType' => $category,
            'RecordStatus' => null,
        ];
    }

    /**
     * @return array<string, float|string|null>
     */
    private function crimeRow(string $month, string $crimeType, float $latitude, float $longitude): array
    {
        return [
            'crime_id' => uniqid('crime-', true),
            'month' => $month,
            'reported_by' => 'Met Police',
            'falls_within' => 'Met Police',
            'longitude' => $longitude,
            'latitude' => $latitude,
            'location' => 'Near Cromwell Road',
            'lsoa_code' => 'E01000001',
            'lsoa_name' => 'Test LSOA',
            'crime_type' => $crimeType,
            'last_outcome_category' => null,
            'context' => null,
        ];
    }
}
