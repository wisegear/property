<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportLandRegistryUprnCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $temporaryFile) {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }

        parent::tearDown();
    }

    public function test_it_replaces_the_table_and_reports_malformed_and_blank_rows(): void
    {
        DB::table('land_registry_uprn')->insert([
            'transaction_id' => '{AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA}',
            'uprn' => '999999999999',
        ]);

        $rows = [];

        for ($rowNumber = 1; $rowNumber <= 10; $rowNumber++) {
            $rows[] = $this->csvRow($rowNumber, (string) (10000000000 + $rowNumber));
        }

        $rows[] = 'not-a-transaction-id,123';
        $rows[] = '';
        $rows[] = '  {00000000-0000-0000-0000-000000000011}  ,  001234  ';

        $file = $this->createCsv($rows);

        $this->artisan('land-registry:import-uprn', ['file' => $file])
            ->expectsOutput('Land Registry UPRN import complete.')
            ->expectsOutput('Total CSV rows read: 13')
            ->expectsOutput('Rows successfully imported: 11')
            ->expectsOutput('Malformed/skipped rows: 2')
            ->expectsOutput('Final land_registry_uprn row count: 11')
            ->assertSuccessful();

        $this->assertDatabaseMissing('land_registry_uprn', [
            'transaction_id' => '{AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA}',
        ]);
        $this->assertDatabaseHas('land_registry_uprn', [
            'transaction_id' => '{00000000-0000-0000-0000-000000000011}',
            'uprn' => '001234',
        ]);
    }

    public function test_initial_validation_failure_preserves_existing_data(): void
    {
        $this->insertExistingRecord();
        $file = $this->createCsv([
            'Transaction ID,UPRN',
            'invalid,row,with-an-extra-column',
        ]);

        $this->artisan('land-registry:import-uprn', ['file' => $file])
            ->expectsOutput('Initial CSV validation failed. The existing UPRN data was left untouched.')
            ->assertFailed();

        $this->assertDatabaseHas('land_registry_uprn', [
            'transaction_id' => '{AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA}',
            'uprn' => '999999999999',
        ]);
    }

    public function test_missing_file_preserves_existing_data(): void
    {
        $this->insertExistingRecord();
        $missingFile = sys_get_temp_dir().'/missing-land-registry-uprn-'.uniqid().'.csv';

        $this->artisan('land-registry:import-uprn', ['file' => $missingFile])
            ->expectsOutput("CSV file does not exist or is not readable: {$missingFile}")
            ->assertFailed();

        $this->assertDatabaseCount('land_registry_uprn', 1);
    }

    public function test_database_failure_rolls_back_the_truncate_and_import(): void
    {
        $this->insertExistingRecord();
        $rows = [];

        for ($rowNumber = 1; $rowNumber <= 10; $rowNumber++) {
            $rows[] = $this->csvRow($rowNumber, (string) (10000000000 + $rowNumber));
        }

        $rows[] = $this->csvRow(1, '20000000000');
        $file = $this->createCsv($rows);

        $this->artisan('land-registry:import-uprn', ['file' => $file])
            ->expectsOutputToContain('Import failed and the replacement was rolled back:')
            ->assertFailed();

        $this->assertDatabaseCount('land_registry_uprn', 1);
        $this->assertDatabaseHas('land_registry_uprn', [
            'transaction_id' => '{AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA}',
            'uprn' => '999999999999',
        ]);
    }

    private function insertExistingRecord(): void
    {
        DB::table('land_registry_uprn')->insert([
            'transaction_id' => '{AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA}',
            'uprn' => '999999999999',
        ]);
    }

    /**
     * @param  array<int, string>  $rows
     */
    private function createCsv(array $rows): string
    {
        $file = tempnam(sys_get_temp_dir(), 'uprn-import-');

        if ($file === false) {
            $this->fail('Unable to create temporary CSV file.');
        }

        file_put_contents($file, implode(PHP_EOL, $rows).PHP_EOL);
        $this->temporaryFiles[] = $file;

        return $file;
    }

    private function csvRow(int $rowNumber, string $uprn): string
    {
        return sprintf('{00000000-0000-0000-0000-%012d},%s', $rowNumber, $uprn);
    }
}
