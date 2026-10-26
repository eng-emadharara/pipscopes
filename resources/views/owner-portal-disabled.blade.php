<x-layouts.app :title="__('بوابة أصحاب القنوات')">
    <div class="mx-auto max-w-lg px-4 py-20 text-center">
        <div class="mx-auto mb-6 grid h-16 w-16 place-items-center rounded-2xl bg-gray-100 text-3xl">🔒</div>
        <h1 class="text-2xl font-bold">{{ __('بوابة أصحاب القنوات غير متاحة حالياً') }}</h1>
        <p class="mt-3 text-gray-600">{{ __('إذا كنت صاحب قناة وتحتاج للوصول إلى حسابك، تواصل معنا.') }}</p>
        <a href="{{ route('contact') }}" class="btn-primary mt-6">{{ __('تواصل معنا') }}</a>
    </div>
</x-layouts.app>
