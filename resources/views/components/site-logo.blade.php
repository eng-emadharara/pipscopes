{{-- The site's logo and name (Admin → إعدادات الموقع); the star mark is the default logo --}}
@php
    $logo = \App\Support\Site::fileUrl('logo');
    $showName = ! $logo || \App\Support\Site::get('show_name_with_logo', true);
@endphp
<a href="{{ route('home') }}" {{ $attributes->merge(['class' => 'flex min-w-0 items-center gap-1 text-lg font-bold']) }}>
    @if ($logo)
        {{-- Pushed toward the name so a narrow logo doesn't leave a gap inside its box --}}
        <img src="{{ $logo }}" alt="{{ config('app.name') }}" class="{{ $showName ? 'h-10 w-15 ltr:object-right rtl:object-left' : 'h-10 max-w-44' }} shrink-0 object-contain">
    @else
        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-brand-500">
            <x-ui-icon name="star" class="h-5 w-5 fill-white" />
        </span>
    @endif
    @if ($showName)
        <span class="truncate">{{ config('app.name') }}</span>
    @endif
</a>
