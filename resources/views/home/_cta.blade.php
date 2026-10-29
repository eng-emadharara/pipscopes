<section @class(['mx-auto max-w-6xl px-4', 'mt-10' => $first, 'mt-14' => ! $first])>
    <div class="card flex flex-col items-center justify-between gap-4 bg-brand-50 p-8 md:flex-row">
        <div>
            <h2 class="text-xl font-bold">{{ __('تقدّم خدمة توصيات أو نسخ صفقات؟') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('أرسل لنا بياناتك وروابطك وسنضيفك إلى المنصة بعد المراجعة.') }}</p>
        </div>
        <a href="{{ route('submissions.create') }}" class="btn-dark">{{ __('أرسل بياناتك') }}</a>
    </div>
</section>
