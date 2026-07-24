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

    private function resolveColumn(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (Schema::hasColumn('epc_certificates', $candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
