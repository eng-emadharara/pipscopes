<?php

namespace App\Models;

use App\Support\AccountHandle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An account known (confirmed) or reported (pending) to impersonate a provider. */
class Impersonator extends Model
{
    public const STATUSES = [
        'pending' => 'بانتظار المراجعة',
        'confirmed' => 'منتحل مؤكَّد (ظاهر للعامة)',
        'rejected' => 'مرفوض',
    ];

    protected $fillable = ['provider_id', 'platform', 'handle', 'account_key', 'public_note', 'status', 'reports_count'];

    protected static function booted(): void
    {
        // Admins may type a link or "@name"; keep the key and display form consistent
        static::saving(function (Impersonator $impersonator) {
            if ($impersonator->isDirty(['handle', 'platform']) && ($parsed = AccountHandle::parse($impersonator->handle, $impersonator->platform))) {
                $impersonator->platform = $parsed['platform'];
                $impersonator->handle = $parsed['display'];
                $impersonator->account_key = $parsed['key'];
            }
        });
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ImpersonatorReport::class);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Confirmed impersonators whose account is exactly what the visitor searched for
     * (a link, "@name" or a number), on any platform. Partial words never match, so a
     * search for "gold" doesn't flag "@gold_signals_fake".
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Impersonator>
     */
    public static function matchingSearch(string $term)
    {
        $term = trim($term);
        if (mb_strlen(ltrim($term, '@')) < 3 || preg_match('/\s/', $term)) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        $parsed = AccountHandle::parse($term);
        $name = $parsed ? \Illuminate\Support\Str::after($parsed['key'], ':') : null;
        if (! $name) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return static::confirmed()
            ->with('provider')
            ->whereHas('provider', fn ($q) => $q->where('is_active', true))
            ->where(fn ($q) => $q->where('account_key', $parsed['key'])
                ->orWhereRaw('account_key LIKE ? '.\App\Support\SearchText::LIKE_ESCAPE, ['%:'.\App\Support\SearchText::escapeLike($name)]))
            ->limit(3)
            ->get();
    }

    public function refreshReportsCount(): void
    {
        $this->forceFill(['reports_count' => $this->reports()->count()])->saveQuietly();
    }

    public function platformLabel(): string
    {
        return __("trust.account_platforms.{$this->platform}");
    }

    public function icon(): string
    {
        return AccountHandle::ICONS[$this->platform] ?? 'alert';
    }
}
