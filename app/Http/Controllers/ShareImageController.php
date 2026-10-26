<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\Review;
use App\Services\ShareImage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the preview images used in og:image / twitter:image. The language is part of the
 * URL (social networks fetch without cookies), and images are cached by ShareImage.
 */
class ShareImageController extends Controller
{
    public function provider(string $locale, Provider $provider): BinaryFileResponse
    {
        abort_unless($provider->is_active, 404);

        return $this->png($locale, fn (ShareImage $images) => $images->provider($provider));
    }

    public function review(string $locale, Review $review): BinaryFileResponse
    {
        abort_unless($review->status === Review::STATUS_APPROVED && $review->provider?->is_active, 404);

        return $this->png($locale, fn (ShareImage $images) => $images->review($review));
    }

    public function site(string $locale): BinaryFileResponse
    {
        return $this->png($locale, fn (ShareImage $images) => $images->site());
    }

    protected function png(string $locale, callable $make): BinaryFileResponse
    {
        app()->setLocale($locale);

        return response()->file($make(new ShareImage), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
