<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comments extends Model
{
    use HasFactory;

    public function commentable()
    {
        return $this->morphTo();
    }
    
    public function users() {
        
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}