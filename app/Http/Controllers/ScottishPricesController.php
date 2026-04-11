<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ScottishPricesController extends Controller
{
    public function index(Request $request): View
    {
        $localAuthorities = Cache::remember(
            'scottish_prices:authorities',
            now()->addDays(45),
            fn (): array => DB::table('scottish_property_prices')
                ->whereNotNull('local_authority')
                ->whereRaw("trim(local_authority) <> ''")
                ->distinct()
                ->orderBy('local_authority')
                ->pluck('local_authority')
                ->map(fn (string $authority): string => trim($authority))
                ->filter()
                ->unique()
                ->values()
                ->all()
        );

        $requestedAuthority = trim((string) $request->query('local_authority', ''));
        $selectedAuthority = in_array($requestedAuthority, $localAuthorities, true) ? $requestedAuthority : null;

        $dataset = $this->yearlyDataset($selectedAuthority);

        $stats = [
            'latestYear' => $dataset['years'] !== [] ? end($dataset['years']) : null,
            'latestMeanPrice' => $dataset['meanPrices'] !== [] ? end($dataset['meanPrices']) : null,
            'latestMedianPrice' => $dataset['medianPrices'] !== [] ? end($dataset['medianPrices']) : null,
            'latestSalesVolume' => $dataset['salesVolumes'] !== [] ? end($dataset['salesVolumes']) : null,
            'latestSalesValue' => $dataset['salesValues'] !== [] ? end($dataset['salesValues']) : null,
        ];

        return view('property.scottish-prices', [
            'localAuthorities' => $localAuthorities,
            'selectedAuthority' => $selectedAuthority,
            'years' => $dataset['years'],
            'meanPrices' => $dataset['meanPrices'],
            'medianPrices' => $dataset['medianPrices'],
            'salesVolumes' => $dataset['salesVolumes'],
            'salesValues' => $dataset['salesValues'],
            'stats' => $stats,
        ]);
    }

    /**
     * @return array{
     *     years: array<int>,
     *     meanPrices: array<float>,
     *     medianPrices: array<float>,
     *     salesVolumes: array<int>,
     *     salesValues: array<float>
     * }
     */
    private function yearlyDataset(?string $localAuthority): array
    {
        $cacheKey = $localAuthority === null
            ? 'scottish_prices:scotland'
            : 'scottish_prices:la:'.md5(mb_strtolower($localAuthority));

        return Cache::remember($cacheKey, now()->addDays(45), function () use ($localAuthority): array {
            $yearExpression = $this->yearExpression('month');

            $query = DB::table('scottish_property_prices')
                ->selectRaw($yearExpression.' as year')
                ->selectRaw('AVG(mean_residential_property_price) as mean_price')
                ->selectRaw('AVG(median_residential_property_price) as median_price')
                ->selectRaw('SUM(volume_of_residential_property_sales) as sales_volume')
                ->selectRaw('SUM(value_of_residential_property_sales) as sales_value')
                ->whereRaw($yearExpression.' is not null');

            if ($localAuthority !== null) {
                $query->where('local_authority', $localAuthority);
            }

            $rows = $query
                ->groupBy('year')
                ->orderBy('year')
                ->get();

            return [
                'years' => $rows->pluck('year')->map(fn (mixed $year): int => (int) $year)->all(),
                'meanPrices' => $rows->pluck('mean_price')->map(fn (mixed $value): float => round((float) $value, 2))->all(),
                'medianPrices' => $rows->pluck('median_price')->map(fn (mixed $value): float => round((float) $value, 2))->all(),
                'salesVolumes' => $rows->pluck('sales_volume')->map(fn (mixed $value): int => (int) round((float) $value))->all(),
                'salesValues' => $rows->pluck('sales_value')->map(fn (mixed $value): float => round((float) $value, 2))->all(),
            ];
        });
    }

    private function yearExpression(string $column): string
    {
        $wrappedColumn = DB::connection()->getQueryGrammar()->wrap($column);
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'pgsql' => "CAST(NULLIF(substring({$wrappedColumn} from '([0-9]{4})'), '') AS integer)",
            'sqlite' => "CASE WHEN substr(trim({$wrappedColumn}), -4) GLOB '[0-9][0-9][0-9][0-9]' THEN CAST(substr(trim({$wrappedColumn}), -4) AS integer) ELSE NULL END",
            default => "CASE WHEN RIGHT(TRIM({$wrappedColumn}), 4) REGEXP '^[0-9]{4}$' THEN CAST(RIGHT(TRIM({$wrappedColumn}), 4) AS UNSIGNED) ELSE NULL END",
        };
    }
}
