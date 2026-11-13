{{-- A published case: how many confirmed reports, what kind, the team's summary and what was done. No names, stories or proofs --}}
@php
    $typeInfo = \App\Support\ScamHelp::types();
    $isAccount = ! in_array($case->target_type, ['wallet', 'broker'], true);
    $reportUrl = route('scam-reports.create', array_filter([
        'account' => $isAccount ? $case->target_display : null,
    ]));
@endphp
<x-layouts.app :title="__('بلاغات نصب موثّقة عن :target', ['target' => $case->displayName()])"
               :description="trans_choice(':count بلاغ نصب موثّق عن :target على :app. اطّلع عليها قبل أن تتعامل معه.', $case->reports_count, ['count' => $case->reports_count, 'target' => $case->displayName(), 'app' => config('app.name')])">
    <section class="border-b border-red-200 bg-red-50">
        <div class="mx-auto max-w-3xl px-4 py-10">
            <span class="inline-flex items-center gap-1 rounded-full bg-red-600 px-3 py-1 text-xs font-semibold text-white"><x-ui-icon name="alert" class="h-3.5 w-3.5" /> {{ __('ملف بلاغات نصب') }}</span>
            <h1 class="mt-3 break-words text-2xl font-bold text-red-900 md:text-3xl">{{ $case->displayName() }}</h1>
            <p class="mt-1 text-sm text-red-800">{{ $case->targetLabel() }}: <bdi dir="ltr" class="font-mono">{{ $case->target_display }}</bdi></p>
            @if ($case->provider)
                <a href="{{ route('providers.show', $case->provider) }}" class="mt-2 inline-block text-sm font-semibold text-red-900 underline">{{ __('صفحة القناة وتقييماتها') }}</a>
            @endif

            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-white p-4"><b class="block text-2xl">{{ $case->reports_count }}</b><span class="text-sm text-gray-600">{{ __('بلاغات مؤكدة') }}</span></div>
                @if ((float) $case->total_usd > 0)
                    <div class="rounded-xl bg-white p-4"><b class="block text-2xl" dir="ltr">≈ ${{ number_format((float) $case->total_usd) }}</b><span class="text-sm text-gray-600">{{ __('خسائر مبلّغ عنها') }}</span></div>
                @endif
                @if ($range?->first)
                    <div class="rounded-xl bg-white p-4"><b class="block text-lg" dir="ltr">{{ \Illuminate\Support\Carbon::parse($range->first)->format('Y/m') }}@if ($range->last !== $range->first) – {{ \Illuminate\Support\Carbon::parse($range->last)->format('Y/m') }}@endif</b><span class="text-sm text-gray-600">{{ __('فترة البلاغات') }}</span></div>
                @endif
            </div>
        </div>
    </section>

    <div class="mx-auto max-w-3xl space-y-5 px-4 py-8" data-scam-case>
        @if ($case->public_summary)
            <section class="card p-5">
                <h2 class="font-bold">{{ __('ملخص البلاغات') }}</h2>
                <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-gray-700">{{ $case->public_summary }}</p>
            </section>
        @endif

        @if ($types)
            <section class="card p-5">
                <h2 class="font-bold">{{ __('ماذا حدث مع المبلّغين') }}</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($types as $key => $count)
                        <li class="flex items-center justify-between gap-3"><span><span aria-hidden="true">{{ $typeInfo[$key][0] ?? '' }}</span> {{ $typeInfo[$key][1] ?? $key }}</span><b>{{ $count }}</b></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($case->actions->isNotEmpty())
            <section class="card p-5">
                <h2 class="font-bold">{{ __('ما قمنا به') }}</h2>
                <ol class="mt-3 space-y-3 border-s-2 border-brand-100 ps-4 text-sm">
                    @foreach ($case->actions as $action)
                        <li><span class="font-semibold text-brand-700">✓ {{ $action->done_at->translatedFormat('j F Y') }}: {{ $action->kindLabel() }}</span>
                            <span class="block text-gray-700">{{ $action->note }}</span></li>
                    @endforeach
                </ol>
            </section>
        @endif

        <section class="card flex flex-wrap items-center justify-between gap-4 border-brand-100 bg-brand-50 p-5">
            <div>
                <h2 class="font-bold text-brand-700">{{ __('نُصب عليك من نفس الجهة؟') }}</h2>
                <p class="mt-1 text-sm text-brand-700">{{ __('أضف بلاغك إلى الملف. كلما زادت البلاغات صار تحركنا أقوى.') }}</p>
            </div>
            <a href="{{ $reportUrl }}" class="btn-primary">{{ __('أضف بلاغك') }}</a>
        </section>

        <p class="rounded-xl bg-gray-100 p-4 text-xs leading-relaxed text-gray-600">
            {{ __('هذه بلاغات من متداولين راجعها فريقنا مع إثباتاتها، وليست حكماً قضائياً. لصاحب الحساب حق الرد عبر صفحة التواصل.') }}
            <a href="{{ route('contact') }}" class="underline">{{ __('تواصل معنا') }}</a>
        </p>
    </div>
</x-layouts.app>
