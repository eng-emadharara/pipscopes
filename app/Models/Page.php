<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public const TRANSLATABLE = ['title', 'subtitle', 'content'];

    protected $fillable = [
        'slug', 'title', 'subtitle', 'content',
        'is_published', 'show_in_header', 'show_in_footer', 'sort_order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'show_in_header' => 'boolean',
        'show_in_footer' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Page $page) {
            if (blank($page->slug)) {
                $page->slug = Str::slug($page->title) ?: Str::lower(Str::random(8));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function url(): string
    {
        // The "contact" page supplies the intro text of the contact form
        return $this->slug === 'contact' ? route('contact') : route('pages.show', $this);
    }

    /**
     * Splits the (translated) content at each <h2> so the page can render every
     * section as its own card. Text before the first heading becomes the intro.
     *
     * @return array{intro: string, sections: list<array{heading: string, html: string}>}
     */
    public function sections(): array
    {
        $html = static::sanitize((string) $this->tr('content'));
        $parts = preg_split('/<h2[^>]*>(.*?)<\/h2>/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        $sections = [];
        for ($i = 1; $i < count($parts); $i += 2) {
            $sections[] = ['heading' => strip_tags($parts[$i]), 'html' => trim($parts[$i + 1] ?? '')];
        }

        return ['intro' => trim($parts[0] ?? ''), 'sections' => $sections];
    }

    /** Content comes from trusted admins; this is defence in depth against stray scripts and handlers. */
    public static function sanitize(string $html): string
    {
        $html = strip_tags($html, '<h2><h3><h4><p><br><strong><b><em><i><u><s><a><ul><ol><li><blockquote><hr>');
        $html = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);

        return preg_replace('/href\s*=\s*(["\'])\s*(javascript|data|vbscript):[^"\']*\1/i', 'href="#"', $html);
    }
}
