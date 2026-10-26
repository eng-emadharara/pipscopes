<x-layouts.app :title="__('أرسل بيانات مقدم خدمة')">
    <div class="mx-auto max-w-3xl px-4 py-10">
        <h1 class="text-2xl font-bold md:text-3xl">{{ __('أضف مقدم خدمة إلى المنصة') }}</h1>
        <p class="mt-2 text-gray-600">{{ __('سواء كنت صاحب قناة توصيات أو خدمة نسخ صفقات، أو مستخدماً تريد إضافة مزود لتقييمه، أرسل البيانات وسيراجعها فريقنا قبل النشر.') }}</p>

        <div class="mt-8">
            @include('submissions._form')
        </div>
    </div>
</x-layouts.app>
