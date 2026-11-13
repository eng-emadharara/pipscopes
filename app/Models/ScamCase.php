<?php

namespace App\Models;

use App\Support\AdminAlerts;
use App\Support\ChannelWatching;
use App\Support\ScamHelp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Every report about one account, wallet or broker, gathered so they can be acted on together
 * (App\Support\ScamHelp::target picks the case). Public only once the team publishes it.
 */
class ScamCase extends Model
{
    protected $fillable = ['target_type', 'target_key', 'target_display', 'provider_id', 'is_public', 'public_summary'];

    protected $casts = [
        'is_public' => 'boolean',
        'total_usd' => 'decimal:2',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (ScamCase $case) {
            if ($case->isDirty('is_public') && $case->is_public && ! $case->published_at) {
                $case->published_at = now();
            }
        });

        // Published: the channel's followers are warned once
        static::saved(function (ScamCase $case) {
            if ($case->is_public && $case->wasChanged('is_public') && $case->provider) {
                ChannelWatching::alert($case->provider, '🚨 '.__('نشرنا ملف بلاغات نصب موثّقة عن قناة «:name» (:count). اطّلع عليه قبل أن تتعامل معها.', [
                    'name' => $case->provider->name, 'count' => $case->reports_count,
                ])."\n".route('scam-cases.show', $case));
            }
        });
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ScamReport::class);
    }

    public function confirmedReports(): HasMany
    {
        return $this->reports()->where('status', ScamReport::STATUS_CONFIRMED);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ScamCaseAction::class)->orderByDesc('done_at')->orderByDesc('id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_public', true)->where('reports_count', '>', 0);
    }

    /** The case for this target, created on first use */
    public static function forTarget(array $target): self
    {
        return static::firstOrCreate(['target_key' => $target['key']], [
            'target_type' => $target['type'],
            'target_display' => $target['display'],
            'provider_id' => static::providerFor($target),
        ]);
    }

    /** The listed channel the target is one of the official accounts of */
    protected static function providerFor(array $target): ?int
    {
        if ($target['type'] !== 'telegram') {
            return null;
        }

        return \App\Support\SubmissionLinks::existingProvider('https://t.me/'.\Illuminate\Support\Str::after($target['key'], ':'))?->id;
    }

    /** Counts and totals of confirmed reports; tells the team when it reaches the point of acting */
    public function recount(): void
    {
        $before = $this->reports_count;
        $confirmed = $this->confirmedReports();

        $this->forceFill([
            'reports_count' => $confirmed->count(),
            'total_usd' => (float) $confirmed->sum('amount_usd'),
        ])->saveQuietly();

        if ($before < ScamHelp::ACT_AT && $this->reports_count >= ScamHelp::ACT_AT) {
            AdminAlerts::scamCaseReady($this);
        }
    }

    public function targetLabel(): string
    {
        return match ($this->target_type) {
            'wallet' => __('محفظة'),
            'broker' => __('وسيط'),
            default => __("trust.account_platforms.{$this->target_type}"),
        };
    }

    /** "@goldpro" for Telegram, the address for a wallet */
    public function displayName(): string
    {
        return $this->provider?->name ?? $this->target_display;
    }

    /** Scam types across confirmed reports, most common first: [type => count] */
    public function typeCounts(): array
    {
        return $this->confirmedReports()->toBase()->selectRaw('scam_type, count(*) as n')->groupBy('scam_type')
            ->orderByDesc('n')->pluck('n', 'scam_type')->all();
    }
}
