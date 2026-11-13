{{-- A member's scam reports: the review status, the case each joined, and what the team did about it --}}
@php($actAt = \App\Support\ScamHelp::ACT_AT)
<x-layouts.app :title="__('بلاغاتي')">
    <section class="bg-ink text-white">
        <div class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-3 px-4 py-8">
            <div>
                <h1 class="text-2xl font-bold">{{ __('بلاغاتي') }}</h1>
                <p class="mt-1 text-sm text-gray-300">{{ __('تابع ما يحدث في كل بلاغ والملف الذي انضم إليه.') }}</p>
            </div>
            <a href="{{ route('scam-reports.create') }}" class="btn-primary">{{ __('بلاغ جديد') }}</a>
        </div>
    </section>

    <div class="mx-auto max-w-3xl space-y-5 px-4 py-8">
        @if (session('scam_reported'))
            <div class="card border-brand-100 bg-brand-50 p-5 text-brand-700" role="status" data-scam-sent>
                <h2 class="font-bold">{{ __('وصلنا بلاغك. شكراً لأنك تساعد غيرك.') }}</h2>
                <p class="mt-1 text-sm leading-relaxed">{{ __('سيراجعه فريقنا، وسنخبرك عندما يُضاف للملف الجماعي. إلى ذلك الحين، لا تتأخر في الخطوات العاجلة.') }}
                    <a href="{{ route('scam-help') }}" class="font-semibold underline">{{ __('ماذا أفعل الآن؟') }}</a></p>
            </div>
        @endif

        @forelse ($reports as $report)
            @php($case = $report->scamCase)
            <article class="card overflow-hidden p-0" data-scam-report="{{ $report->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3 p-5">
                    <div class="min-w-0">
                        <h2 class="font-bold">{{ $report->typeLabel() }}</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            @if ($case) <bdi dir="ltr" class="font-semibold text-ink">{{ $case->target_display }}</bdi> · @endif
                            @if ($report->amountLabel()) <bdi dir="ltr">{{ $report->amountLabel() }}</bdi> · @endif
                            {{ $report->created_at->translatedFormat('j F Y') }}
                        </p>
                    </div>
                    <span @class([
                        'rounded-full px-3 py-1 text-xs font-semibold',
                        'bg-amber-100 text-amber-800' => $report->status === 'pending',
                        'bg-brand-100 text-brand-700' => $report->status === 'confirmed',
                        'bg-gray-100 text-gray-600' => $report->status === 'rejected',
                    ])>{{ [
                        'pending' => __('قيد المراجعة'),
                        'confirmed' => __('مؤكد ومضاف للملف'),
                        'rejected' => __('لم يُؤكَّد'),
                    ][$report->status] ?? $report->status }}</span>
                </div>

                @if ($report->status === 'rejected' && $report->admin_note)
                    <p class="border-t border-gray-100 px-5 py-3 text-sm text-gray-600">{{ __('ملاحظة الفريق: :note', ['note' => $report->admin_note]) }}</p>
                @endif

                @if ($report->status === 'confirmed' && $case)
                    <div class="border-t border-gray-100 bg-gray-50 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span class="font-semibold">{{ trans_choice('في الملف :count بلاغ مؤكد', $case->reports_count, ['count' => $case->reports_count]) }}</span>
                            @if ((float) $case->total_usd > 0)
                                <span class="text-gray-600">{{ __('مجموع الخسائر المبلّغ عنها: ≈ $:n', ['n' => number_format((float) $case->total_usd)]) }}</span>
                            @endif
                        </div>
                        @if ($case->reports_count < $actAt)
                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-200" aria-hidden="true">
                                <div class="h-full rounded-full bg-brand-500" style="width: {{ min(100, $case->reports_count / $actAt * 100) }}%"></div>
                            </div>
                            <p class="mt-2 text-xs text-gray-600">{{ __('نبدأ التحرك عندما يصل الملف إلى :n بلاغات مؤكدة. شارك الصفحة مع من نُصب عليه من نفس الجهة.', ['n' => $actAt]) }}</p>
                        @endif

                        @if ($case->actions->isNotEmpty())
                            <h3 class="mt-4 text-sm font-bold">{{ __('ما قمنا به') }}</h3>
                            <ol class="mt-2 space-y-2 border-s-2 border-brand-100 ps-4 text-sm">
                                @foreach ($case->actions as $action)
                                    <li>
                                        <span class="font-semibold text-brand-700">✓ {{ $action->done_at->translatedFormat('j F') }}: {{ $action->kindLabel() }}</span>
                                        <span class="block text-gray-700">{{ $action->note }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        @endif

                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ route('scam-reports.file', $report) }}" class="btn-outline text-xs" data-scam-file>{{ __('الملف الجماعي للشكوى') }}</a>
                            @if ($case->is_public)
                                <a href="{{ route('scam-cases.show', $case) }}" class="btn-outline text-xs">{{ __('صفحة الملف العامة') }}</a>
                            @endif
                        </div>
                    </div>
                @endif
            </article>
        @empty
            <div class="card p-8 text-center text-gray-600">
                <p>{{ __('لم ترسل أي بلاغ بعد.') }}</p>
                <a href="{{ route('scam-help') }}" class="btn-outline mt-4">{{ __('نُصب عليك؟ ابدأ من هنا') }}</a>
            </div>
        @endforelse
    </div>
</x-layouts.app>
