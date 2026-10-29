<?php

namespace App\Support;

use App\Models\Provider;
use App\Models\Review;
use Illuminate\Support\Str;

/**
 * schema.org data (JSON-LD) for search engines: lets Google show a channel's stars and review
 * count under its result. Only what the page itself shows is described, as Google requires.
 * Check a page with https://search.google.com/test/rich-results
 */
class StructuredData
{
    /** The site itself: name, logo and official accounts (home page). */
    public static function site(): array
    {
        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => Site::name(),
                'url' => route('home'),
                'inLanguage' => app()->getLocale(),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('providers.index').'?q={search_term_string}'],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
            array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => Site::name(),
                'url' => route('home'),
                'logo' => Site::fileUrl('logo'),
                'description' => Site::text('meta_description'),
                'email' => Site::get('contact_email'),
                'sameAs' => array_values(Site::socials()) ?: null,
            ]),
        ];
    }

    /** A channel with its rating and its most helpful reviews, plus the breadcrumb trail. */
    public static function provider(Provider $provider): array
    {
        $url = route('providers.show', $provider);

        $channel = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => $url.'#channel',
            'name' => $provider->name,
            'url' => $url,
            'description' => Str::limit((string) ($provider->tr('bio') ?: $provider->tr('service_type')), 300),
            'image' => $provider->avatarUrl(),
            'sameAs' => array_values(array_unique(array_filter([$provider->telegram_url, ...array_column($provider->officialLinks(), 'url')]))) ?: null,
        ]);

        // Stars only once there are reviews: an empty rating is an error for Google
        if ($provider->reviews_count > 0) {
            $channel['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float) $provider->rating_avg, 1),
                'reviewCount' => $provider->reviews_count,
                'bestRating' => 5,
                'worstRating' => 1,
            ];

            $channel['review'] = $provider->approvedReviews()->with('user')
                ->orderByDesc('helpful_count')->latest()->take(5)->get()
                ->map(fn (Review $review) => [
                    '@type' => 'Review',
                    'author' => ['@type' => 'Person', 'name' => $review->displayName()],
                    'datePublished' => $review->created_at->toDateString(),
                    'name' => $review->headline(),
                    'reviewBody' => Str::limit($review->body, 500),
                    'reviewRating' => ['@type' => 'Rating', 'ratingValue' => $review->rating, 'bestRating' => 5, 'worstRating' => 1],
                ])->all();
        }

        $category = $provider->categories->first();
        $crumbs = array_values(array_filter([
            [__('الرئيسية'), route('home')],
            [__('تصفح مقدمي الخدمة'), route('providers.index')],
            $category ? [$category->tr('name'), route('categories.show', $category)] : null,
            [$provider->name, $url],
        ]));

        return [$channel, [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($crumb, $i) => [
                '@type' => 'ListItem', 'position' => $i + 1, 'name' => $crumb[0], 'item' => $crumb[1],
            ], $crumbs, array_keys($crumbs)),
        ]];
    }
}
