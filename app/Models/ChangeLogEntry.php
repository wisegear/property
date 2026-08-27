<?php

namespace App\Models;

use Database\Factories\ChangeLogEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeLogEntry extends Model
{
    /** @use HasFactory<ChangeLogEntryFactory> */
    use HasFactory;

    protected $fillable = ['category', 'title', 'body', 'published_at', 'author_id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
