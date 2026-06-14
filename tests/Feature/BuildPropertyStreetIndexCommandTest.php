<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BuildPropertyStreetIndexCommandTest extends TestCase
{
    private ?string $originalIndex = null;

    protected function setUp(): void
    {
        parent::setUp();

        $path = public_path('data/property_streets.json');
        $this->originalIndex = File::exists($path) ? File::get($path) : null;
    }

    protected function tearDown(): void
    {
        $path = public_path('data/property_streets.json');

        if ($this->originalIndex === null) {
            File::delete($path);
        } else {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $this->originalIndex);
        }

        parent::tearDown();
    }

    public function test_command_builds_unique_street_and_outcode_index_items_from_land_registry_sales(): void
    {
        $this->ensureLandRegistryTable();

        File::ensureDirectoryExists(public_path('data'));
        File::delete(public_path('data/property_streets.json'));

        DB::table('land_registry')->delete();

        DB::table('land_registry')->insert([
            $this->saleRow('tx-001', 'CROMWELL ROAD', 'SW7 5PH', 100000, '2024-01-10 00:00:00'),
            $this->saleRow('tx-002', 'CROMWELL ROAD', 'SW7 5AA', 110000, '2024-02-10 00:00:00'),
            $this->saleRow('tx-003', 'CROMWELL ROAD', 'SW7 4ZZ', 120000, '2024-03-10 00:00:00'),
            $this->saleRow('tx-004', 'CROMWELL ROAD', 'SW5 0AA', 130000, '2024-04-10 00:00:00'),
            $this->saleRow('tx-005', 'CROMWELL ROAD', 'SW5 0AB', 140000, '2024-05-10 00:00:00'),
            $this->saleRow('tx-006', 'CROMWELL ROAD', 'SW5 0AC', 150000, '2024-06-10 00:00:00'),
            $this->saleRow('tx-007', 'BAKER STREET', 'W1 1AA', 200000, '2024-01-01 00:00:00'),
            $this->saleRow('tx-008', 'BAKER STREET', 'W1 1AB', 210000, '2024-02-01 00:00:00'),
            $this->saleRow('tx-009', 'BAKER STREET', 'W1 1AC', 220000, '2024-03-01 00:00:00'),
            $this->saleRow('tx-010', 'BAKER STREET', 'W1 1AD', 230000, '2024-04-01 00:00:00', 'B'),
            $this->saleRow('tx-011', 'SHORT STREET', 'E1 1AA', 90000, '2024-01-01 00:00:00'),
            $this->saleRow('tx-012', 'SHORT STREET', 'E1 1AB', 91000, '2024-02-01 00:00:00'),
        ]);

        $this->artisan('property:build-street-index')->assertExitCode(0);

        $path = public_path('data/property_streets.json');
        $this->assertFileExists($path);

        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([
            [
                'street' => 'BAKER STREET',
                'outcode' => 'W1',
                'label' => 'BAKER STREET, W1',
                'path' => '/property/street/baker-street?outcode=W1',
                'search' => 'baker street w1',
            ],
            [
                'street' => 'CROMWELL ROAD',
                'outcode' => 'SW5',
                'label' => 'CROMWELL ROAD, SW5',
                'path' => '/property/street/cromwell-road?outcode=SW5',
                'search' => 'cromwell road sw5',
            ],
            [
                'street' => 'CROMWELL ROAD',
                'outcode' => 'SW7',
                'label' => 'CROMWELL ROAD, SW7',
                'path' => '/property/street/cromwell-road?outcode=SW7',
                'search' => 'cromwell road sw7',
            ],
        ], $payload);
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

    /**
     * @return array<string, int|string|null>
     */
    private function saleRow(string $transactionId, string $street, string $postcode, int $price, string $date, string $category = 'A'): array
    {
        return [
            'TransactionID' => $transactionId,
            'Price' => $price,
            'Date' => $date,
            'Postcode' => $postcode,
            'PropertyType' => 'F',
            'NewBuild' => 'N',
            'Duration' => 'L',
            'PAON' => '1',
            'SAON' => null,
            'Street' => $street,
            'Locality' => null,
            'TownCity' => 'London',
            'District' => 'Kensington',
            'County' => 'Greater London',
            'PPDCategoryType' => $category,
            'RecordStatus' => null,
        ];
    }
}
