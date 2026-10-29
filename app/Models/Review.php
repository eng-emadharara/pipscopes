<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Review extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING => 'بانتظار المراجعة',
        self::STATUS_APPROVED => 'منشور',
        self::STATUS_REJECTED => 'مرفوض',
    ];

    protected $fillable = [
        'provider_id', 'user_id', 'rating', 'title', 'body', 'show_name',
        'rating_accuracy', 'rating_transparency', 'rating_support', 'rating_value',
        'pros', 'cons', 'experience_duration', 'status', 'admin_note', 'provider_reply',
        'provider_replied_at', 'provider_reply_owner_id', 'ip', 'hold_reason',
    ];

    protected $casts = [
        'provider_replied_at' => 'datetime',
        'show_name' => 'boolean',
        'pros' => 'array',
        'cons' => 'array',
    ];

    protected static function booted(): void
    {
        // Keep the reply date in step with the reply, whoever edits it (owner portal or admin)
        static::saving(function (Review $review) {
            if ($review->isDirty('provider_reply')) {
                $review->provider_replied_at = filled($review->provider_reply) ? now() : null;
                if (blank($review->provider_reply)) {
                    $review->provider_reply_owner_id = null;
                }
            }
        });

        static::updated(fn (Review $review) => $review->notifyMember());
        static::saved(fn (Review $review) => $review->refreshProviderStats());
        static::deleted(fn (Review $review) => $review->refreshProviderStats());
    }

    /** Tells the author when the review is published, rejected or taken down, and when the owner first replies. */
    protected function notifyMember(): void
    {
        if (! $this->user) {
            return;
        }

        if ($this->wasChanged('status')) {
            match ($this->status) {
                self::STATUS_APPROVED => $this->user->notify(new \App\Notifications\ReviewPublished($this)),
                self::STATUS_REJECTED => $this->user->notify(new \App\Notifications\ReviewNotPublished($this, removed: $this->getOriginal('status') === self::STATUS_APPROVED)),
                default => null,
            };
        }

        // Only the first reply: editing it later doesn't notify again
        if ($this->wasChanged('provider_reply') && filled($this->provider_reply) && blank($this->getOriginal('provider_reply'))) {
            $this->user->notify(new \App\Notifications\OwnerReplied($this));
        }
    }

    protected function refreshProviderStats(): void
    {
        $this->provider?->recalculateRating();

        // The review may have been moved to another provider
        if ($this->wasChanged('provider_id') && $this->getOriginal('provider_id')) {
            Provider::find($this->getOriginal('provider_id'))?->recalculateRating();
        }

        \App\Support\RedFlags::forget();
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ReviewVote::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ReviewReport::class);
    }

    /** Recounts helpful votes and open reports; saved quietly so the provider's rating isn't recalculated. */
    public function refreshCounters(): void
    {
        $this->forceFill([
            'helpful_count' => $this->votes()->count(),
            'open_reports_count' => $this->reports()->where('status', 'open')->count(),
        ])->saveQuietly();
    }

    /**
     * One key per voter, so each person can vote/report a review once:
     * the account for members, a hash of the IP address for guests.
     */
    public static function voterKey(Request $request): string
    {
        return match (true) {
            (bool) $request->user() => 'u:'.$request->user()->id,
            (bool) auth('owner')->id() => 'o:'.auth('owner')->id(),
            default => 'g:'.hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shareUrl(): string
    {
        return route('reviews.show', [$this, 'lang' => app()->getLocale()]);
    }

    /** A short line for page titles, share cards and search results: the start of the experience (old titles stay hidden) */
    public function headline(int $limit = 70): string
    {
        return Str::limit(Str::squish((string) $this->body), $limit);
    }

    public function shareText(): string
    {
        return __('تقييم :stars لـ :name: «:title»', [
            'stars' => str_repeat('★', (int) $this->rating),
            'name' => $this->provider?->name,
            'title' => $this->headline(),
        ]);
    }

    public function displayName(): string
    {
        return $this->show_name ? ($this->user?->name ?? __('مستخدم')) : __('مستخدم مجهول');
    }

    /** @return list<string> */
    public function prosLabels(): array
    {
        return collect($this->pros ?? [])->map(fn ($k) => __("trust.pros.$k"))->filter()->values()->all();
    }

    /** @return list<string> */
    public function consLabels(): array
    {
        return collect($this->cons ?? [])->map(fn ($k) => __("trust.cons.$k"))->filter()->values()->all();
    }
}
