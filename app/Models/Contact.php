<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class);
    }

    /**
     * Accepts int (single label) or array of ints (multi - OR match).
     */
    /**
     * Accepts int, "none" (contacts without labels), or array (multi — OR match).
     */
    public function scopeFilterByLabel(Builder $query, int|string|array|null $labelId): Builder
    {
        if (is_null($labelId) || (is_array($labelId) && empty($labelId))) {
            return $query;
        }

        // "none" = contacts that have zero labels
        if ($labelId === 'none' || (is_array($labelId) && in_array('none', $labelId))) {
            $realIds = is_array($labelId) ? array_filter($labelId, fn ($v) => $v !== 'none') : [];
            if (empty($realIds)) {
                return $query->doesntHave('labels');
            }
            // OR: contacts that have any of realIds OR have no labels at all
            return $query->where(function ($q) use ($realIds) {
                $q->whereHas('labels', fn ($sub) => $sub->whereIn('labels.id', $realIds))
                  ->orDoesntHave('labels');
            });
        }

        if (is_array($labelId)) {
            return $query->whereHas('labels', fn (Builder $q) => $q->whereIn('labels.id', $labelId));
        }

        return $query->whereHas('labels', fn (Builder $q) => $q->where('labels.id', $labelId));
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

    public function scopeFilterByStatus(Builder $query, int|string|array|null $statusId): Builder
    {
        if (is_null($statusId) || (is_array($statusId) && empty($statusId))) {
            return $query;
        }

        if ($statusId === 'none' || (is_array($statusId) && in_array('none', $statusId))) {
            $realIds = is_array($statusId) ? array_filter($statusId, fn ($v) => $v !== 'none') : [];
            if (empty($realIds)) {
                return $query->whereNull('status_id');
            }
            return $query->where(function ($q) use ($realIds) {
                $q->whereIn('status_id', $realIds)
                  ->orWhereNull('status_id');
            });
        }

        if (is_array($statusId)) {
            return $query->whereIn('status_id', $statusId);
        }

        return $query->where('status_id', $statusId);
    }

    /**
     * Accepts string (single country) or array of strings (multi).
     */
    public function scopeFilterByCountry(Builder $query, string|array|null $country): Builder
    {
        if (empty($country)) {
            return $query;
        }

        if (is_array($country)) {
            return $query->whereIn('country', $country);
        }

        return $query->where('country', $country);
    }

    public function scopeFilterByDate(Builder $query, ?string $dateFrom, ?string $dateTo): Builder
    {
        if (!empty($dateFrom)) {
            $query->where('date', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $query->where('date', '<=', $dateTo);
        }

        return $query;
    }
}
