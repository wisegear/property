<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ScottishPropertyPricesImport implements SkipsEmptyRows, ToCollection, WithChunkReading, WithHeadingRow
{
    private int $importedRowCount = 0;

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        DB::connection()->disableQueryLog();

        $timestamp = now();
        $payload = [];

        foreach ($rows as $row) {
            $record = $this->mapRow($row);

            if ($record === null) {
                continue;
            }

            $payload[] = array_merge($record, [
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        if ($payload === []) {
            return;
        }

        foreach (array_chunk($payload, $this->batchSize()) as $batch) {
            DB::table('scottish_property_prices')->upsert(
                $batch,
                ['month', 'local_authority_code'],
                [
                    'local_authority',
                    'median_residential_property_price',
                    'mean_residential_property_price',
                    'volume_of_residential_property_sales',
                    'value_of_residential_property_sales',
                    'updated_at',
                ]
            );

            $this->importedRowCount += count($batch);
        }
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function batchSize(): int
    {
        return 1000;
    }

    public function importedRowCount(): int
    {
        return $this->importedRowCount;
    }

    public function isEmptyWhen(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->cleanString($value) !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  Collection<string, mixed>  $row
     * @return array<string, int|string|null>|null
     */
    private function mapRow(Collection $row): ?array
    {
        $month = $this->cleanString($row->get('month'));
        $localAuthorityCode = $this->cleanString($row->get('local_authority_code'));

        if ($month === null || $localAuthorityCode === null) {
            return null;
        }

        return [
            'month' => $month,
            'local_authority_code' => $localAuthorityCode,
            'volume_of_residential_property_sales' => $this->cleanInteger(
                $row->get('volume_of_residential_property_sales')
            ),
            'mean_residential_property_price' => $this->cleanInteger(
                $row->get('mean_residential_property_price')
            ),
            'median_residential_property_price' => $this->cleanInteger(
                $row->get('median_residential_property_price')
            ),
            'value_of_residential_property_sales' => $this->cleanInteger(
                $row->get('value_of_residential_property_sales')
            ),
            'local_authority' => $this->cleanString($row->get('local_authority')),
        ];
    }

    private function cleanInteger(mixed $value): ?int
    {
        $cleaned = $this->cleanString($value);

        if ($cleaned === null) {
            return null;
        }

        $numeric = str_replace(',', '', $cleaned);

        if (! is_numeric($numeric)) {
            return null;
        }

        return (int) round((float) $numeric);
    }

    private function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $cleaned = trim((string) $value);

        return $cleaned === '' ? null : $cleaned;
    }
}
