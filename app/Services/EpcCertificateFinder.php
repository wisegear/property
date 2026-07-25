<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EpcCertificateFinder
{
    public function findEnglandWales(string $reference): ?object
    {
        $lmkColumn = $this->resolveColumn(['LMK_KEY', 'lmk_key']);
        $buildingReferenceColumn = $this->resolveColumn(['BUILDING_REFERENCE_NUMBER', 'building_reference_number']);

        $certificate = DB::table('epc_certificates')
            ->where($lmkColumn, $reference)
            ->first();

        if ($certificate !== null || $buildingReferenceColumn === null) {
            return $certificate;
        }

        return DB::table('epc_certificates')
            ->where($buildingReferenceColumn, $reference)
            ->first();
    }

    public function findScotland(string $reference): ?object
    {
        $column = $this->resolveColumnForTable('epc_certificates_scotland', ['REPORT_REFERENCE_NUMBER', 'report_reference_number']);

        return DB::table('epc_certificates_scotland')->where($column, $reference)->first();
    }

    private function resolveColumn(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (Schema::hasColumn('epc_certificates', $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveColumnForTable(string $table, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (Schema::hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }
}
