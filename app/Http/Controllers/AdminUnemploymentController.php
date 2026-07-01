<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnemploymentImportRequest;
use App\Models\UnemploymentMonthly;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use RuntimeException;

class AdminUnemploymentController extends Controller
{
    public function index(): View
    {
        $unemployment = UnemploymentMonthly::orderBy('date', 'desc')->get();

        return view('admin.unemployment.index', compact('unemployment'));
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => 'required|date',
            'single_month' => 'nullable|integer|min:0',
            'single' => 'nullable|numeric',
            'three_month' => 'nullable|numeric',
        ]);

        UnemploymentMonthly::create($data);

        return back()->with('success', 'New unemployment row added.');
    }

    public function import(UnemploymentImportRequest $request): RedirectResponse
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
                'csv_file' => 'The uploaded file did not contain any importable unemployment rows.',
            ]);
        }

        $rows->each(function (array $row): void {
            $existingRow = UnemploymentMonthly::query()
                ->whereDate('date', $row['date'])
                ->first();

            if ($existingRow) {
                $existingRow->update([
                    'single_month' => $row['single_month'],
                    'single' => $row['single'],
                    'three_month' => $row['three_month'],
                ]);

                return;
            }

            UnemploymentMonthly::query()->create([
                'date' => $row['date'],
                'single_month' => $row['single_month'],
                'single' => $row['single'],
                'three_month' => $row['three_month'],
            ]);
        });

        return back()->with('success', 'Unemployment CSV imported successfully.');
    }

    public function store(Request $request): RedirectResponse
    {
        $rows = $request->input('rows', []);

        foreach ($rows as $row) {
            $id = $row['id'] ?? null;
            $date = $row['date'] ?? null;
            $singleMonth = $row['single_month'] ?? null;
            $single = $row['single'] ?? null;
            $threeMonth = $row['three_month'] ?? null;

            if (empty($id)) {
                continue;
            }

            if (
                ($date === null || $date === '') &&
                ($singleMonth === null || $singleMonth === '') &&
                ($single === null || $single === '') &&
                ($threeMonth === null || $threeMonth === '')
            ) {
                continue;
            }

            UnemploymentMonthly::where('id', $id)->update([
                'date' => $date,
                'single_month' => $singleMonth,
                'single' => $single,
                'three_month' => $threeMonth,
            ]);
        }

        return back()->with('success', 'Unemployment data saved successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        UnemploymentMonthly::findOrFail($id)->delete();

        return back()->with('success', 'Row deleted.');
    }

    private function rowsForImport(string $path): Collection
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to read the uploaded unemployment file.');
        }

        $rows = collect();
        $lineNumber = 0;
        $hasSeenImportableRow = false;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            if ($lineNumber === 1 && $this->looksLikeHeader($row)) {
                continue;
            }

            $columns = $this->importColumnsForRow($row);
            if ($columns === null) {
                if ($hasSeenImportableRow) {
                    throw new RuntimeException("Unable to parse unemployment row on line {$lineNumber}.");
                }

                continue;
            }

            $rows->push([
                'date' => $this->normalizeImportDate($columns['date'], $lineNumber),
                'single_month' => $this->normalizeImportInteger($columns['single_month'], $lineNumber, 'single_month'),
                'single' => $this->normalizeImportDecimal($columns['single'], $lineNumber, 'single'),
                'three_month' => $this->normalizeImportDecimal($columns['three_month'], $lineNumber, 'three_month'),
            ]);
            $hasSeenImportableRow = true;
        }

        fclose($handle);

        return $rows;
    }

    private function rowIsEmpty(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }

    private function looksLikeHeader(array $row): bool
    {
        $cells = collect($row)
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter();

        return $cells->contains('single_month')
            || $cells->contains('single')
            || $cells->contains('single month')
            || $cells->contains('three_month');
    }

    /**
     * @return array{date:string,single_month:string,single:string,three_month:string}|null
     */
    private function importColumnsForRow(array $row): ?array
    {
        $date = (string) ($row[0] ?? '');

        if (trim($date) === '' || ! $this->canParseImportDate($date)) {
            return null;
        }

        if (trim((string) ($row[1] ?? '')) !== '') {
            return [
                'date' => $date,
                'single_month' => (string) ($row[1] ?? ''),
                'single' => (string) ($row[2] ?? ''),
                'three_month' => (string) ($row[3] ?? ''),
            ];
        }

        if (trim((string) ($row[2] ?? '')) !== '') {
            return [
                'date' => $date,
                'single_month' => (string) ($row[2] ?? ''),
                'single' => (string) ($row[3] ?? ''),
                'three_month' => (string) ($row[4] ?? ''),
            ];
        }

        return null;
    }

    private function normalizeImportDate(string $value, int $lineNumber): string
    {
        $normalized = $this->normalizeImportDateValue($value);
        $formats = ['M-y', 'M Y', 'Y-m-d', 'Y/m/d', 'd/m/Y', 'd-m-Y'];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $normalized, new \DateTimeZone('UTC'));
            $errors = \DateTimeImmutable::getLastErrors();

            if ($date instanceof \DateTimeImmutable && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->modify('first day of this month')->format('Y-m-d');
            }
        }

        throw new RuntimeException("Unable to parse unemployment date [{$value}] on line {$lineNumber}.");
    }

    private function canParseImportDate(string $value): bool
    {
        try {
            $this->normalizeImportDate($value, 0);
        } catch (RuntimeException) {
            return false;
        }

        return true;
    }

    private function normalizeImportDateValue(string $value): string
    {
        return trim(preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value);
    }

    private function normalizeImportInteger(string $value, int $lineNumber, string $column): int
    {
        $normalized = str_replace([',', ' '], '', trim($value));

        if ($normalized === '' || ! ctype_digit($normalized)) {
            throw new RuntimeException("Unable to parse unemployment {$column} value [{$value}] on line {$lineNumber}.");
        }

        return (int) $normalized;
    }

    private function normalizeImportDecimal(string $value, int $lineNumber, string $column): float
    {
        $normalized = trim(str_replace(',', '.', $value));

        if ($normalized === '' || ! is_numeric($normalized)) {
            throw new RuntimeException("Unable to parse unemployment {$column} value [{$value}] on line {$lineNumber}.");
        }

        return round((float) $normalized, 2);
    }
}
