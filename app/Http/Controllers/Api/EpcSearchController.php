<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\EpcSearchRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EpcSearchController extends Controller
{
    public function __invoke(EpcSearchRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $scotland = $validated['nation'] === 'scotland';
        $table = $scotland ? 'epc_certificates_scotland' : 'epc_certificates';
        $reference = $this->column($table, $scotland
            ? ['REPORT_REFERENCE_NUMBER', 'report_reference_number']
            : ['LMK_KEY', 'lmk_key']);
        $postcodeColumn = $this->column($table, ['POSTCODE', 'postcode']);
        $lodgement = $this->column($table, ['LODGEMENT_DATE', 'lodgement_date']);

        $columns = [
            DB::raw($this->wrap($reference).' as reference'),
            DB::raw($this->wrap($postcodeColumn).' as postcode'),
            DB::raw($this->wrap($lodgement).' as lodgement_date'),
            DB::raw($this->wrap($this->column($table, ['CURRENT_ENERGY_RATING', 'current_energy_rating'])).' as current_energy_rating'),
            DB::raw($this->wrap($this->column($table, ['POTENTIAL_ENERGY_RATING', 'potential_energy_rating'])).' as potential_energy_rating'),
            DB::raw($this->wrap($this->column($table, ['PROPERTY_TYPE', 'property_type'])).' as property_type'),
            DB::raw($this->wrap($this->column($table, ['TOTAL_FLOOR_AREA', 'total_floor_area'])).' as total_floor_area'),
            DB::raw($this->wrap($this->column($table, ['LOCAL_AUTHORITY_LABEL', 'local_authority_label'])).' as local_authority'),
        ];

        if ($scotland) {
            foreach (['ADDRESS1', 'ADDRESS2', 'ADDRESS3'] as $addressColumn) {
                $columns[] = DB::raw($this->wrap($this->column($table, [$addressColumn, strtolower($addressColumn)])).' as '.strtolower($addressColumn));
            }
        } else {
            $columns[] = DB::raw($this->wrap($this->column($table, ['ADDRESS', 'address'])).' as address');
        }

        $paginator = DB::table($table)
            ->select($columns)
            ->where($postcodeColumn, $validated['postcode'])
            ->orderBy($lodgement, 'desc')
            ->paginate(50);

        $results = collect($paginator->items())->map(function ($row) use ($scotland): array {
            $address = $scotland
                ? implode(', ', array_filter([$row->address1, $row->address2, $row->address3]))
                : $row->address;

            return [
                'reference' => $row->reference,
                'address' => $address !== '' ? $address : null,
                'postcode' => $row->postcode,
                'lodgement_date' => $row->lodgement_date,
                'current_energy_rating' => $row->current_energy_rating,
                'potential_energy_rating' => $row->potential_energy_rating,
                'property_type' => $row->property_type,
                'total_floor_area_square_metres' => is_numeric($row->total_floor_area) ? (float) $row->total_floor_area : null,
                'local_authority' => $row->local_authority,
                'api_url' => $scotland
                    ? route('api.v1.epc.scotland.show', ['reference' => $row->reference])
                    : route('api.v1.epc.show', ['reference' => $row->reference]),
                'website_url' => $scotland
                    ? route('epc.scotland.show', ['rrn' => $row->reference])
                    : route('epc.show', ['lmk' => $row->reference]),
            ];
        });

        return response()->json(['data' => [
            'nation' => $validated['nation'],
            'nation_label' => $scotland ? 'Scotland' : 'England & Wales',
            'postcode' => $validated['postcode'],
            'results' => $results,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]]);
    }

    private function column(string $table, array $candidates): string
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
}
