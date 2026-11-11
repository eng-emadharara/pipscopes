{{-- The free tools: "Is this account real?" first when it's switched on (Settings → home sections: account_check), then the calculators --}}
@php
    $tools = array_values(array_filter([
        \App\Support\HomeSections::enabled('account_check')
            ? ['account-check', '🛡️', __('هل هذا الحساب حقيقي؟'), __('راسلك شخص يدّعي أنه من قناة؟ تحقق قبل أن ترسل أي أموال.')] : null,
        ['tools.money-management', '🧭', __('حاسبة إدارة رأس المال'), __('خطة كاملة حسب رأس مالك وأسلوبك: المخاطرة، اللوت، ومتى تتوقف.')],
        ['tools.lot-size', '📐', __('حاسبة حجم اللوت'), __('كم لوت تفتح حسب رأس مالك ونسبة المخاطرة؟')],
        ['tools.pip-value', '💲', __('حاسبة قيمة النقطة'), __('كم دولاراً تساوي النقطة في صفقتك؟')],
        ['tools.profit', '📊', __('حاسبة الربح والخسارة'), __('كم تربح أو تخسر بين سعر الدخول والخروج؟')],
    ]));
    // One row of four on the homepage; the rest are a tap away on the tools page
    $tools = array_slice($tools, 0, 4);
@endphp
<section @class(['mx-auto max-w-6xl px-4', 'mt-10' => $first, 'mt-14' => ! $first]) data-home-tools>
    <div class="mb-5 flex items-baseline justify-between gap-3">
        <h2 class="text-2xl font-bold">{{ \App\Support\HomeSections::title($section) }}</h2>
        <a href="{{ route('tools.index') }}" class="text-sm font-semibold text-brand-600 hover:underline">{{ __('كل الأدوات') }} ←</a>
    </div>
    {{-- One row on wide screens, however many tools are on --}}
    <div @class(['grid gap-4 sm:grid-cols-2', 'lg:grid-cols-4' => count($tools) === 4, 'lg:grid-cols-3' => count($tools) === 3])>
        @foreach ($tools as [$route, $emoji, $name, $text])
            <a href="{{ route($route) }}" class="card flex items-start gap-4 p-5 transition hover:border-brand-500" data-track="tool_click" data-track-params='@json(['tool' => $route, 'location' => 'home'])'>
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-xl" aria-hidden="true">{{ $emoji }}</span>
                <span>
                    <span class="block font-bold">{{ $name }}</span>
                    <span class="mt-0.5 block text-sm text-gray-600">{{ $text }}</span>
                </span>
            </a>
        @endforeach
    </div>
</section>
