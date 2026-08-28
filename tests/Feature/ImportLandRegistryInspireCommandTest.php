<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportLandRegistryInspireCommandTest extends TestCase
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
        $this->insertExistingRecord();
        $rows = [];

        for ($rowNumber = 1; $rowNumber <= 10; $rowNumber++) {
            $rows[] = $this->csvRow($rowNumber, (string) (18110890 + $rowNumber));
        }

        $rows[] = 'not-a-transaction-id,123';
        $rows[] = '';
        $rows[] = '  {00000000-0000-0000-0000-000000000011}  ,  0018110901  ';

        $file = $this->createCsv($rows);

        $this->artisan('land-registry:import-inspire', ['file' => $file])
            ->expectsOutput('Land Registry INSPIRE import complete.')
            ->expectsOutput('Total CSV rows read: 13')
            ->expectsOutput('Rows successfully imported: 11')
            ->expectsOutput('Malformed/skipped rows: 2')
            ->expectsOutput('Final land_registry_inspire row count: 11')
            ->assertSuccessful();

        $this->assertDatabaseMissing('land_registry_inspire', [
            'transaction_id' => '{AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA}',
        ]);
        $this->assertDatabaseHas('land_registry_inspire', [
            'transaction_id' => '{00000000-0000-0000-0000-000000000011}',
            'inspire_id' => '0018110901',
        ]);
    }

    public function test_repeated_transaction_ids_with_different_inspire_ids_are_accepted(): void
    {
        $transactionId = '{5834E4E8-91F1-29C7-E063-4804A8C015BC}';
        $rows = [
            "{$transactionId},50857562",
            "{$transactionId},50857566",
        ];

        for ($rowNumber = 3; $rowNumber <= 10; $rowNumber++) {
            $rows[] = $this->csvRow($rowNumber, (string) (50857560 + $rowNumber));
        }

        $file = $this->createCsv($rows);

        $this->artisan('land-registry:import-inspire', ['file' => $file])
            ->assertSuccessful();

        $this->assertSame(
            ['50857562', '50857566'],
            DB::table('land_registry_inspire')
                ->where('transaction_id', $transactionId)
                ->orderBy('inspire_id')
                ->pluck('inspire_id')
                ->all()
        );
    }

    public function test_initial_validation_failure_preserves_existing_data(): void
    {
        $this->insertExistingRecord();
        $rows = ['Transaction ID,INSPIRE ID'];

        for ($rowNumber = 1; $rowNumber <= 9; $rowNumber++) {
            $rows[] = $this->csvRow($rowNumber, (string) (18110890 + $rowNumber));
        }

        $file = $this->createCsv($rows);

        $this->artisan('land-registry:import-inspire', ['file' => $file])
            ->expectsOutput('Initial CSV validation failed. The existing INSPIRE data was left untouched.')
            ->assertFailed();

        $this->assertDatabaseHas('land_registry_inspire', [
            'transaction_id' => '{AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA}',
            'inspire_id' => '99999999',
        ]);
    }

    public function test_missing_file_preserves_existing_data(): void
    {
        $this->insertExistingRecord();
        $missingFile = sys_get_temp_dir().'/missing-land-registry-inspire-'.uniqid().'.csv';

        $this->artisan('land-registry:import-inspire', ['file' => $missingFile])
            ->expectsOutput("CSV file does not exist or is not readable: {$missingFile}")
            ->assertFailed();

        $this->assertDatabaseCount('land_registry_inspire', 1);
    }

    public function test_database_integrity_failure_rolls_back_the_truncate_and_import(): void
    {
        $this->insertExistingRecord();
        $rows = [];

        for ($rowNumber = 1; $rowNumber <= 10; $rowNumber++) {
            $rows[] = $this->csvRow($rowNumber, (string) (18110890 + $rowNumber));
        }

        $rows[] = $this->csvRow(1, '18110891');
        $file = $this->createCsv($rows);

        $this->artisan('land-registry:import-inspire', ['file' => $file])
            ->expectsOutputToContain('Import failed and the replacement was rolled back:')
            ->assertFailed();

        $this->assertDatabaseCount('land_registry_inspire', 1);
        $this->assertDatabaseHas('land_registry_inspire', [
            'transaction_id' => '{AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA}',
            'inspire_id' => '99999999',
        ]);
    }

    private function insertExistingRecord(): void
    {
        DB::table('land_registry_inspire')->insert([
            'transaction_id' => '{AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA}',
            'inspire_id' => '99999999',
        ]);
    }

    /**
     * @param  array<int, string>  $rows
     */
    private function createCsv(array $rows): string
    {
        $file = tempnam(sys_get_temp_dir(), 'inspire-import-');

        if ($file === false) {
            $this->fail('Unable to create temporary CSV file.');
        }

        file_put_contents($file, implode(PHP_EOL, $rows).PHP_EOL);
        $this->temporaryFiles[] = $file;

        return $file;
    }

    private function csvRow(int $rowNumber, string $inspireId): string
    {
        return sprintf('{00000000-0000-0000-0000-%012d},%s', $rowNumber, $inspireId);
    }
}
