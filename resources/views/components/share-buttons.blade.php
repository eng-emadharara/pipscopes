@props(['url', 'text', 'variant' => 'button'])
@php
    $u = rawurlencode($url);
    $t = rawurlencode($text);
    $networks = [
        ['telegram', __('تيليجرام'), "https://t.me/share/url?url=$u&text=$t", 'text-sky-600'],
        ['x', 'X', "https://twitter.com/intent/tweet?text=$t&url=$u", 'text-ink'],
        ['whatsapp', __('واتساب'), 'https://wa.me/?text='.rawurlencode($text.' '.$url), 'text-green-600'],
        ['facebook', __('فيسبوك'), "https://www.facebook.com/sharer/sharer.php?u=$u", 'text-blue-600'],
    ];
@endphp
{{-- Share menu; resources/js/share.js adds the phone's native share sheet and "copy link" --}}
<details {{ $attributes->merge(['class' => 'relative']) }} data-share>
    @if ($variant === 'button')
        {{-- Icon only on phones so it fits on the same row as the other buttons --}}
        <summary class="btn-outline cursor-pointer list-none px-3.5 max-md:h-11 max-sm:w-11 max-sm:px-0 sm:px-5" aria-label="{{ __('مشاركة') }}">
            <x-ui-icon name="share" class="h-4 w-4" /> <span class="hidden sm:inline">{{ __('مشاركة') }}</span>
        </summary>
    @else
        <summary class="inline-flex cursor-pointer list-none items-center gap-1 py-1.5 text-gray-500 hover:text-ink">
            <x-ui-icon name="share" class="h-4 w-4" /> {{ __('مشاركة') }}
        </summary>
    @endif

    {{-- Phones: dim the page behind the bottom sheet; tapping it closes the menu --}}
    <div data-share-close class="fixed inset-0 z-40 bg-ink/40 transition-opacity duration-200 starting:opacity-0 sm:hidden"></div>

    {{-- Bottom sheet on phones (a dropdown can't be anchored reliably there), dropdown from sm up --}}
    <div class="fixed inset-x-0 bottom-0 z-50 rounded-t-3xl bg-white p-3 pb-[max(1rem,env(safe-area-inset-bottom))] text-start text-base text-ink shadow-2xl transition-transform duration-200 ease-out starting:translate-y-full
                sm:absolute sm:inset-x-auto sm:bottom-auto sm:end-0 sm:mt-2 sm:w-56 sm:rounded-2xl sm:border sm:border-gray-200 sm:p-1.5 sm:pb-1.5 sm:text-sm sm:shadow-xl sm:transition-none sm:starting:translate-y-0">
        <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-gray-300 sm:hidden"></div>
        <p class="px-3 pb-2 font-bold sm:hidden">{{ __('مشاركة') }}</p>
        <button type="button" data-native-share data-url="{{ $url }}" data-text="{{ $text }}"
                class="hidden w-full items-center gap-3 rounded-xl px-3 py-3 text-start hover:bg-gray-50 sm:py-2">
            <x-ui-icon name="share" class="h-5 w-5 text-gray-600" /> {{ __('مشاركة عبر…') }}
        </button>
        @foreach ($networks as [$icon, $label, $href, $color])
            <a href="{{ $href }}" target="_blank" rel="noopener nofollow" class="flex items-center gap-3 rounded-xl px-3 py-3 hover:bg-gray-50 sm:py-2">
                <x-ui-icon :name="$icon" class="h-5 w-5 {{ $color }}" /> {{ $label }}
            </a>
        @endforeach
        <button type="button" data-copy-url="{{ $url }}" data-copied-label="{{ __('تم نسخ الرابط ✓') }}"
                class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-start hover:bg-gray-50 sm:py-2">
            <x-ui-icon name="link" class="h-5 w-5 text-gray-600" /> <span>{{ __('نسخ الرابط') }}</span>
        </button>
    </div>
</details>
