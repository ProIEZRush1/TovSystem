<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    protected $fillable = [
        'phone',
        'country',
        'status_id',
        'source',
        'name',
        'date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('phone', 'like', "%{$search}%")
              ->orWhere('name', 'like', "%{$search}%");
        });
    }

    public function scopeFilterByStatus(Builder $query, ?int $statusId): Builder
    {
        if (is_null($statusId)) {
            return $query;
        }

        return $query->where('status_id', $statusId);
    }

    public function scopeFilterByCountry(Builder $query, ?string $country): Builder
    {
        if (empty($country)) {
            return $query;
        }

        return $query->where('country', $country);
    }
}
