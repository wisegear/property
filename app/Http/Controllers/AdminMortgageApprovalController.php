<?php

namespace App\Http\Controllers;

use App\Http\Requests\MortgageApprovalImportRequest;
use App\Models\MortgageApproval;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use RuntimeException;

class AdminMortgageApprovalController extends Controller
{
    public function index(): View
    {
        $approvals = MortgageApproval::orderBy('period', 'desc')->get();

        return view('admin.mortgageapprovals.index', compact('approvals'));
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'series_code' => 'required|string|max:32',
            'period' => 'required|date',
            'value' => 'nullable|integer',
            'unit' => 'nullable|string|max:16',
            'source' => 'nullable|string|max:64',
        ]);

        MortgageApproval::create($data);

        return back()->with('success', 'Mortgage approval entry added successfully.');
    }

    public function import(MortgageApprovalImportRequest $request): RedirectResponse
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
                'csv_file' => 'The uploaded file did not contain any importable mortgage approvals rows.',
            ]);
        }

        $rows->each(function (array $row): void {
            $existingRow = MortgageApproval::query()
                ->where('series_code', $row['series_code'])
                ->whereDate('period', $row['period'])
                ->first();

            if ($existingRow) {
                $existingRow->update([
                    'value' => $row['value'],
                    'unit' => $row['unit'],
                    'source' => $row['source'],
                ]);

                return;
            }

            MortgageApproval::query()->create($row);
        });

        return back()->with('success', 'Mortgage approvals CSV imported successfully.');
    }

    public function store(Request $request): RedirectResponse
    {
        $rows = $request->input('rows', []);

        foreach ($rows as $row) {
            $id = $row['id'] ?? null;

            if (! $id) {
                continue;
            }

            $approval = MortgageApproval::find($id);

            if (! $approval) {
                continue;
            }

            if (array_key_exists('series_code', $row) && $row['series_code'] !== '') {
                $approval->series_code = $row['series_code'];
            }

            if (array_key_exists('period', $row) && $row['period'] !== '') {
                $approval->period = $row['period'];
            }

            if (array_key_exists('value', $row)) {
                $approval->value = $row['value'] !== '' ? $row['value'] : null;
            }

            if (array_key_exists('unit', $row)) {
                $approval->unit = $row['unit'] !== '' ? $row['unit'] : null;
            }

            if (array_key_exists('source', $row) && $row['source'] !== '') {
                $approval->source = $row['source'];
            }

            $approval->save();
        }

        return back()->with('success', 'Mortgage approvals updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        MortgageApproval::findOrFail($id)->delete();

        return back()->with('success', 'Mortgage approval entry deleted.');
    }

    private function rowsForImport(string $path): Collection
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to read the uploaded mortgage approvals file.');
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            throw new RuntimeException('The uploaded mortgage approvals file is empty.');
        }

        $seriesCodes = $this->extractSeriesCodes($header);
        $rows = collect();
        $lineNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $period = $this->normalizeImportDate((string) ($row[0] ?? ''), $lineNumber);

            foreach ($seriesCodes as $index => $seriesCode) {
                $rawValue = (string) ($row[$index] ?? '');

                if (trim($rawValue) === '') {
                    continue;
                }

                $rows->push([
                    'series_code' => $seriesCode,
                    'period' => $period,
                    'value' => $this->normalizeImportValue($rawValue, $lineNumber, $seriesCode),
                    'unit' => 'count',
                    'source' => 'BoE',
                ]);
            }
        }

        fclose($handle);

        return $rows;
    }

    private function extractSeriesCodes(array $header): array
    {
        $seriesCodes = [];

        foreach ($header as $index => $cell) {
            if ($index === 0) {
                continue;
            }

            $seriesCode = strtoupper(trim((string) $cell));

            if ($seriesCode === '') {
                continue;
            }

            $seriesCodes[$index] = $seriesCode;
        }

        if ($seriesCodes === []) {
            throw new RuntimeException('The mortgage approvals CSV header did not contain any series codes.');
        }

        return $seriesCodes;
    }

    private function rowIsEmpty(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }

    private function normalizeImportDate(string $value, int $lineNumber): string
    {
        $normalized = trim($value);
        $formats = ['d-M-y', 'd-M-Y', 'Y-m-d', 'd/m/Y', 'd-m-Y'];

        foreach ($formats as $format) {
            $date = CarbonImmutable::createFromFormat('!'.$format, $normalized);

            if ($date !== false) {
                return $date->startOfMonth()->toDateString();
            }
        }

        throw new RuntimeException("Unable to parse mortgage approvals date [{$value}] on line {$lineNumber}.");
    }

    private function normalizeImportValue(string $value, int $lineNumber, string $seriesCode): int
    {
        $normalized = str_replace([',', ' '], '', trim($value));

        if ($normalized === '' || ! ctype_digit($normalized)) {
            throw new RuntimeException("Unable to parse mortgage approvals value [{$value}] for [{$seriesCode}] on line {$lineNumber}.");
        }

        return (int) $normalized;
    }
}
