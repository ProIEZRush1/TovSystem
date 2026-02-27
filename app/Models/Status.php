<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Status extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'color',
        'description',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (Status $status) {
            if (empty($status->slug)) {
                $status->slug = Str::slug($status->name);
            }
        });
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }
}
