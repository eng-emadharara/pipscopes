<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Provider;
use App\Support\Launch;
use App\Support\Site;
use Illuminate\Support\Facades\Cache;

/** sitemap.xml and robots.txt for search engines (submit the sitemap in Google Search Console). */
class SeoController extends Controller
{
    public function sitemap()
    {
        // Rebuilt at most hourly; a new channel shows up within the hour
        $xml = Cache::remember('seo.sitemap.'.(Launch::isPrelaunch() ? 'pre' : 'live'), now()->addHour(), function () {
            $urls = [['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily']];

            // Before launch only the pages visitors can open are listed
            if (! Launch::isPrelaunch()) {
                $urls[] = ['loc' => route('providers.index'), 'priority' => '0.9', 'changefreq' => 'daily'];

                foreach (Category::where('is_active', true)->orderBy('sort_order')->get(['slug', 'updated_at']) as $category) {
                    $urls[] = ['loc' => route('categories.show', $category), 'priority' => '0.7', 'changefreq' => 'weekly'];
                }

                Provider::active()->orderBy('id')->select(['id', 'slug', 'updated_at'])
                    ->withMax(['reviews as last_review_at' => fn ($q) => $q->where('status', 'approved')], 'updated_at')
                    ->chunk(500, function ($providers) use (&$urls) {
                        foreach ($providers as $provider) {
                            $urls[] = [
                                'loc' => route('providers.show', $provider),
                                // A new review changes the page as much as an edit does
                                'lastmod' => max($provider->updated_at, $provider->last_review_at ? \Illuminate\Support\Carbon::parse($provider->last_review_at) : $provider->updated_at),
                                'priority' => '0.8',
                                'changefreq' => 'daily',
                            ];
                        }
                    });
            }

            $urls[] = ['loc' => route('submissions.create'), 'priority' => '0.5', 'changefreq' => 'monthly'];
            foreach (Page::published()->get() as $page) {
                $urls[] = ['loc' => $page->url(), 'lastmod' => $page->updated_at, 'priority' => '0.4', 'changefreq' => 'monthly'];
            }

            // The XML declaration must be the very first bytes
            return ltrim(view('seo.sitemap', ['urls' => $urls, 'locales' => array_keys(config('trust.locales'))])->render());
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots()
    {
        $lines = ['User-agent: *'];

        if (Site::get('noindex')) {
            // Test copy of the site (Site settings → محركات البحث): keep it out of search results
            $lines[] = 'Disallow: /';
        } else {
            // Private or duplicate pages: admin, sign-in, forms, and endpoints that return fragments
            foreach (['/admin', '/owner', '/login', '/register', '/verify-email', '/lang/', '/search/suggest', '/check-account', '/submit/check', '/p/*/review'] as $path) {
                $lines[] = 'Disallow: '.$path;
            }
            $lines[] = '';
            $lines[] = 'Sitemap: '.route('seo.sitemap');
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
