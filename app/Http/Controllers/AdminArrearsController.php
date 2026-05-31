<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArrearsImportRequest;
use App\Models\MlarArrear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use RuntimeException;

class AdminArrearsController extends Controller
{
    public function index(): View
    {
        $arrears = MlarArrear::orderBy('year', 'desc')
            ->orderBy('quarter', 'desc')
            ->get();

        return view('admin.arrears.index', compact('arrears'));
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'description' => 'required|string',
            'year' => 'required|integer',
            'quarter' => 'required|string',
            'value' => 'required|numeric',
        ]);

        MlarArrear::create($data);

        return back()->with('success', 'New arrears row added.');
    }

    public function import(ArrearsImportRequest $request): RedirectResponse
    {
        try {
            $rows = $this->rowsForImport(
                $request->file('csv_file')->getRealPath()
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'csv_file' => $exception->getMessage(),
            ]);
        }

        if ($rows->isEmpty()) {
            return back()->withErrors([
                'csv_file' => 'The uploaded file did not contain any importable arrears rows.',
            ]);
        }

        $rows->each(function (array $row): void {
            $existingRow = MlarArrear::query()
                ->where('description', $row['description'])
                ->where('year', $row['year'])
                ->where('quarter', $row['quarter'])
                ->first();

            if ($existingRow) {
                $existingRow->update([
                    'value' => $row['value'],
                ]);

                return;
            }

            MlarArrear::query()->create($row);
        });

        return back()->with('success', 'Arrears CSV imported successfully.');
    }

    public function store(Request $request): RedirectResponse
    {
        $rows = $request->input('rows', []);

        foreach ($rows as $row) {
            $id = $row['id'] ?? null;

            if (empty($id)) {
                continue;
            }

            $arrear = MlarArrear::find($id);

            if (! $arrear) {
                continue;
            }

            if (array_key_exists('description', $row) && $row['description'] !== '') {
                $arrear->description = $row['description'];
            }

            if (array_key_exists('year', $row) && $row['year'] !== '') {
                $arrear->year = $row['year'];
            }

            if (array_key_exists('quarter', $row) && $row['quarter'] !== '') {
                $arrear->quarter = $row['quarter'];
            }

            if (array_key_exists('value', $row) && $row['value'] !== '') {
                $arrear->value = $row['value'];
            }

            $arrear->save();
        }

        return back()->with('success', 'Arrears data updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        MlarArrear::findOrFail($id)->delete();

        return back()->with('success', 'Row deleted.');
    }

    private function rowsForImport(string $path): Collection
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to read the uploaded arrears file.');
        }

        $yearHeader = fgetcsv($handle);
        $quarterHeader = fgetcsv($handle);

        if ($yearHeader === false || $quarterHeader === false) {
            fclose($handle);

            throw new RuntimeException('The arrears CSV must contain both a year row and a quarter row.');
        }

        $columns = $this->extractPeriodColumns($yearHeader, $quarterHeader);
        $rows = collect();
        $lineNumber = 2;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $description = trim((string) ($row[0] ?? ''));

            if ($description === '') {
                continue;
            }

            foreach ($columns as $index => $period) {
                $rawValue = (string) ($row[$index] ?? '');

                if (trim($rawValue) === '') {
                    continue;
                }

                $rows->push([
                    'description' => $description,
                    'year' => $period['year'],
                    'quarter' => $period['quarter'],
                    'value' => $this->normalizeImportValue($rawValue, $lineNumber, $description),
                ]);
            }
        }

        fclose($handle);

        return $rows;
    }

    private function extractPeriodColumns(array $yearHeader, array $quarterHeader): array
    {
        $columns = [];
        $currentYear = null;

        foreach ($quarterHeader as $index => $quarterCell) {
            if ($index === 0) {
                continue;
            }

            $yearCell = trim((string) ($yearHeader[$index] ?? ''));

            if ($yearCell !== '') {
                if (! ctype_digit($yearCell)) {
                    throw new RuntimeException("Unable to parse arrears year header [{$yearCell}] in column {$index}.");
                }

                $currentYear = (int) $yearCell;
            }

            $quarter = strtoupper(trim((string) $quarterCell));

            if ($quarter === '') {
                continue;
            }

            if ($currentYear === null) {
                throw new RuntimeException("Missing year header before quarter [{$quarter}] in column {$index}.");
            }

            if (! in_array($quarter, ['Q1', 'Q2', 'Q3', 'Q4'], true)) {
                throw new RuntimeException("Unable to parse arrears quarter header [{$quarter}] in column {$index}.");
            }

            $columns[$index] = [
                'year' => $currentYear,
                'quarter' => $quarter,
            ];
        }

        if ($columns === []) {
            throw new RuntimeException('The arrears CSV header did not contain any year/quarter columns.');
        }

        return $columns;
    }

    private function rowIsEmpty(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }

    private function normalizeImportValue(string $value, int $lineNumber, string $description): float
    {
        $normalized = trim(str_replace(',', '.', $value));

        if ($normalized === '' || ! is_numeric($normalized)) {
            throw new RuntimeException("Unable to parse arrears value [{$value}] for [{$description}] on line {$lineNumber}.");
        }

        return round((float) $normalized, 3);
    }
}
