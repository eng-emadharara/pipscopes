@props(['provider', 'size' => 'h-14 w-14 text-xl'])
@if ($provider->avatarUrl())
    <img src="{{ $provider->avatarUrl() }}" alt="{{ $provider->name }}" {{ $attributes->merge(['class' => "$size shrink-0 rounded-xl border border-gray-200 object-cover"]) }}>
@else
    <div {{ $attributes->merge(['class' => "$size grid shrink-0 place-items-center rounded-xl bg-brand-50 font-bold text-brand-700 border border-brand-100"]) }}>
        {{ $provider->initials() }}
    </div>
@endif
