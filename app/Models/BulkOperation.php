<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkOperation extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'description',
        'payload',
        'undone_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'undone_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
