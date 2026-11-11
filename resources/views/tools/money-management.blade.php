<x-tool-page :title="__('حاسبة إدارة رأس المال')" current="tools.money-management"
             :intro="__('أدخل رأس مالك وعلى ماذا تتداول وأسلوبك، ونعطيك خطة كاملة: كم تخاطر في الصفقة، اللوت المناسب، الهدف، ومتى تتوقف.')"
             :description="__('حاسبة إدارة رأس المال مجاناً بالعربي: خطة تداول حسب رأس مالك والأداة وأسلوبك، مع اللوت المناسب ونسبة المخاطرة وحدود الخسارة اليومية والأسبوعية.')">
    <form class="card grid gap-5 p-6" data-calc="plan" novalidate>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="balance">{{ __('رأس المال (دولار)') }}</label>
                <input id="balance" name="balance" type="number" step="any" min="0" value="1000" class="input" dir="ltr" inputmode="decimal">
            </div>
            @include('tools._symbol')
        </div>

        <fieldset>
            <legend class="label">{{ __('أسلوب تداولك') }}</legend>
            <div class="grid gap-2 sm:grid-cols-3">
                @foreach (['scalp' => [__('سكالبينج'), __('صفقات سريعة بدقائق')], 'day' => [__('تداول يومي'), __('تُغلق في نفس اليوم')], 'swing' => [__('سوينغ'), __('تبقى أياماً')]] as $value => [$name, $hint])
                    <label class="choice-card flex-col !items-start">
                        <input type="radio" name="style" value="{{ $value }}" class="sr-only" @checked($value === 'day')>
                        <span class="text-sm font-semibold">{{ $name }}</span>
                        <span class="text-xs text-gray-500">{{ $hint }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset>
            <legend class="label">{{ __('كم تتحمل من المخاطرة؟') }}</legend>
            <div class="grid gap-2 sm:grid-cols-3">
                @foreach (['careful' => [__('محافظ'), __('0.5% في الصفقة')], 'balanced' => [__('متوازن'), __('1% في الصفقة (الأكثر شيوعاً)')], 'bold' => [__('جريء'), __('2% في الصفقة')]] as $value => [$name, $hint])
                    <label class="choice-card flex-col !items-start">
                        <input type="radio" name="profile" value="{{ $value }}" class="sr-only" @checked($value === 'balanced')>
                        <span class="text-sm font-semibold">{{ $name }}</span>
                        <span class="text-xs text-gray-500">{{ $hint }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="sm:w-1/2">
            <label class="label" for="stop">{{ __('وقف الخسارة المعتاد (بالنقاط)') }}</label>
            <input id="stop" name="stop" type="number" step="any" min="0" class="input" dir="ltr" inputmode="decimal">
            <p class="mt-1 text-xs text-gray-500">{{ __('نقترحه حسب الأداة وأسلوبك، ويمكنك تغييره.') }}</p>
        </div>

        {{-- The plan --}}
        <div class="space-y-4 rounded-2xl bg-gray-50 p-5" aria-live="polite" data-plan>
            <h2 class="font-bold">{{ __('خطتك لإدارة رأس المال') }}</h2>

            <div class="grid gap-3 text-center sm:grid-cols-3">
                <div class="rounded-xl bg-white p-3">
                    <p class="text-xs text-gray-500">{{ __('مخاطرتك في كل صفقة') }}</p>
                    <p class="text-xl font-bold tabular-nums"><span data-out="risk-pct">—</span> = <span data-out="risk-amount">—</span></p>
                </div>
                <div class="rounded-xl bg-white p-3">
                    <p class="text-xs text-gray-500">{{ __('اللوت المناسب') }}</p>
                    <p class="text-2xl font-bold text-brand-700 tabular-nums" data-out="lots">—</p>
                </div>
                <div class="rounded-xl bg-white p-3">
                    <p class="text-xs text-gray-500">{{ __('أقصى عدد صفقات مفتوحة معاً') }}</p>
                    <p class="text-xl font-bold tabular-nums" data-out="open-trades">—</p>
                </div>
            </div>

            <p class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" data-plan-too-small hidden>
                ⚠️ {{ __('رأس مالك صغير على هذا الوقف: أصغر لوت (0.01) يخاطر بـ') }} <b data-out="min-lot-risk" dir="ltr">—</b> {{ __('من حسابك في الصفقة. صغّر الوقف، أو تداول على حساب سنت، أو انتظر حتى يكبر رأس مالك.') }}
            </p>

            <ul class="space-y-2 text-sm leading-relaxed text-gray-700">
                <li>🛑 {{ __('ضع وقف الخسارة على') }} <b data-out="stop-pips" dir="ltr">—</b> {{ __('نقطة، فإذا ضرب تخسر') }} <b data-out="stop-amount" dir="ltr">—</b> {{ __('فقط.') }}</li>
                <li>🎯 {{ __('اجعل هدفك ضعف الوقف على الأقل:') }} <b data-out="target-pips" dir="ltr">—</b> {{ __('نقطة، أي') }} <b data-out="target-amount" dir="ltr">—</b>. {{ __('هكذا تكفي صفقة رابحة لتعويض خسارتين.') }}</li>
                <li>📅 {{ __('توقف عن التداول لبقية اليوم إذا خسرت') }} <b data-out="day-limit" dir="ltr">—</b>.</li>
                <li>🗓️ {{ __('وتوقف لبقية الأسبوع إذا خسرت') }} <b data-out="week-limit" dir="ltr">—</b>.</li>
                <li>📉 {{ __('لو خسرت 10 صفقات متتالية بهذه الخطة يبقى معك') }} <b data-out="streak-left" dir="ltr">—</b> ({{ __('خسارة') }} <span data-out="streak-loss" dir="ltr">—</span>)، {{ __('فتستطيع الاستمرار.') }}</li>
            </ul>
        </div>
    </form>

    <x-slot:explain>
        <h2 class="mb-2 font-bold text-ink">{{ __('قواعد إدارة رأس المال') }}</h2>
        <ol class="list-decimal space-y-1.5 ps-5">
            <li>{{ __('لا تخاطر بأكثر من 1–2% من حسابك في صفقة واحدة، مهما بدت التوصية مؤكدة.') }}</li>
            <li>{{ __('حدّد وقف الخسارة قبل الدخول، واحسب اللوت منه، لا العكس.') }}</li>
            <li>{{ __('لا تفتح صفقات كثيرة معاً على نفس الأداة؛ مخاطرتها تتجمع.') }}</li>
            <li>{{ __('عند الوصول لحد الخسارة اليومي توقف، فالتداول بعد الخسارة بدافع التعويض أسرع طريق لخسارة الحساب.') }}</li>
            <li>{{ __('القناة التي تطلب منك لوتات كبيرة أو «دبل» بعد الخسارة لا تهتم بحسابك.') }}</li>
        </ol>
        <p class="mt-3 text-xs text-gray-500">{{ __('الخطة تقريبية للتعلم وليست نصيحة مالية، وقيمة النقطة قد تختلف قليلاً حسب الوسيط.') }}</p>
    </x-slot:explain>
</x-tool-page>
