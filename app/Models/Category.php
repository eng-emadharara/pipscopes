<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public const TRANSLATABLE = ['name', 'description'];

    protected $fillable = ['name', 'slug', 'icon', 'description', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Category $category) {
            if (blank($category->slug)) {
                $category->slug = Str::slug($category->name) ?: Str::random(8);
            }
        });

        // Category names are part of each provider's search text
        static::saved(function (Category $category) {
            if ($category->wasChanged(['name', 'translations'])) {
                $category->providers()->get()->each->refreshSearchIndex();
            }
        });
    }

    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
