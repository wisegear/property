<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EpcDashboardData
{
    public function forNation(string $nation): array
    {
        $config = $this->config($nation);
        $today = Carbon::today();
        $ttl = now()->addDays(45);
        $key = fn (string $name): string => "epc:{$nation}:{$name}";
        $ratings = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

        $stats = Cache::remember($key('stats'), $ttl, function () use ($config, $today): array {
            $latest = DB::table($config['table'])->selectRaw("MAX({$config['dateExpr']}) as date")->value('date');

            return [
                'total' => (int) DB::table($config['table'])->count(),
                'latest_lodgement' => $latest,
                'last30_count' => $latest ? (int) DB::table($config['table'])
                    ->whereBetween(DB::raw($config['dateExpr']), [Carbon::parse($latest)->subDays(30), $latest])->count() : 0,
                'last365_count' => (int) DB::table($config['table'])
                    ->whereBetween(DB::raw($config['dateExpr']), [$today->copy()->subDays(365), $today])->count(),
            ];
        });

        $byYear = Cache::remember($key('byYear'), $ttl, fn () => $this->yearCounts($config));
        $ratingByYear = Cache::remember($key('ratingByYear'), $ttl, fn () => $this->ratingsByYear($config, $config['currentCol'], $ratings));
        $potentialByYear = Cache::remember($key('potentialByYear'), $ttl, fn () => $this->ratingsByYear($config, $config['potentialCol'], $ratings));
        $tenureByYear = Cache::remember($key('tenureByYear'), $ttl, fn () => $this->tenureByYear($config));
        $ratingDist = Cache::remember($key('ratingDist'), $ttl, fn () => $this->ratingDistribution($config));

        return compact('stats', 'byYear', 'ratingByYear', 'potentialByYear', 'tenureByYear', 'ratingDist');
    }

    private function yearCounts(array $config): mixed
    {
        return DB::table($config['table'])
            ->selectRaw("{$config['yearExpr']} as yr, COUNT(*) as cnt")
            ->whereRaw("{$config['dateExpr']} IS NOT NULL")
            ->whereRaw("{$config['dateExpr']} >= ?", [$config['since']])
            ->groupBy('yr')->orderBy('yr')->get();
    }

    private function ratingsByYear(array $config, string $ratingColumn, array $ratings): mixed
    {
        $column = $this->wrap($ratingColumn);

        return DB::table($config['table'])
            ->selectRaw("{$config['yearExpr']} as yr, {$column} as rating, COUNT(*) as cnt")
            ->whereRaw("{$config['dateExpr']} IS NOT NULL")
            ->whereRaw("{$config['dateExpr']} >= ?", [$config['since']])
            ->whereIn($ratingColumn, $ratings)
            ->groupBy('yr', 'rating')->orderBy('yr')->orderByRaw($this->ratingOrder($column))->get();
    }

    private function tenureByYear(array $config): mixed
    {
        $column = $this->wrap($config['tenureCol']);
        $case = "CASE
            WHEN {$column} IN ('Owner-occupied','owner-occupied','Owner occupied','owner occupied','Owner Occupied','Owner-Occupied') THEN 'Owner-occupied'
            WHEN {$column} IN ('Rented (private)','rented (private)','Rental (private)','rental (private)','Private rented','private rented','Private Rented','Rented - private','rented - private','Rental - private','rental - private') THEN 'Rented (private)'
            WHEN {$column} IN ('Rented (social)','rented (social)','Rental (social)','rental (social)','Social rented','social rented','Social Rented','Rented - social','rented - social','Rental - social','rental - social') THEN 'Rented (social)'
            ELSE NULL END";

        return DB::table($config['table'])
            ->selectRaw("{$config['yearExpr']} as yr, {$case} as tenure, COUNT(*) as cnt")
            ->whereRaw("{$config['dateExpr']} IS NOT NULL")
            ->whereRaw("{$config['dateExpr']} >= ?", [$config['since']])
            ->whereNotNull($config['tenureCol'])
            ->groupBy('yr', 'tenure')->orderBy('yr')
            ->orderByRaw("CASE ({$case}) WHEN 'Owner-occupied' THEN 1 WHEN 'Rented (private)' THEN 2 WHEN 'Rented (social)' THEN 3 ELSE 99 END")
            ->get();
    }

    private function ratingDistribution(array $config): mixed
    {
        $column = $this->wrap($config['currentCol']);
        $case = "CASE WHEN {$column} IN ('A','B','C','D','E','F','G') THEN {$column} WHEN {$column} IS NULL THEN 'Unknown' ELSE 'Other' END";

        return DB::table($config['table'])
            ->selectRaw("{$case} as rating, COUNT(*) as cnt")
            ->groupBy('rating')->orderByRaw($this->ratingOrder("({$case})", true))->get();
    }

    private function config(string $nation): array
    {
        $table = $nation === 'scotland' ? 'epc_certificates_scotland' : 'epc_certificates';
        $dateColumn = $this->resolveColumn($table, ['LODGEMENT_DATE', 'lodgement_date']);
        $date = $this->wrap($dateColumn);
        $postgres = DB::connection()->getDriverName() === 'pgsql';

        return [
            'table' => $table,
            'dateExpr' => $postgres ? "CAST({$date} AS date)" : "date({$date})",
            'yearExpr' => $postgres ? "EXTRACT(YEAR FROM CAST({$date} AS date))::int" : "CAST(strftime('%Y', {$date}) AS INTEGER)",
            'currentCol' => $this->resolveColumn($table, ['CURRENT_ENERGY_RATING', 'current_energy_rating']),
            'potentialCol' => $this->resolveColumn($table, ['POTENTIAL_ENERGY_RATING', 'potential_energy_rating']),
            'tenureCol' => $this->resolveColumn($table, ['TENURE', 'tenure']),
            'since' => Carbon::create($nation === 'scotland' ? 2015 : 2008, 1, 1),
        ];
    }

    private function resolveColumn(string $table, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (Schema::hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }

    private function wrap(string $column): string
    {
        return DB::connection()->getQueryGrammar()->wrap($column);
    }

    private function ratingOrder(string $column, bool $tail = false): string
    {
        $case = "CASE {$column} WHEN 'A' THEN 1 WHEN 'B' THEN 2 WHEN 'C' THEN 3 WHEN 'D' THEN 4 WHEN 'E' THEN 5 WHEN 'F' THEN 6 WHEN 'G' THEN 7";

        return $case.($tail ? " WHEN 'Other' THEN 8 WHEN 'Unknown' THEN 9" : '').' ELSE 99 END';
    }
}
