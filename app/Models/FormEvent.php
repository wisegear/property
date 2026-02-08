<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'form_key',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
