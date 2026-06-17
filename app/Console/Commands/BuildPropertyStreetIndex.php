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
            ->select(['Street', 'Postcode', 'TownCity', 'Locality'])
            ->where('PPDCategoryType', 'A')
            ->whereNotNull('Street')
            ->where('Street', '<>', '')
            ->whereNotNull('Postcode')
            ->where('Postcode', '<>', '')
            ->cursor()
            ->each(function (object $row) use (&$groups): void {
                $street = $this->normalizeStreet((string) ($row->Street ?? ''));
                $outcode = $this->extractOutcode((string) ($row->Postcode ?? ''));
                $postcode = $this->normalizePostcode((string) ($row->Postcode ?? ''));

                if ($street === null || $outcode === null || $postcode === null) {
                    return;
                }

                $key = $street.'|'.$outcode;

                if (! array_key_exists($key, $groups)) {
                    $groups[$key] = [
                        'street' => $street,
                        'outcode' => $outcode,
                        'sales_count' => 0,
                        'postcodes' => [],
                        'towns' => [],
                        'localities' => [],
                    ];
                }

                if (! array_key_exists($postcode, $groups[$key]['postcodes'])) {
                    $groups[$key]['postcodes'][$postcode] = [
                        'count' => 0,
                        'towns' => [],
                        'localities' => [],
                    ];
                }

                $groups[$key]['sales_count']++;
                $groups[$key]['postcodes'][$postcode]['count']++;

                $town = $this->normalizePlace((string) ($row->TownCity ?? ''));
                $locality = $this->normalizePlace((string) ($row->Locality ?? ''));

                if ($town !== null) {
                    $groups[$key]['towns'][$town] = ($groups[$key]['towns'][$town] ?? 0) + 1;
                    $groups[$key]['postcodes'][$postcode]['towns'][$town] = ($groups[$key]['postcodes'][$postcode]['towns'][$town] ?? 0) + 1;
                }

                if ($locality !== null) {
                    $groups[$key]['localities'][$locality] = ($groups[$key]['localities'][$locality] ?? 0) + 1;
                    $groups[$key]['postcodes'][$postcode]['localities'][$locality] = ($groups[$key]['postcodes'][$postcode]['localities'][$locality] ?? 0) + 1;
                }
            });

        $payload = collect($groups)
            ->filter(fn (array $group): bool => $group['sales_count'] >= self::MIN_SALES)
            ->map(function (array $group): array {
                $street = $group['street'];
                $outcode = $group['outcode'];
                $slug = Str::slug($street);

                return [
                    'street' => $street,
                    'slug' => $slug,
                    'outcode' => $outcode,
                    'place' => $this->representativePlace($group),
                    'sales_count' => $group['sales_count'],
                    'url' => PropertyStreetController::streetPath($outcode, $slug),
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

    private function normalizePostcode(string $postcode): ?string
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $postcode) ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizePlace(string $value): ?string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return $normalized !== '' ? Str::title(Str::lower($normalized)) : null;
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private function representativePlace(array $group): string
    {
        /** @var array<string, mixed>|null $primaryPostcode */
        $primaryPostcode = $this->topCountedEntry($group['postcodes'] ?? []);

        if (is_array($primaryPostcode)) {
            $town = $this->topCountedKey($primaryPostcode['towns'] ?? []);

            if ($town !== null) {
                return $town;
            }

            $locality = $this->topCountedKey($primaryPostcode['localities'] ?? []);

            if ($locality !== null) {
                return $locality;
            }
        }

        $town = $this->topCountedKey($group['towns'] ?? []);

        if ($town !== null) {
            return $town;
        }

        $locality = $this->topCountedKey($group['localities'] ?? []);

        if ($locality !== null) {
            return $locality;
        }

        return (string) ($group['outcode'] ?? '');
    }

    /**
     * @param  array<string, array<string, mixed>>  $entries
     * @return array<string, mixed>|null
     */
    private function topCountedEntry(array $entries): ?array
    {
        if ($entries === []) {
            return null;
        }

        uksort($entries, function (string $left, string $right) use ($entries): int {
            $countCompare = ((int) ($entries[$right]['count'] ?? 0)) <=> ((int) ($entries[$left]['count'] ?? 0));

            if ($countCompare !== 0) {
                return $countCompare;
            }

            return strcmp($left, $right);
        });

        $key = array_key_first($entries);

        return $key !== null ? $entries[$key] : null;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function topCountedKey(array $counts): ?string
    {
        if ($counts === []) {
            return null;
        }

        uksort($counts, function (string $left, string $right) use ($counts): int {
            $countCompare = $counts[$right] <=> $counts[$left];

            if ($countCompare !== 0) {
                return $countCompare;
            }

            return strcmp($left, $right);
        });

        $key = array_key_first($counts);

        return is_string($key) ? $key : null;
    }
}
