<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Support\AccountHandle;
use App\Support\SearchText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Provider extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public const TRANSLATABLE = ['service_type', 'bio'];

    protected $fillable = [
        'name', 'slug', 'service_type', 'pricing', 'bio', 'avatar',
        'telegram_url', 'telegram_members', 'telegram_data', 'telegram_checked_at', 'telegram_status', 'telegram_failures', 'website_url', 'twitter_url', 'instagram_url',
        'youtube_url', 'tiktok_url', 'facebook_url', 'whatsapp', 'extra_links',
        'is_verified', 'is_featured', 'is_active',
    ];

    protected $casts = [
        'extra_links' => 'array',
        'telegram_data' => 'array',
        'telegram_checked_at' => 'datetime',
        'is_verified' => 'boolean',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'rating_avg' => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(function (Provider $provider) {
            if (blank($provider->slug)) {
                $provider->slug = static::uniqueSlug($provider->name);
            }

            // Categories are synced after the save, so callers that change them run refreshSearchIndex()
            $provider->search_index = $provider->buildSearchIndex();
        });
    }

    /** Fields included in search, besides translations and category names */
    protected const SEARCHABLE = [
        'name', 'slug', 'service_type', 'bio',
        'telegram_url', 'website_url', 'twitter_url', 'instagram_url', 'youtube_url', 'tiktok_url', 'facebook_url',
    ];

    public function buildSearchIndex(): string
    {
        $parts = collect(static::SEARCHABLE)->map(fn ($field) => $this->getAttribute($field));

        $parts->push($this->telegramHandle());
        $parts = $parts->merge(collect($this->translations ?? [])->flatten());

        if ($this->exists) {
            foreach ([...$this->categories, ...$this->markets] as $term) {
                $parts->push($term->name);
                $parts = $parts->merge(collect($term->translations ?? [])->pluck('name'));
            }
        }

        return SearchText::normalize($parts->filter()->join(' '));
    }

    public function refreshSearchIndex(): void
    {
        $this->unsetRelation('categories')->unsetRelation('markets');
        $this->forceFill(['search_index' => $this->buildSearchIndex()])->saveQuietly();
    }

    /**
     * Every query word must appear somewhere in the provider's searchable text.
     * With $rankByName, results whose name starts with / contains the query come first.
     */
    public function scopeSearch(Builder $query, string $term, bool $rankByName = true): Builder
    {
        $words = SearchText::words($term);
        if (! $words) {
            return $query;
        }

        foreach ($words as $word) {
            $query->whereRaw("search_index LIKE ? ESCAPE '\\'", [SearchText::like($word)]);
        }

        if (! $rankByName) {
            return $query;
        }

        $phrase = implode(' ', $words);

        return $query->orderByRaw(
            "CASE WHEN lower(name) LIKE ? ESCAPE '\\' THEN 0 WHEN lower(name) LIKE ? ESCAPE '\\' THEN 1 ELSE 2 END",
            [addcslashes($phrase, '%_\\').'%', SearchText::like($phrase)]
        );
    }

    public static function uniqueSlug(string $name): string
    {
        // Str::slug drops Arabic letters, so fall back to a random suffix
        $base = Str::slug($name) ?: 'provider';
        $slug = $base;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Ordered by the categories' own sort order, so the first one acts as the main category. */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->orderBy('categories.sort_order');
    }

    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class)->orderBy('markets.sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function owners(): HasMany
    {
        return $this->hasMany(ProviderOwner::class);
    }

    public function changeLog(): HasMany
    {
        return $this->hasMany(ProviderChange::class);
    }

    public function impersonators(): HasMany
    {
        return $this->hasMany(Impersonator::class);
    }

    /**
     * Every official account in normalized form. Reports naming one of these are refused,
     * since a visitor reporting the real channel as fake is a mistake (or mischief).
     *
     * @return list<string>
     */
    public function officialAccountKeys(): array
    {
        $values = [
            $this->telegram_url, $this->website_url, $this->twitter_url, $this->instagram_url,
            $this->youtube_url, $this->tiktok_url, $this->facebook_url,
            ...collect($this->extra_links ?? [])->pluck('url')->all(),
        ];

        $keys = collect($values)->map(fn ($v) => AccountHandle::parse($v)['key'] ?? null);

        if ($this->whatsapp) {
            $keys->push(AccountHandle::parse($this->whatsapp, 'whatsapp')['key'] ?? null);
        }

        return $keys->filter()->unique()->values()->all();
    }

    public function telegramSnapshots(): HasMany
    {
        return $this->hasMany(TelegramSnapshot::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('status', Review::STATUS_APPROVED);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function recalculateRating(): void
    {
        $stats = $this->approvedReviews()
            ->selectRaw('COUNT(*) as total, AVG(rating) as avg')
            ->first();

        $this->forceFill([
            'reviews_count' => (int) $stats->total,
            'rating_avg' => round((float) $stats->avg, 2),
        ])->saveQuietly();
    }

    public function avatarUrl(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        return Str::startsWith($this->avatar, ['http://', 'https://'])
            ? $this->avatar
            : Storage::disk('public')->url($this->avatar);
    }

    public function initials(): string
    {
        return Str::upper(mb_substr(trim($this->name), 0, 1));
    }

    /** @return array<string, array{label:string, icon:string, url:string}> */
    public function socialLinks(): array
    {
        $links = [];
        foreach (config('trust.socials') as $field => $icon) {
            if ($this->{$field}) {
                $links[$field] = ['label' => __("trust.socials.$field"), 'icon' => $icon, 'url' => $this->{$field}];
            }
        }

        return $links;
    }

    /**
     * Standard social links plus the admin-entered extra official accounts (support, bot, backup channel…).
     *
     * @return list<array{label:string, icon:string, url:string}>
     */
    public function officialLinks(): array
    {
        $extra = collect($this->extra_links ?? [])
            ->filter(fn ($link) => filled($link['url'] ?? null))
            ->map(function (array $link) {
                $platform = AccountHandle::parse($link['url'])['platform'] ?? 'website';

                return [
                    'label' => filled($link['label'] ?? null) ? $link['label'] : __("trust.account_platforms.$platform"),
                    'icon' => AccountHandle::ICONS[$platform] ?? 'globe',
                    'url' => $link['url'],
                ];
            });

        return [...array_values($this->socialLinks()), ...$extra->values()->all()];
    }

    /** "@channel" from a t.me / telegram.me link, so visitors can tell which channel this is. */
    public function telegramHandle(): ?string
    {
        if (! $this->telegram_url) {
            return null;
        }

        $path = trim((string) parse_url($this->telegram_url, PHP_URL_PATH), '/');
        $handle = Str::before($path, '/');

        // Private invite links (t.me/+abc or t.me/joinchat/abc) have no public username
        if ($handle === '' || Str::startsWith($handle, ['+', 'joinchat'])) {
            return null;
        }

        return '@'.$handle;
    }

    /** Link used by the share buttons; keeps the current language so the preview image matches. */
    public function shareUrl(): string
    {
        return route('providers.show', [$this, 'lang' => app()->getLocale()]);
    }

    public function shareText(): string
    {
        return $this->reviews_count
            ? __(':name على :app: ★ :rating من 5 (:count)', [
                'name' => $this->name,
                'app' => config('app.name'),
                'rating' => number_format((float) $this->rating_avg, 1),
                'count' => trans_choice(':count تقييم', $this->reviews_count),
            ])
            : __(':name على :app: شارك تجربتك وساعد غيرك', ['name' => $this->name, 'app' => config('app.name')]);
    }

    public function pricingLabel(): ?string
    {
        return $this->pricing ? __('trust.pricing_options.'.$this->pricing) : null;
    }

    public static function ratingLabel(float $rating): string
    {
        return __('trust.rating_labels.'.static::ratingColor($rating));
    }

    public static function ratingColor(float $rating): string
    {
        return match (true) {
            $rating >= 4.5 => 'excellent',
            $rating >= 3.8 => 'great',
            $rating >= 3 => 'average',
            $rating >= 2 => 'poor',
            $rating > 0 => 'bad',
            default => 'none',
        };
    }
}
