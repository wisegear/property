<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PropertySchoolEstablishment extends Model
{
    protected $table = 'property_school_establishments';

    protected $casts = [
        'open_date' => 'date',
        'census_date' => 'date',
        'date_of_last_inspection_visit' => 'date',
        'statutory_low_age' => 'integer',
        'statutory_high_age' => 'integer',
        'number_of_pupils' => 'integer',
        'school_capacity' => 'integer',
        'percentage_fsm' => 'float',
        'easting' => 'integer',
        'northing' => 'integer',
    ];

    public function ofsted(): HasOne
    {
        return $this->hasOne(PropertySchool::class, 'urn', 'urn');
    }
}
