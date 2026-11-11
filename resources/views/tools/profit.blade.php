<x-tool-page :title="__('حاسبة الربح والخسارة')" current="tools.profit"
             :intro="__('أدخل سعر الدخول والخروج وحجم اللوت، ونحسب لك عدد النقاط والربح أو الخسارة بالدولار.')"
             :description="__('حاسبة الربح والخسارة في التداول مجاناً بالعربي: احسب نقاط الصفقة وربحها أو خسارتها بالدولار للذهب والفوركس والبيتكوين.')">
    <form class="card grid gap-4 p-6 sm:grid-cols-2" data-calc="profit" novalidate>
        @include('tools._symbol', ['noPrice' => true])
        <fieldset>
            <legend class="label">{{ __('نوع الصفقة') }}</legend>
            <div class="grid grid-cols-2 gap-2">
                <label class="choice-card justify-center"><input type="radio" name="direction" value="buy" class="sr-only" checked> {{ __('شراء') }}</label>
                <label class="choice-card justify-center"><input type="radio" name="direction" value="sell" class="sr-only"> {{ __('بيع') }}</label>
            </div>
        </fieldset>
        <div>
            <label class="label" for="entry">{{ __('سعر الدخول') }}</label>
            <input id="entry" name="entry" type="number" step="any" min="0" value="4000" class="input" dir="ltr" inputmode="decimal">
        </div>
        <div>
            <label class="label" for="exit">{{ __('سعر الخروج') }}</label>
            <input id="exit" name="exit" type="number" step="any" min="0" value="4010" class="input" dir="ltr" inputmode="decimal">
        </div>
        <div>
            <label class="label" for="lots">{{ __('حجم اللوت') }}</label>
            <input id="lots" name="lots" type="number" step="any" min="0" value="0.1" class="input" dir="ltr" inputmode="decimal">
        </div>

        <div class="grid gap-3 rounded-2xl bg-gray-50 p-4 text-center sm:col-span-2 sm:grid-cols-2" aria-live="polite">
            <div><p class="text-xs text-gray-500">{{ __('النقاط') }}</p><p class="text-xl font-bold tabular-nums" data-out="pips" dir="ltr">—</p></div>
            <div><p class="text-xs text-gray-500">{{ __('الربح / الخسارة') }}</p><p class="text-2xl font-bold tabular-nums" data-out="result" dir="ltr">—</p></div>
        </div>
    </form>

    <x-slot:explain>
        <h2 class="mb-2 font-bold text-ink">{{ __('كيف نحسبها؟') }}</h2>
        <p>{{ __('النقاط = (سعر الخروج − سعر الدخول) ÷ حجم النقطة، وتنعكس في صفقات البيع. الربح أو الخسارة = النقاط × قيمة النقطة × حجم اللوت.') }}</p>
        <p class="mt-2">{{ __('مثال: شراء ذهب من 4000 وإغلاق عند 4010 بلوت 0.1 = 100 نقطة × 1 دولار = 100 دولار ربح.') }}</p>
        <p class="mt-2 text-xs text-gray-500">{{ __('لا تشمل الحسبة السبريد والعمولة وتبييت الصفقة، فالنتيجة الفعلية أقل قليلاً.') }}</p>
    </x-slot:explain>
</x-tool-page>
