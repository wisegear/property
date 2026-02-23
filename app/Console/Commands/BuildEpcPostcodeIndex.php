<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BuildEpcPostcodeIndex extends Command
{
    protected $signature = 'epc:build-postcode-index';

    protected $description = 'Build postcode index JSON for EPC England/Wales and Scotland datasets.';

    private const MIN_CERTIFICATES = 30;

    private const FROM_DATE = '2015-01-01';

    public function handle(): int
    {
        $englandWalesPostcodes = $this->distinctPostcodes('epc_certificates');
        $scotlandPostcodes = $this->distinctPostcodes('epc_certificates_scotland');

        $payload = [
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'min_certificates' => self::MIN_CERTIFICATES,
                'from_year' => 2015,
            ],
            'postcodes' => [
                'england_wales' => $englandWalesPostcodes,
                'scotland' => $scotlandPostcodes,
            ],
        ];

        $directory = public_path('data');
        if (! is_dir($directory)) {
            if (! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                $this->error('Failed to create target directory: '.$directory);

                return self::FAILURE;
            }
        }

        $path = $directory.'/epc-postcodes.json';
        file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->info('EPC postcode index written to '.$path);
        $this->line('England & Wales postcodes: '.number_format(count($englandWalesPostcodes)));
        $this->line('Scotland postcodes: '.number_format(count($scotlandPostcodes)));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function distinctPostcodes(string $table): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $postcodeColumn = $this->resolveColumn($table, ['POSTCODE', 'postcode']);
        $inspectionDateColumn = $this->resolveColumn($table, ['INSPECTION_DATE', 'inspection_date']);
        if ($postcodeColumn === null || $inspectionDateColumn === null) {
            return [];
        }

        $postcodes = DB::table($table)
            ->select($postcodeColumn)
            ->selectRaw('COUNT(*) as certificates_count')
            ->whereNotNull($postcodeColumn)
            ->where($postcodeColumn, '<>', '')
            ->whereNotNull($inspectionDateColumn)
            ->where($inspectionDateColumn, '>=', self::FROM_DATE)
            ->groupBy($postcodeColumn)
            ->havingRaw('COUNT(*) >= ?', [self::MIN_CERTIFICATES])
            ->orderBy($postcodeColumn)
            ->pluck($postcodeColumn)
            ->map(fn ($postcode) => trim((string) $postcode))
            ->filter(fn (string $postcode) => $postcode !== '')
            ->values()
            ->all();

        sort($postcodes, SORT_STRING);

        return array_values(array_unique($postcodes));
    }

    private function resolveColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (Schema::hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
