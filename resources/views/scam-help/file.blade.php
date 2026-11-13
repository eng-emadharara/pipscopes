{{-- The joint case file a reporter attaches to their complaint (bank, police): every confirmed report without names or stories, plus their own in full. Printed or saved as PDF from the browser --}}
@php($locale = app()->getLocale())
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ config("trust.locales.$locale.dir", 'rtl') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('ملف بلاغات: :target', ['target' => $case->target_display]) }} | {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; color: #191919; font: 14px/1.6 system-ui, "Segoe UI", Tahoma, sans-serif; }
        .page { max-width: 820px; margin: 24px auto; background: #fff; padding: 36px; border-radius: 12px; }
        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 3px solid #00b67a; padding-bottom: 16px; }
        h1 { margin: 0; font-size: 22px; }
        h2 { font-size: 16px; margin: 28px 0 8px; }
        .muted { color: #6b7280; font-size: 12px; }
        .brand { font-weight: 800; color: #00b67a; font-size: 18px; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 20px; }
        .stat { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; }
        .stat b { display: block; font-size: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: start; vertical-align: top; }
        th { background: #f9fafb; }
        .ltr { direction: ltr; unicode-bidi: isolate; }
        .mine { background: #ecfdf5; }
        .box { border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; white-space: pre-line; }
        .actions { display: flex; gap: 8px; justify-content: center; margin: 16px; }
        .actions button, .actions a { font: inherit; padding: 8px 18px; border-radius: 999px; border: 1px solid #d1d5db; background: #fff; color: inherit; text-decoration: none; cursor: pointer; }
        .actions button { background: #00b67a; border-color: #00b67a; color: #fff; font-weight: 600; }
        @media print { body { background: #fff; } .page { margin: 0; padding: 0; border-radius: 0; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">{{ __('طباعة أو حفظ PDF') }}</button>
        <a href="{{ route('scam-reports.index') }}">{{ __('رجوع لبلاغاتي') }}</a>
    </div>

    <main class="page" data-scam-file-page>
        <header>
            <div>
                <h1>{{ __('ملف بلاغات احتيال جماعي') }}</h1>
                <div class="muted">{{ __('تاريخ الإصدار: :date · رقم الملف: :id', ['date' => now()->format('Y-m-d'), 'id' => 'PS-'.$case->id]) }}</div>
            </div>
            <div class="brand">{{ config('app.name') }}</div>
        </header>

        <h2>{{ __('الجهة المبلّغ عنها') }}</h2>
        <div><strong>{{ $case->targetLabel() }}:</strong> <span class="ltr">{{ $case->target_display }}</span>
            @if ($case->provider) · {{ $case->provider->name }} @endif</div>

        <div class="stats">
            <div class="stat"><b>{{ $case->reports_count }}</b>{{ __('بلاغات مؤكدة') }}</div>
            <div class="stat"><b class="ltr">≈ ${{ number_format((float) $case->total_usd) }}</b>{{ __('مجموع الخسائر المبلّغ عنها') }}</div>
            <div class="stat"><b>{{ $reports->pluck('user_id')->unique()->count() }}</b>{{ __('أشخاص مختلفون') }}</div>
        </div>

        <h2>{{ __('البلاغات المؤكدة (بدون أسماء)') }}</h2>
        <table>
            <thead><tr><th>#</th><th>{{ __('تاريخ الدفع') }}</th><th>{{ __('نوع الاحتيال') }}</th><th>{{ __('طريقة الدفع') }}</th><th>{{ __('المبلغ') }}</th></tr></thead>
            <tbody>
                @foreach ($reports as $i => $row)
                    <tr @class(['mine' => $row->id === $report->id])>
                        <td>{{ $i + 1 }}@if ($row->id === $report->id) ({{ __('بلاغك') }})@endif</td>
                        <td class="ltr">{{ $row->paid_at?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $row->typeLabel() }}</td>
                        <td>{{ $row->paymentLabel() }}</td>
                        <td class="ltr">{{ $row->amountLabel() ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($actions->isNotEmpty())
            <h2>{{ __('الإجراءات التي قام بها :app', ['app' => config('app.name')]) }}</h2>
            <table>
                <tbody>
                    @foreach ($actions as $action)
                        <tr><td class="ltr" style="width: 110px">{{ $action->done_at->format('Y-m-d') }}</td><td><strong>{{ $action->kindLabel() }}:</strong> {{ $action->note }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h2>{{ __('بلاغك') }}</h2>
        <table>
            <tbody>
                <tr><th style="width: 160px">{{ __('نوع الاحتيال') }}</th><td>{{ $report->typeLabel() }}</td></tr>
                <tr><th>{{ __('طريقة الدفع') }}</th><td>{{ $report->paymentLabel() }}</td></tr>
                <tr><th>{{ __('تاريخ الدفع') }}</th><td class="ltr">{{ $report->paid_at?->format('Y-m-d') ?? '—' }}</td></tr>
                <tr><th>{{ __('المبلغ') }}</th><td class="ltr">{{ $report->amountLabel() ?? '—' }}</td></tr>
                @foreach (['account' => __('الحساب'), 'wallet' => __('المحفظة'), 'broker' => __('الوسيط')] as $field => $label)
                    @if ($report->$field)
                        <tr><th>{{ $label }}</th><td class="ltr">{{ $report->$field }}</td></tr>
                    @endif
                @endforeach
            </tbody>
        </table>
        <div class="box" style="margin-top: 10px">{{ $report->story }}</div>

        <p class="muted" style="margin-top: 28px">
            {{ __('جُمعت هذه البلاغات من متداولين على :app وراجعها فريقنا مع إثباتاتها. الأسماء والإثباتات محفوظة لدينا ولا تُنشر، ويمكن للجهات المختصة طلبها عبر صفحة التواصل: :url', ['app' => config('app.name'), 'url' => route('contact')]) }}
        </p>
    </main>
</body>
</html>
