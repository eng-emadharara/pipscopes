{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
{{-- Each URL also lists its language versions (?lang= switches the language) --}}
@foreach ($urls as $url)
@php($base = parse_url($url['loc'], PHP_URL_PATH) ? $url['loc'] : $url['loc'].'/')
    <url>
        <loc>{{ $base }}</loc>
@foreach ($locales as $code)
        <xhtml:link rel="alternate" hreflang="{{ $code }}" href="{{ $base.(str_contains($base, '?') ? '&' : '?').'lang='.$code }}" />
@endforeach
@if (! empty($url['lastmod']))
        <lastmod>{{ $url['lastmod']->toAtomString() }}</lastmod>
@endif
        <changefreq>{{ $url['changefreq'] }}</changefreq>
        <priority>{{ $url['priority'] }}</priority>
    </url>
@endforeach
</urlset>
