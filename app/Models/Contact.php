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
    public function scopeFilterByLabel(Builder $query, int|array|null $labelId): Builder
    {
        if (is_null($labelId) || (is_array($labelId) && empty($labelId))) {
            return $query;
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

    /**
     * Accepts int (single status) or array of ints (multi).
     */
    public function scopeFilterByStatus(Builder $query, int|array|null $statusId): Builder
    {
        if (is_null($statusId) || (is_array($statusId) && empty($statusId))) {
            return $query;
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
