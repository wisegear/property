<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertySchool extends Model
{
    protected $table = 'property_schools';

    protected $casts = [
        'urn' => 'integer',
        'school_open_date' => 'date',
        'inspection_start_date' => 'date',
        'publication_date' => 'date',
        'inspection_start_date_of_latest_oeif_graded_inspection' => 'date',
        'publication_date_of_latest_oeif_graded_inspection' => 'date',
        'date_of_latest_ungraded_inspection' => 'date',
        'ungraded_inspection_publication_date' => 'date',
        'total_number_of_pupils' => 'integer',
        'statutory_lowest_age' => 'integer',
        'statutory_highest_age' => 'integer',
        'inspection_number_of_latest_full_inspection' => 'integer',
        'inspection_number_of_latest_oeif_graded_inspection' => 'integer',
        'latest_ungraded_inspection_number' => 'integer',
    ];

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(PropertySchoolEstablishment::class, 'urn', 'urn');
    }
}
