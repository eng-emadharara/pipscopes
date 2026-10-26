<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/** A traded market (Forex, Gold, Crypto…) that providers cover; used for filtering. */
class Market extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public const TRANSLATABLE = ['name'];

    protected $fillable = ['name', 'slug', 'icon', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Market $market) {
            if (blank($market->slug)) {
                $market->slug = (Str::slug($market->name) ?: 'market').'-'.Str::lower(Str::random(4));
            }
        });

        // Market names are part of each provider's search text
        static::saved(function (Market $market) {
            if ($market->wasChanged(['name', 'translations'])) {
                $market->providers()->get()->each->refreshSearchIndex();
            }
        });
    }

    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
