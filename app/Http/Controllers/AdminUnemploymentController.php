<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnemploymentImportRequest;
use App\Models\UnemploymentMonthly;
use Carbon\CarbonImmutable;
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

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            if ($lineNumber === 1 && $this->looksLikeHeader($row)) {
                continue;
            }

            $rows->push([
                'date' => $this->normalizeImportDate((string) ($row[0] ?? ''), $lineNumber),
                'single_month' => $this->normalizeImportInteger((string) ($row[1] ?? ''), $lineNumber, 'single_month'),
                'single' => $this->normalizeImportDecimal((string) ($row[2] ?? ''), $lineNumber, 'single'),
                'three_month' => $this->normalizeImportDecimal((string) ($row[3] ?? ''), $lineNumber, 'three_month'),
            ]);
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
            || $cells->contains('three_month');
    }

    private function normalizeImportDate(string $value, int $lineNumber): string
    {
        $normalized = trim($value);
        $formats = ['M-y', 'M Y', 'Y-m-d', 'Y/m/d', 'd/m/Y', 'd-m-Y'];

        foreach ($formats as $format) {
            $date = CarbonImmutable::createFromFormat('!'.$format, $normalized);

            if ($date !== false) {
                return $date->startOfMonth()->toDateString();
            }
        }

        throw new RuntimeException("Unable to parse unemployment date [{$value}] on line {$lineNumber}.");
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
