<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A channel in the homepage's "team picks" section (Admin → اختيارات الفريق). */
class HomePick extends Model
{
    use HasTranslations;

    public const TRANSLATABLE = ['note'];

    protected $fillable = ['provider_id', 'position', 'note', 'starts_at', 'ends_at'];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    protected static function booted(): void
    {
        // New picks go to the end of the list
        static::creating(function (HomePick $pick) {
            $pick->position = $pick->position ?: (int) static::max('position') + 1;
        });
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /** Picks to show today: within their dates, of active channels, in the admin's order. */
    public function scopeShowing(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->whereHas('provider', fn ($q) => $q->active())
            ->orderBy('position');
    }

    public function isShowing(): bool
    {
        $today = now()->startOfDay();

        return (! $this->starts_at || $this->starts_at->lte($today))
            && (! $this->ends_at || $this->ends_at->gte($today))
            && $this->provider?->is_active;
    }
}
