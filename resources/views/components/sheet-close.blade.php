{{-- The close button of a bottom sheet on phones; the sheet's script finds it by the data attribute passed in --}}
<button type="button" {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-200']) }}>
    <span class="text-lg leading-none" aria-hidden="true">&times;</span>
    {{ __('إغلاق') }}
</button>
