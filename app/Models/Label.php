<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Label extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'color',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (Label $label) {
            if (empty($label->slug)) {
                $label->slug = Str::slug($label->name);
            }
        });
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class);
    }
}
