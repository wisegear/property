<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TopSalesService
{
    /**
     * @return array{
     *     label: string,
     *     title: string,
     *     description: string,
     *     min_price: ?int,
     *     max_price: ?int,
     *     county: ?string,
     *     exclude_county: ?string,
     *     uses_rest_percentile: bool
     * }
     */
    public function modeConfig(string $mode): array
    {
        return match ($this->normalizeMode($mode)) {
            'ultra' => [
                'label' => 'Ultra Prime London (£10m+)',
                'title' => 'Ultra Prime London Property Sales (£10m+)',
                'description' => 'Ultra high-value property transactions across London.',
                'min_price' => 10000000,
                'max_price' => null,
                'county' => 'GREATER LONDON',
                'exclude_county' => null,
                'uses_rest_percentile' => false,
            ],
            'london' => [
                'label' => 'Prime London (£2m-£10m)',
                'title' => 'Prime London Property Sales (£2m-£10m)',
                'description' => 'High-value property transactions across London.',
                'min_price' => 2000000,
                'max_price' => 10000000,
                'county' => 'GREATER LONDON',
                'exclude_county' => null,
                'uses_rest_percentile' => false,
            ],
            default => [
                'label' => 'Top 1% Rest of UK',
                'title' => 'Top 1% Property Sales (Rest of UK)',
                'description' => 'The highest value property transactions outside London.',
                'min_price' => null,
                'max_price' => null,
                'county' => null,
                'exclude_county' => 'GREATER LONDON',
                'uses_rest_percentile' => true,
            ],
        };
    }

    public function lastWarmedCacheKey(): string
    {
        return 'top_sales:last_warmed_at';
    }

    public function cachedSales(string $mode): Collection
    {
        $normalizedMode = $this->normalizeMode($mode);

        return Cache::remember(
            $this->cacheKey($normalizedMode),
            now()->addDays(45),
            fn (): Collection => $this->querySales($normalizedMode)
        );
    }

    public function warmMode(string $mode): Collection
    {
        $normalizedMode = $this->normalizeMode($mode);

        Cache::forget($this->cacheKey($normalizedMode));

        return $this->cachedSales($normalizedMode);
    }

    public function cacheKey(string $mode): string
    {
        return 'top_sales:'.$this->normalizeMode($mode);
    }

    public function normalizeMode(string $mode): string
    {
        return in_array($mode, ['ultra', 'london', 'rest'], true) ? $mode : 'ultra';
    }

    private function querySales(string $mode): Collection
    {
        $config = $this->modeConfig($mode);

        if ($config['uses_rest_percentile']) {
            return $this->queryRestOfUkSales($config['exclude_county'], $config['max_price']);
        }

        return DB::query()
            ->from('land_registry')
            ->select([
                'Price',
                'Date',
                'Postcode',
                'PAON',
                'SAON',
                'Street',
                'TownCity',
                'District',
                'County',
                'PropertyType',
            ])
            ->where('PPDCategoryType', 'A')
            ->when(
                $config['min_price'] !== null,
                fn ($query) => $query->where('Price', '>=', $config['min_price'])
            )
            ->when(
                $config['max_price'] !== null,
                fn ($query) => $query->where('Price', '<', $config['max_price'])
            )
            ->when(
                $config['county'] !== null,
                fn ($query) => $query->where('County', $config['county'])
            )
            ->orderByDesc('Price')
            ->limit(200)
            ->get()
            ->map(fn (object $sale): object => $this->appendPropertySlug($sale));
    }

    private function queryRestOfUkSales(?string $excludedCounty, ?int $maxPrice): Collection
    {
        $baseQuery = DB::query()
            ->from('land_registry')
            ->select([
                'Price',
                'Date',
                'Postcode',
                'PAON',
                'SAON',
                'Street',
                'TownCity',
                'District',
                'County',
                'PropertyType',
            ])
            ->selectRaw('NTILE(100) OVER (ORDER BY "Price" DESC) as percentile_rank')
            ->where('PPDCategoryType', 'A')
            ->when(
                $excludedCounty !== null,
                fn ($query) => $query->where('County', '<>', $excludedCounty)
            )
            ->when(
                $maxPrice !== null,
                fn ($query) => $query->where('Price', '<', $maxPrice)
            );

        return DB::query()
            ->fromSub($baseQuery, 'ranked_sales')
            ->select([
                'Price',
                'Date',
                'Postcode',
                'PAON',
                'SAON',
                'Street',
                'TownCity',
                'District',
                'County',
                'PropertyType',
            ])
            ->where('percentile_rank', 1)
            ->orderByDesc('Price')
            ->limit(200)
            ->get()
            ->map(fn (object $sale): object => $this->appendPropertySlug($sale));
    }

    private function appendPropertySlug(object $sale): object
    {
        $sale->property_slug = $this->buildPropertySlug(
            (string) ($sale->Postcode ?? ''),
            (string) ($sale->PAON ?? ''),
            (string) ($sale->Street ?? ''),
            $sale->SAON !== null ? (string) $sale->SAON : null
        );

        return $sale;
    }

    private function buildPropertySlug(string $postcode, string $paon, string $street, ?string $saon = null): string
    {
        $parts = [
            $this->normalizeSlugPart($postcode),
            $this->normalizeSlugPart($paon),
            $this->normalizeSlugPart($street),
        ];

        if ($saon !== null && trim($saon) !== '') {
            $parts[] = $this->normalizeSlugPart($saon);
        }

        $parts = array_values(array_filter($parts, fn (string $part): bool => $part !== ''));

        return preg_replace('/-+/', '-', implode('-', $parts)) ?? '';
    }

    private function normalizeSlugPart(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(',', '', $normalized);
        $normalized = preg_replace('/\s+/', '-', $normalized) ?? '';
        $normalized = preg_replace('/-+/', '-', $normalized) ?? '';

        return trim($normalized, '-');
    }
}
