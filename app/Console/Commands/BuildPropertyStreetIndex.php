<?php

namespace App\Console\Commands;

use App\Http\Controllers\PropertyStreetController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BuildPropertyStreetIndex extends Command
{
    protected $signature = 'property:build-street-index';

    protected $description = 'Build a street + outcode autocomplete index from Land Registry sales data.';

    private const MIN_SALES = 3;

    public function handle(): int
    {
        if (! Schema::hasTable('land_registry')) {
            $this->error('Land Registry table not found.');

            return self::FAILURE;
        }

        $groups = [];

        DB::table('land_registry')
            ->select(['Street', 'Postcode'])
            ->where('PPDCategoryType', 'A')
            ->whereNotNull('Street')
            ->where('Street', '<>', '')
            ->whereNotNull('Postcode')
            ->where('Postcode', '<>', '')
            ->cursor()
            ->each(function (object $row) use (&$groups): void {
                $street = $this->normalizeStreet((string) ($row->Street ?? ''));
                $outcode = $this->extractOutcode((string) ($row->Postcode ?? ''));

                if ($street === null || $outcode === null) {
                    return;
                }

                $key = $street.'|'.$outcode;

                if (! array_key_exists($key, $groups)) {
                    $groups[$key] = [
                        'street' => $street,
                        'outcode' => $outcode,
                        'count' => 0,
                    ];
                }

                $groups[$key]['count']++;
            });

        $payload = collect($groups)
            ->filter(fn (array $group): bool => $group['count'] >= self::MIN_SALES)
            ->map(function (array $group): array {
                $street = $group['street'];
                $outcode = $group['outcode'];

                return [
                    'street' => $street,
                    'outcode' => $outcode,
                    'label' => $street.', '.$outcode,
                    'path' => PropertyStreetController::streetPath($outcode, Str::slug($street)),
                    'search' => Str::lower($street.' '.$outcode),
                ];
            })
            ->sort(function (array $left, array $right): int {
                $streetCompare = strcmp($left['street'], $right['street']);

                if ($streetCompare !== 0) {
                    return $streetCompare;
                }

                return strcmp($left['outcode'], $right['outcode']);
            })
            ->values()
            ->all();

        $directory = public_path('data');

        if (! is_dir($directory)) {
            if (! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                $this->error('Failed to create target directory: '.$directory);

                return self::FAILURE;
            }
        }

        $path = $directory.'/property_streets.json';

        file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->info('Property street index written to '.$path);
        $this->line('Indexed street/outcode combinations: '.number_format(count($payload)));
        $this->line('Minimum sales threshold: '.self::MIN_SALES);

        return self::SUCCESS;
    }

    private function normalizeStreet(string $street): ?string
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $street) ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function extractOutcode(string $postcode): ?string
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', trim($postcode)) ?? '');

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^([A-Z]{1,2}\d[A-Z\d]?)\d[A-Z]{2}$/', $normalized, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
