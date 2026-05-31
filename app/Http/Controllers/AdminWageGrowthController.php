<?php

namespace App\Http\Controllers;

use App\Http\Requests\WageGrowthImportRequest;
use App\Models\WageGrowthMonthly;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use RuntimeException;

class AdminWageGrowthController extends Controller
{
    public function index(): View
    {
        $wagegrowth = WageGrowthMonthly::orderBy('date', 'desc')->get();

        return view('admin.wagegrowth.index', compact('wagegrowth'));
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => 'required|date',
            'three_month_avg_yoy' => 'nullable|numeric',
        ]);

        WageGrowthMonthly::create($data);

        return back()->with('success', 'New wage growth row added.');
    }

    public function import(WageGrowthImportRequest $request): RedirectResponse
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
                'csv_file' => 'The uploaded file did not contain any importable wage growth rows.',
            ]);
        }

        $rows->each(function (array $row): void {
            $existingRow = WageGrowthMonthly::query()
                ->whereDate('date', $row['date'])
                ->first();

            if ($existingRow) {
                $existingRow->update([
                    'three_month_avg_yoy' => $row['three_month_avg_yoy'],
                ]);

                return;
            }

            WageGrowthMonthly::query()->create([
                'date' => $row['date'],
                'three_month_avg_yoy' => $row['three_month_avg_yoy'],
            ]);
        });

        return back()->with('success', 'Wage growth CSV imported successfully.');
    }

    public function store(Request $request): RedirectResponse
    {
        $rows = $request->input('rows', []);

        foreach ($rows as $row) {
            $id = $row['id'] ?? null;
            $date = $row['date'] ?? null;
            $threeMonthAverage = $row['three_month_avg_yoy'] ?? null;

            if (empty($id)) {
                continue;
            }

            if (
                ($date === null || $date === '') &&
                ($threeMonthAverage === null || $threeMonthAverage === '')
            ) {
                continue;
            }

            WageGrowthMonthly::where('id', $id)->update([
                'date' => $date,
                'three_month_avg_yoy' => $threeMonthAverage,
            ]);
        }

        return back()->with('success', 'Wage growth data saved successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        WageGrowthMonthly::findOrFail($id)->delete();

        return back()->with('success', 'Row deleted.');
    }

    private function rowsForImport(string $path): Collection
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to read the uploaded wage growth file.');
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
                'three_month_avg_yoy' => $this->normalizeImportValue((string) ($row[1] ?? ''), $lineNumber),
                'created_at' => now(),
                'updated_at' => now(),
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
        $firstColumn = strtoupper(trim((string) ($row[0] ?? '')));

        return in_array($firstColumn, ['UNIT', 'DATE', 'MONTH'], true);
    }

    private function normalizeImportDate(string $value, int $lineNumber): string
    {
        $normalized = strtoupper(preg_replace('/\s+/', ' ', trim($value)) ?? '');
        $formats = ['Y M', 'M Y', 'Y-m-d', 'Y/m/d', 'd/m/Y', 'd-m-Y'];

        foreach ($formats as $format) {
            $date = CarbonImmutable::createFromFormat('!'.$format, $normalized);

            if ($date !== false) {
                return $date->startOfMonth()->toDateString();
            }
        }

        throw new RuntimeException("Unable to parse wage growth date [{$value}] on line {$lineNumber}.");
    }

    private function normalizeImportValue(string $value, int $lineNumber): float
    {
        $normalized = trim(str_replace(',', '.', $value));

        if ($normalized === '' || ! is_numeric($normalized)) {
            throw new RuntimeException("Unable to parse wage growth value [{$value}] on line {$lineNumber}.");
        }

        return round((float) $normalized, 2);
    }
}
