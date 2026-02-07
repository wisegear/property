<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EpcMatcher
{
    private ?bool $pgTrgmAvailable = null;

    public function findForProperty(string $postcode, string $paon, ?string $saon, string $street, ?Carbon $refDate = null, int $limit = 5, ?string $locality = null): array
    {
        $postcode = $this->normalisePostcode($postcode);
        $paon = strtoupper(trim($paon));
        $saon = $saon !== null ? strtoupper(trim($saon)) : null;
        $street = strtoupper(trim($street));
        $locality = $locality !== null ? strtoupper(trim($locality)) : null;

        if ($this->supportsPostgresTrigramRanking()) {
            return $this->findForPropertyUsingPostgres($postcode, $paon, $saon, $street, $limit, $locality);
        }

        return $this->findForPropertyUsingPhpScoring($postcode, $paon, $saon, $street, $refDate, $limit, $locality);
    }

    protected function findForPropertyUsingPhpScoring(
        string $postcode,
        string $paon,
        ?string $saon,
        string $street,
        ?Carbon $refDate,
        int $limit,
        ?string $locality
    ): array {
        $epcTable = 'epc_certificates';
        $epcLmkColumn = $this->resolveColumn($epcTable, ['LMK_KEY', 'lmk_key']);
        $epcAddressColumn = $this->resolveColumn($epcTable, ['ADDRESS', 'address']);
        $epcPostcodeColumn = $this->resolveColumn($epcTable, ['POSTCODE', 'postcode']);
        $epcLodgementColumn = $this->resolveColumn($epcTable, ['LODGEMENT_DATE', 'lodgement_date']);
        $epcCurrentRatingColumn = $this->resolveColumn($epcTable, ['CURRENT_ENERGY_RATING', 'current_energy_rating']);
        $epcPotentialRatingColumn = $this->resolveColumn($epcTable, ['POTENTIAL_ENERGY_RATING', 'potential_energy_rating']);
        $epcPropertyTypeColumn = $this->resolveColumn($epcTable, ['PROPERTY_TYPE', 'property_type']);
        $epcFloorAreaColumn = $this->resolveColumn($epcTable, ['TOTAL_FLOOR_AREA', 'total_floor_area']);
        $epcLocalAuthorityColumn = $this->resolveColumn($epcTable, ['LOCAL_AUTHORITY_LABEL', 'local_authority_label']);

        $candidates = DB::table($epcTable)
            ->selectRaw($this->wrapColumn($epcLmkColumn).' as lmk_key')
            ->selectRaw($this->wrapColumn($epcAddressColumn).' as address')
            ->selectRaw($this->wrapColumn($epcPostcodeColumn).' as postcode')
            ->selectRaw($this->wrapColumn($epcLodgementColumn).' as lodgement_date')
            ->selectRaw($this->wrapColumn($epcCurrentRatingColumn).' as current_energy_rating')
            ->selectRaw($this->wrapColumn($epcPotentialRatingColumn).' as potential_energy_rating')
            ->selectRaw($this->wrapColumn($epcPropertyTypeColumn).' as property_type')
            ->selectRaw($this->wrapColumn($epcFloorAreaColumn).' as total_floor_area')
            ->selectRaw($this->wrapColumn($epcLocalAuthorityColumn).' as local_authority_label')
            ->where($epcPostcodeColumn, $postcode)
            ->orderByDesc($epcLodgementColumn)
            ->limit(500)
            ->get();

        $scored = [];
        foreach ($candidates as $row) {
            $scored[] = [
                'row' => $row,
                'score' => $this->scoreCandidate($paon, $saon, $street, $locality, (string) ($row->address ?? ''), $refDate, $row->lodgement_date),
            ];
        }

        usort($scored, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return strcmp($b['row']->lodgement_date ?? '', $a['row']->lodgement_date ?? '');
            }

            return $b['score'] <=> $a['score'];
        });

        // keep top matches above 50
        $scored = array_values(array_filter($scored, fn ($s) => $s['score'] >= 50));

        return array_slice($scored, 0, $limit);
    }

    protected function findForPropertyUsingPostgres(
        string $postcode,
        string $paon,
        ?string $saon,
        string $street,
        int $limit,
        ?string $locality
    ): array {
        $epcTable = 'epc_certificates';
        $epcLmkColumn = $this->resolveColumn($epcTable, ['LMK_KEY', 'lmk_key']);
        $epcAddressColumn = $this->resolveColumn($epcTable, ['ADDRESS', 'address']);
        $epcPostcodeColumn = $this->resolveColumn($epcTable, ['POSTCODE', 'postcode']);
        $epcLodgementColumn = $this->resolveColumn($epcTable, ['LODGEMENT_DATE', 'lodgement_date']);
        $epcCurrentRatingColumn = $this->resolveColumn($epcTable, ['CURRENT_ENERGY_RATING', 'current_energy_rating']);
        $epcPotentialRatingColumn = $this->resolveColumn($epcTable, ['POTENTIAL_ENERGY_RATING', 'potential_energy_rating']);
        $epcPropertyTypeColumn = $this->resolveColumn($epcTable, ['PROPERTY_TYPE', 'property_type']);
        $epcFloorAreaColumn = $this->resolveColumn($epcTable, ['TOTAL_FLOOR_AREA', 'total_floor_area']);
        $epcLocalAuthorityColumn = $this->resolveColumn($epcTable, ['LOCAL_AUTHORITY_LABEL', 'local_authority_label']);

        $normalizedPaon = $this->normToken($paon);
        $normalizedSaon = $saon ? $this->normToken($saon) : '';
        $normalizedStreet = $this->normStreet($street);
        $normalizedLocality = $locality ? $this->normToken($locality) : '';
        $saonUnitId = $normalizedSaon !== '' ? ($this->extractUnitId($normalizedSaon) ?? '') : '';
        $normalizedTargetAddress = $this->normAddress(trim(implode(' ', array_filter([
            $paon,
            $saon,
            $street,
            $locality,
        ], fn ($value) => $value !== null && trim((string) $value) !== ''))));

        $addressColumn = $this->wrapColumn($epcAddressColumn);
        $normalizedAddressSql = $this->normalizedAddressSql($addressColumn);
        $normalizedStreetAddressSql = $this->normalizedStreetAddressSql($normalizedAddressSql);
        $epcUnitSql = $this->epcUnitSql($normalizedAddressSql);

        $baseQuery = DB::table($epcTable)
            ->selectRaw($this->wrapColumn($epcLmkColumn).' as lmk_key')
            ->selectRaw($this->wrapColumn($epcAddressColumn).' as address')
            ->selectRaw($this->wrapColumn($epcPostcodeColumn).' as postcode')
            ->selectRaw($this->wrapColumn($epcLodgementColumn).' as lodgement_date')
            ->selectRaw($this->wrapColumn($epcCurrentRatingColumn).' as current_energy_rating')
            ->selectRaw($this->wrapColumn($epcPotentialRatingColumn).' as potential_energy_rating')
            ->selectRaw($this->wrapColumn($epcPropertyTypeColumn).' as property_type')
            ->selectRaw($this->wrapColumn($epcFloorAreaColumn).' as total_floor_area')
            ->selectRaw($this->wrapColumn($epcLocalAuthorityColumn).' as local_authority_label')
            ->selectRaw("{$normalizedAddressSql} as norm_address")
            ->selectRaw("{$normalizedStreetAddressSql} as norm_street")
            ->selectRaw("{$epcUnitSql} as epc_unit")
            ->where($epcPostcodeColumn, $postcode)
            ->whereNotNull($epcAddressColumn);

        $flagsQuery = DB::query()
            ->fromSub($baseQuery, 'base')
            ->select('base.*')
            ->selectRaw(
                "CASE WHEN ? <> '' AND POSITION(' ' || ? || ' ' IN ' ' || norm_address || ' ') > 0 THEN 1 ELSE 0 END as paon_hit",
                [$normalizedPaon, $normalizedPaon]
            )
            ->selectRaw(
                "CASE WHEN ? <> '' AND ? <> '' AND epc_unit <> '' AND epc_unit = ? THEN 1 WHEN ? <> '' AND POSITION(' ' || ? || ' ' IN ' ' || norm_address || ' ') > 0 THEN 1 ELSE 0 END as saon_hit",
                [$normalizedSaon, $saonUnitId, $saonUnitId, $normalizedSaon, $normalizedSaon]
            )
            ->selectRaw(
                "CASE WHEN ? <> '' AND ? <> '' AND epc_unit <> '' AND epc_unit <> ? THEN 1 ELSE 0 END as saon_unit_mismatch",
                [$normalizedSaon, $saonUnitId, $saonUnitId]
            )
            ->selectRaw(
                "CASE WHEN ? <> '' AND POSITION(' ' || ? || ' ' IN ' ' || norm_street || ' ') > 0 THEN 1 ELSE 0 END as street_hit",
                [$normalizedStreet, $normalizedStreet]
            )
            ->selectRaw(
                "CASE WHEN ? <> '' AND POSITION(' ' || ? || ' ' IN ' ' || norm_address || ' ') > 0 THEN 1 ELSE 0 END as locality_hit",
                [$normalizedLocality, $normalizedLocality]
            )
            ->selectRaw(
                "CASE WHEN ? <> '' AND (norm_address = ? OR norm_address LIKE ? OR ? LIKE norm_address || ' %') THEN 1 ELSE 0 END as exact_address_hit",
                [
                    $normalizedTargetAddress,
                    $normalizedTargetAddress,
                    $normalizedTargetAddress.' %',
                    $normalizedTargetAddress,
                ]
            );

        $scoreSql = <<<'SQL'
LEAST(
    100,
    GREATEST(
        0,
        (
            CASE WHEN paon_hit = 1 THEN 50 WHEN ? <> '' AND similarity(?, norm_address) >= 0.85 THEN 30 ELSE 0 END
            + CASE WHEN saon_hit = 1 THEN 20 WHEN saon_unit_mismatch = 1 THEN -25 ELSE 0 END
            + CASE WHEN locality_hit = 1 THEN 18 WHEN ? <> '' AND similarity(?, norm_address) >= 0.85 THEN 12 WHEN ? <> '' AND similarity(?, norm_address) >= 0.75 THEN 6 ELSE 0 END
            + CASE WHEN street_hit = 1 THEN 25 WHEN ? <> '' AND similarity(?, norm_street) >= 0.90 THEN 20 WHEN ? <> '' AND similarity(?, norm_street) >= 0.80 THEN 15 WHEN ? <> '' AND similarity(?, norm_street) >= 0.70 THEN 8 ELSE 0 END
            + CASE WHEN paon_hit = 1 AND (saon_hit = 1 OR street_hit = 1) THEN 10 ELSE 0 END
            + CASE WHEN paon_hit = 1 AND locality_hit = 1 AND street_hit = 0 THEN 8 ELSE 0 END
            + CASE WHEN exact_address_hit = 1 THEN 15 ELSE 0 END
        )
    )
)
SQL;

        $scoreBindings = [
            $normalizedPaon, $normalizedPaon,
            $normalizedLocality, $normalizedLocality, $normalizedLocality, $normalizedLocality,
            $normalizedStreet, $normalizedStreet, $normalizedStreet, $normalizedStreet, $normalizedStreet, $normalizedStreet,
        ];

        $rankedQuery = DB::query()
            ->fromSub($flagsQuery, 'scored')
            ->select('scored.*')
            ->selectRaw("{$scoreSql} as score", $scoreBindings)
            ->orderByDesc('lodgement_date');

        $rows = DB::query()
            ->fromSub($rankedQuery, 'ranked')
            ->where('score', '>=', 50)
            ->orderByDesc('score')
            ->orderByDesc('lodgement_date')
            ->limit($limit)
            ->get();

        return $rows
            ->map(function ($row) {
                $record = clone $row;
                unset($record->norm_address, $record->norm_street, $record->epc_unit, $record->score);
                unset($record->paon_hit, $record->saon_hit, $record->saon_unit_mismatch, $record->street_hit, $record->locality_hit, $record->exact_address_hit);

                return [
                    'row' => $record,
                    'score' => (float) $row->score,
                ];
            })
            ->values()
            ->all();
    }

    protected function normalisePostcode(string $pc): string
    {
        $pc = strtoupper(preg_replace('/\\s+/', '', $pc));

        return strlen($pc) >= 5 ? substr($pc, 0, -3).' '.substr($pc, -3) : $pc;
    }

    /**
     * Score a single EPC candidate against LR address parts.
     * Heuristics: PAON token match, SAON presence, street similarity, and locality token.
     */
    protected function scoreCandidate(string $paon, ?string $saon, string $street, ?string $locality, string $epcAddress, ?Carbon $refDate, ?string $lodgementDate): float
    {
        $score = 0.0;

        $normEpc = $this->normAddress($epcAddress);
        $normPAON = $this->normToken($paon);
        $normSAON = $saon ? $this->normToken($saon) : null;
        $normStreet = $this->normStreet($street);
        $normLocality = $locality ? $this->normToken($locality) : null;
        $epcUnitId = $this->extractUnitId($normEpc);
        $saonUnitId = $normSAON ? $this->extractUnitId($normSAON) : null;

        // Flags for combo bonuses
        $paonHit = false;
        $saonHit = false;
        $streetHit = false;
        $localityHit = false;

        // 1) PAON (house number/name)
        if ($normPAON !== '') {
            if (preg_match('/(^|\s)'.preg_quote($normPAON, '/').'($|\s)/', $normEpc)) {
                $score += 50; // exact token present
                $paonHit = true;
            } elseif ($this->levRatio($normPAON, $normEpc) >= 0.85) {
                $score += 30; // near match
            }
        }

        // 2) SAON (flat/unit)
        if ($normSAON) {
            if ($saonUnitId && $epcUnitId) {
                if ($saonUnitId === $epcUnitId) {
                    $score += 20; // exact flat/unit match
                    $saonHit = true;
                } else {
                    $score -= 25; // penalise different flat/unit number
                }
            } elseif (preg_match('/(^|\s)'.preg_quote($normSAON, '/').'($|\s)/', $normEpc)) {
                $score += 20; // exact flat text present
                $saonHit = true;
            }
        }

        // 3) Locality match (e.g., village/hamlet) — helpful where there is no street
        if ($normLocality) {
            if (preg_match('/(^|\s)'.preg_quote($normLocality, '/').'($|\s)/', $normEpc)) {
                $score += 18; // exact token present
                $localityHit = true;
            } else {
                $simLoc = $this->similarity($normLocality, $normEpc);
                if ($simLoc >= 0.85) {
                    $score += 12;
                } elseif ($simLoc >= 0.75) {
                    $score += 6;
                }
            }
        }

        // 4) Street match — compare LR street against a street-only version of EPC address
        $normEpcStreet = preg_replace('/\b(FLAT|APARTMENT|APT|UNIT|STUDIO|ROOM|MAISONETTE)\b/', '', $normEpc);
        $normEpcStreet = preg_replace('/\b\d+[A-Z]?\b/', '', $normEpcStreet); // drop numbers like 194 or 16A
        $normEpcStreet = preg_replace('/\s+/', ' ', trim($normEpcStreet));

        if ($normStreet !== '' && preg_match('/(^|\s)'.preg_quote($normStreet, '/').'($|\s)/', $normEpcStreet)) {
            $score += 25; // exact street string present
            $streetHit = true;
        } else {
            $sim = $this->similarity($normStreet, $normEpcStreet);
            if ($sim >= 0.90) {
                $score += 20;
            } elseif ($sim >= 0.80) {
                $score += 15;
            } elseif ($sim >= 0.70) {
                $score += 8;
            }
        }

        // 5) Combo bonus: if PAON matches AND (SAON or street) matches, it's almost certainly correct
        if ($paonHit && ($saonHit || $streetHit)) {
            $score += 10;
        }

        // Additional combo: PAON + Locality is very strong where street is absent
        if ($paonHit && $localityHit && ! $streetHit) {
            $score += 8;
        }

        $targetAddress = trim(implode(' ', array_filter([
            $paon,
            $saon,
            $street,
            $locality,
        ], fn ($value) => $value !== null && trim((string) $value) !== '')));
        $normalizedTargetAddress = $this->normAddress($targetAddress);
        if ($this->isEquivalentAddress($normalizedTargetAddress, $normEpc)) {
            $score += 15;
        }

        if ($score < 0) {
            $score = 0;
        }

        return min(100, $score);
    }

    protected function normAddress(string $s): string
    {
        $s = strtoupper($s);
        $s = str_replace(
            [' ROAD', ' STREET', ' AVENUE', ' LANE', ' DRIVE', ' COURT', ' PLACE', ' SQUARE', ' CRESCENT'],
            [' RD', ' ST', ' AVE', ' LN', ' DR', ' CT', ' PL', ' SQ', ' CRES'],
            $s
        );
        $s = preg_replace('/[^A-Z0-9 ]+/', ' ', $s);
        $s = preg_replace('/\\s+/', ' ', $s);

        return trim($s);
    }

    protected function normToken(string $s): string
    {
        $s = strtoupper(trim($s));
        $s = preg_replace('/[^A-Z0-9]+/', ' ', $s);

        return trim($s);
    }

    protected function normStreet(string $s): string
    {
        $s = $this->normAddress($s);
        $s = preg_replace('/\\b(FLAT|APARTMENT|APT|UNIT|STUDIO|ROOM|MAISONETTE)\\b/', '', $s);
        $s = preg_replace('/\\s+/', ' ', $s);

        return trim($s);
    }

    protected function levRatio(string $a, string $b): float
    {
        $a = trim($a);
        $b = trim($b);
        if ($a === '' || $b === '') {
            return 0.0;
        }
        $len = max(strlen($a), strlen($b));
        if ($len === 0) {
            return 1.0;
        }
        $dist = levenshtein($a, $b);

        return 1.0 - ($dist / $len);
    }

    protected function similarity(string $a, string $b): float
    {
        $p = 0.0;
        similar_text($a, $b, $p);

        return $p / 100.0;
    }

    protected function extractUnitId(string $s): ?string
    {
        if ($s === '') {
            return null;
        }
        $s = strtoupper($s);
        if (preg_match('/\b(FLAT|APARTMENT|APT|UNIT|STUDIO|ROOM|MAISONETTE)\s+([A-Z0-9]+)\b/', $s, $m)) {
            return $m[2];
        }
        if (preg_match('/\b([0-9]+[A-Z]?)\b/', $s, $m)) {
            return $m[1];
        }

        return null;
    }

    private function supportsPostgresTrigramRanking(): bool
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return false;
        }

        if ($this->pgTrgmAvailable !== null) {
            return $this->pgTrgmAvailable;
        }

        try {
            $this->pgTrgmAvailable = DB::table('pg_extension')
                ->where('extname', 'pg_trgm')
                ->exists();
        } catch (\Throwable) {
            $this->pgTrgmAvailable = false;
        }

        return $this->pgTrgmAvailable;
    }

    private function normalizedAddressSql(string $wrappedAddressColumn): string
    {
        $sql = "UPPER(COALESCE({$wrappedAddressColumn}, ''))";
        $sql = "REGEXP_REPLACE({$sql}, '\\mROAD\\M', ' RD ', 'g')";
        $sql = "REGEXP_REPLACE({$sql}, '\\mSTREET\\M', ' ST ', 'g')";
        $sql = "REGEXP_REPLACE({$sql}, '\\mAVENUE\\M', ' AVE ', 'g')";
        $sql = "REGEXP_REPLACE({$sql}, '\\mLANE\\M', ' LN ', 'g')";
        $sql = "REGEXP_REPLACE({$sql}, '\\mDRIVE\\M', ' DR ', 'g')";
        $sql = "REGEXP_REPLACE({$sql}, '\\mCOURT\\M', ' CT ', 'g')";
        $sql = "REGEXP_REPLACE({$sql}, '\\mPLACE\\M', ' PL ', 'g')";
        $sql = "REGEXP_REPLACE({$sql}, '\\mSQUARE\\M', ' SQ ', 'g')";
        $sql = "REGEXP_REPLACE({$sql}, '\\mCRESCENT\\M', ' CRES ', 'g')";
        $sql = "REGEXP_REPLACE({$sql}, '[^A-Z0-9 ]+', ' ', 'g')";
        $sql = "REGEXP_REPLACE({$sql}, '\\s+', ' ', 'g')";

        return "TRIM({$sql})";
    }

    private function normalizedStreetAddressSql(string $normalizedAddressSql): string
    {
        $sql = "REGEXP_REPLACE({$normalizedAddressSql}, '\\m(FLAT|APARTMENT|APT|UNIT|STUDIO|ROOM|MAISONETTE)\\M', ' ', 'g')";
        $sql = "REGEXP_REPLACE({$sql}, '\\m\\d+[A-Z]\\M', ' ', 'g')";
        $sql = "REGEXP_REPLACE({$sql}, '\\m\\d+\\M', ' ', 'g')";
        $sql = "REGEXP_REPLACE({$sql}, '\\s+', ' ', 'g')";

        return "TRIM({$sql})";
    }

    private function epcUnitSql(string $normalizedAddressSql): string
    {
        return "COALESCE((regexp_match({$normalizedAddressSql}, '\\m(FLAT|APARTMENT|APT|UNIT|STUDIO|ROOM|MAISONETTE)\\s+([A-Z0-9]+)\\M'))[2], (regexp_match({$normalizedAddressSql}, '\\m([0-9]+[A-Z])\\M'))[1], (regexp_match({$normalizedAddressSql}, '\\m([0-9]+)\\M'))[1], '')";
    }

    private function isEquivalentAddress(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        if (str_starts_with($a, $b.' ') || str_starts_with($b, $a.' ')) {
            return true;
        }

        return false;
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

    private function wrapColumn(string $column): string
    {
        $grammar = DB::connection()->getQueryGrammar();

        return $grammar->wrap($column);
    }
}
