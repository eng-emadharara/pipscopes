<?php

namespace App\Services;

use App\Models\Provider;
use App\Models\Review;
use App\Support\ArabicShaper;
use App\Support\Site;
use GdImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds the 1200×630 PNG previews (Open Graph / Twitter cards) shown when a provider
 * page or a review is shared. Images are cached on disk; the cache key changes whenever
 * the content shown in the image changes.
 */
class ShareImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    /** Bump to regenerate every cached image after a design change */
    protected const DESIGN_VERSION = 3;

    protected const PADDING = 64;

    protected GdImage $img;

    /** @var array<string, int> */
    protected array $colors = [];

    protected bool $rtl;

    protected string $locale;

    public function __construct()
    {
        $this->locale = app()->getLocale();
        $this->rtl = config("trust.locales.{$this->locale}.dir") === 'rtl';
    }

    /** Path of the cached PNG for a provider, generating it if needed. */
    public function provider(Provider $provider): string
    {
        $key = implode('|', [$provider->id, $provider->updated_at?->timestamp, $provider->rating_avg, $provider->reviews_count, $provider->is_verified]);

        return $this->cached('provider', $key, fn () => $this->drawProvider($provider));
    }

    public function review(Review $review): string
    {
        $key = implode('|', [$review->id, $review->updated_at?->timestamp, $review->provider->updated_at?->timestamp]);

        return $this->cached('review', $key, fn () => $this->drawReview($review));
    }

    /** Generic card for pages without a specific image (home, content pages). */
    public function site(): string
    {
        return $this->cached('site', config('app.name'), fn () => $this->drawSite());
    }

    protected function cached(string $type, string $key, callable $draw): string
    {
        $name = $type.'-'.sha1(self::DESIGN_VERSION.'|'.Site::brandVersion().'|'.$this->locale.'|'.$key).'.png';
        $path = 'share-images/'.$name;
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            $this->canvas();
            $draw();
            File::ensureDirectoryExists(dirname($disk->path($path)));
            imagepng($this->img, $disk->path($path), 7);
            imagedestroy($this->img);
        }

        return $disk->path($path);
    }

    // ---- Layouts ------------------------------------------------------------------

    protected function drawProvider(Provider $provider): void
    {
        $this->header();

        // Avatar on the start side, text next to it
        $avatarSize = 168;
        $avatarX = $this->rtl ? self::WIDTH - self::PADDING - $avatarSize : self::PADDING;
        $this->avatar($provider, $avatarX, 150, $avatarSize);

        $textStart = self::PADDING + $avatarSize + 40;
        $textWidth = self::WIDTH - $textStart - self::PADDING;

        $y = 196;
        foreach ($this->wrap($provider->name, 'bold', 56, $textWidth, 2) as $line) {
            $this->text($line, 'bold', 56, $y, 'white', $textStart);
            $y += 72;
        }

        $this->text($this->fit($provider->tr('service_type') ?? '', 'regular', 30, $textWidth), 'regular', 30, $y + 2, 'muted', $textStart);

        if ($handle = $provider->telegramHandle()) {
            $this->text($handle, 'regular', 28, $y + 48, 'sky', $textStart);
        }

        // Rating row
        $rating = (float) $provider->rating_avg;
        $starsWidth = $this->stars($rating, 400, 64);
        $after = self::PADDING + $starsWidth + 28;
        $this->text($provider->reviews_count ? number_format($rating, 1) : '—', 'bold', 60, 462, 'white', $after);
        $label = Provider::ratingLabel($rating).'  ·  '.trans_choice(':count تقييم', $provider->reviews_count);
        $this->text($label, 'regular', 30, 456, 'muted', $after + 140);

        if ($provider->is_verified) {
            $this->pill(__('موثّق'), 52);
        }

        $this->footer();
    }

    protected function drawReview(Review $review): void
    {
        $this->header($review->provider->name);

        $this->stars((float) $review->rating, 150, 52);
        $this->text($review->displayName(), 'semibold', 30, 186, 'muted', self::PADDING + 5 * 58 + 24);

        $width = self::WIDTH - 2 * self::PADDING;
        $y = 290;
        foreach ($this->wrap($review->title, 'bold', 48, $width, 2) as $line) {
            $this->text($line, 'bold', 48, $y, 'white');
            $y += 64;
        }

        foreach ($this->wrap(Str::squish($review->body), 'regular', 30, $width, 3) as $line) {
            $this->text($line, 'regular', 30, $y + 6, 'muted');
            $y += 46;
        }

        $this->footer();
    }

    protected function drawSite(): void
    {
        $this->header();
        $width = self::WIDTH - 2 * self::PADDING;
        $this->stars(5, 150, 56);
        $y = 300;
        foreach ($this->wrap(__('اقرأ التقييمات. اختر بثقة.'), 'bold', 72, $width, 2) as $line) {
            $this->text($line, 'bold', 72, $y, 'white');
            $y += 92;
        }
        foreach ($this->wrap(__('منصة تقييمات مستقلة لقنوات التوصيات ومقدمي خدمات نسخ الصفقات والتداول.'), 'regular', 34, $width, 2) as $line) {
            $this->text($line, 'regular', 34, $y, 'muted');
            $y += 50;
        }
        $this->footer();
    }

    // ---- Building blocks ----------------------------------------------------------

    protected function canvas(): void
    {
        $this->img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagesavealpha($this->img, true);
        imageantialias($this->img, true);

        foreach ([
            'bg' => '#191919', 'panel' => '#232323', 'white' => '#ffffff', 'muted' => '#b8b8b8', 'faint' => '#6b6b6b',
            'brand' => '#00b67a', 'brandSoft' => '#0f3d2e', 'sky' => '#7dd3fc', 'empty' => '#3a3a3a',
            'excellent' => '#00b67a', 'great' => '#73cf11', 'average' => '#ffce00', 'poor' => '#ff8622', 'bad' => '#ff3722',
        ] as $name => $hex) {
            [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
            $this->colors[$name] = imagecolorallocate($this->img, $r, $g, $b);
        }

        imagefill($this->img, 0, 0, $this->colors['bg']);
        // Brand accent strip along the bottom edge
        imagefilledrectangle($this->img, 0, self::HEIGHT - 10, self::WIDTH, self::HEIGHT, $this->colors['brand']);
    }

    /** Logo + site name at the top, optional context (e.g. provider name) on the other side. */
    protected function header(?string $context = null): void
    {
        $logo = 52;
        $logoX = $this->rtl ? self::WIDTH - self::PADDING - $logo : self::PADDING;
        if (! $this->customLogo($logoX, 52, $logo)) {
            imagefilledrectangle($this->img, $logoX, 52, $logoX + $logo, 52 + $logo, $this->colors['brand']);
            $this->star($logoX + $logo / 2, 52 + $logo / 2, $logo * 0.36, $this->colors['white']);
        }
        $this->text(config('app.name'), 'bold', 30, 90, 'white', self::PADDING + $logo + 18);

        if ($context) {
            $context = $this->fit($context, 'semibold', 26, 520);
            $this->text($context, 'semibold', 26, 88, 'muted', null, true);
        }
    }

    /** Draws the logo uploaded in the site settings, fitted into the square; false when there is none. */
    protected function customLogo(int $x, int $y, int $size): bool
    {
        $path = Site::filePath('logo');
        $logo = $path ? @imagecreatefromstring((string) file_get_contents($path)) : false;
        if (! $logo) {
            return false;
        }

        [$w, $h] = [imagesx($logo), imagesy($logo)];
        $scale = $size / max($w, $h);
        [$dw, $dh] = [(int) round($w * $scale), (int) round($h * $scale)];
        imagealphablending($this->img, true);
        imagecopyresampled($this->img, $logo, $x + intdiv($size - $dw, 2), $y + intdiv($size - $dh, 2), 0, 0, $dw, $dh, $w, $h);
        imagedestroy($logo);

        return true;
    }

    protected function footer(): void
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: config('app.name');
        $this->text($host, 'semibold', 26, self::HEIGHT - 44, 'brand');
        $this->text(__('تقييمات حقيقية من متداولين'), 'regular', 24, self::HEIGHT - 44, 'faint', null, true);
    }

    /** Five rating squares at the start side; returns their total width. */
    protected function stars(float $rating, int $top, int $size): int
    {
        $gap = (int) round($size * 0.1);
        $color = $this->colors[Provider::ratingColor($rating)] ?? $this->colors['empty'];

        for ($i = 0; $i < 5; $i++) {
            $offset = self::PADDING + $i * ($size + $gap);
            // The first star sits on the start side (right in RTL)
            $x = $this->rtl ? self::WIDTH - $offset - $size : $offset;
            imagefilledrectangle($this->img, $x, $top, $x + $size, $top + $size, $this->colors['empty']);

            $fill = max(0, min(1, $rating - $i));
            if ($fill > 0) {
                $w = (int) round($size * $fill);
                $fx = $this->rtl ? $x + $size - $w : $x;
                imagefilledrectangle($this->img, $fx, $top, $fx + $w, $top + $size, $color);
            }

            $this->star($x + $size / 2, $top + $size / 2, $size * 0.36, $this->colors['white']);
        }

        return 5 * $size + 4 * $gap;
    }

    protected function star(float $cx, float $cy, float $radius, int $color): void
    {
        $points = [];
        for ($i = 0; $i < 10; $i++) {
            $r = $i % 2 ? $radius * 0.45 : $radius;
            $angle = deg2rad(-90 + $i * 36);
            $points[] = (int) round($cx + $r * cos($angle));
            $points[] = (int) round($cy + $r * sin($angle));
        }
        imagefilledpolygon($this->img, $points, $color);
    }

    protected function avatar(Provider $provider, int $x, int $y, int $size): void
    {
        $source = $this->loadAvatar($provider);

        if ($source) {
            $w = imagesx($source);
            $h = imagesy($source);
            $crop = min($w, $h);
            imagecopyresampled($this->img, $source, $x, $y, (int) (($w - $crop) / 2), (int) (($h - $crop) / 2), $size, $size, $crop, $crop);
            imagedestroy($source);

            return;
        }

        imagefilledrectangle($this->img, $x, $y, $x + $size, $y + $size, $this->colors['brandSoft']);
        $initial = ArabicShaper::forImage($provider->initials());
        $box = imagettfbbox(84, 0, $this->font('bold'), $initial);
        $tw = $box[2] - $box[0];
        imagettftext($this->img, 84, 0, (int) ($x + ($size - $tw) / 2 - $box[0]), $y + (int) ($size * 0.7), $this->colors['brand'], $this->font('bold'), $initial);
    }

    protected function loadAvatar(Provider $provider): ?GdImage
    {
        if (! $provider->avatar || Str::startsWith($provider->avatar, ['http://', 'https://'])) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($provider->avatar)) {
            return null;
        }

        $image = @imagecreatefromstring($disk->get($provider->avatar));

        return $image ?: null;
    }

    /** Green label with a drawn check mark, pinned to the end side of the header row. */
    protected function pill(string $label, int $top): void
    {
        $text = ArabicShaper::forImage($label);
        $box = imagettfbbox(24, 0, $this->font('semibold'), $text);
        $textWidth = $box[2] - $box[0];
        $height = 52;
        $width = $textWidth + 84;
        $x = $this->rtl ? self::PADDING : self::WIDTH - self::PADDING - $width;
        imagefilledrectangle($this->img, $x, $top, $x + $width, $top + $height, $this->colors['brand']);

        // Check mark on the start side of the label
        $checkX = $this->rtl ? $x + $width - 40 : $x + 18;
        imagesetthickness($this->img, 4);
        imageline($this->img, $checkX, $top + 27, $checkX + 7, $top + 35, $this->colors['white']);
        imageline($this->img, $checkX + 7, $top + 35, $checkX + 22, $top + 17, $this->colors['white']);
        imagesetthickness($this->img, 1);

        $textX = $this->rtl ? $x + 22 : $x + 54;
        imagettftext($this->img, 24, 0, $textX - $box[0], $top + 36, $this->colors['white'], $this->font('semibold'), $text);
    }

    /**
     * Draws one line aligned to the start side (or the end side with $alignEnd),
     * $startOffset being the distance from the start edge.
     */
    protected function text(string $text, string $weight, int $size, int $baseline, string $color, ?int $startOffset = null, bool $alignEnd = false): void
    {
        $drawn = ArabicShaper::forImage($text);
        $font = $this->font($weight);
        $box = imagettfbbox($size, 0, $font, $drawn);
        $width = $box[2] - $box[0];
        $offset = $startOffset ?? self::PADDING;

        // The start side is the right edge in RTL; $alignEnd pins the text to the opposite edge
        $x = match (true) {
            $alignEnd && $this->rtl => self::PADDING,
            $alignEnd => self::WIDTH - self::PADDING - $width,
            $this->rtl => self::WIDTH - $offset - $width,
            default => $offset,
        };

        imagettftext($this->img, $size, 0, (int) ($x - $box[0]), $baseline, $this->colors[$color], $font, $drawn);
    }

    /**
     * Splits text into at most $maxLines lines that fit $maxWidth; the last line gets "…" if cut.
     *
     * @return list<string>
     */
    protected function wrap(string $text, string $weight, int $size, int $maxWidth, int $maxLines): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $index => $word) {
            $candidate = $current === '' ? $word : "$current $word";
            if ($this->width($candidate, $weight, $size) <= $maxWidth || $current === '') {
                $current = $candidate;

                continue;
            }

            $lines[] = $current;
            $current = $word;

            if (count($lines) === $maxLines) {
                $current = '';
                $lines[$maxLines - 1] = $this->fit($lines[$maxLines - 1].' '.implode(' ', array_slice($words, $index)), $weight, $size, $maxWidth);
                break;
            }
        }

        if ($current !== '') {
            $lines[] = $this->fit($current, $weight, $size, $maxWidth);
        }

        return $lines;
    }

    /** Shortens text with "…" until it fits $maxWidth. */
    protected function fit(string $text, string $weight, int $size, int $maxWidth): string
    {
        if ($this->width($text, $weight, $size) <= $maxWidth) {
            return $text;
        }

        while (mb_strlen($text) > 1 && $this->width($text.'…', $weight, $size) > $maxWidth) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text).'…';
    }

    protected function width(string $text, string $weight, int $size): int
    {
        $box = imagettfbbox($size, 0, $this->font($weight), ArabicShaper::forImage($text));

        return $box[2] - $box[0];
    }

    protected function font(string $weight): string
    {
        $file = ['regular' => 'Regular', 'semibold' => 'SemiBold', 'bold' => 'Bold'][$weight];

        return resource_path("fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-$file.woff");
    }
}
