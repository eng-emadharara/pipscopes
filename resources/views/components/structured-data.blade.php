{{-- schema.org JSON-LD blocks (App\Support\StructuredData); pushed into the page <head> --}}
@props(['items'])
@push('structured-data')
    @foreach ($items as $item)
        <script type="application/ld+json">{!! json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endforeach
@endpush
