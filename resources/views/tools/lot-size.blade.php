<x-tool-page :title="__('حاسبة حجم اللوت')" current="tools.lot-size"
             :intro="__('أدخل رأس مالك ونسبة المخاطرة ووقف الخسارة، ونحسب لك حجم اللوت المناسب حتى لا تخسر أكثر مما قررت.')"
             :description="__('حاسبة حجم اللوت مجاناً بالعربي: احسب حجم الصفقة المناسب للذهب والفوركس والبيتكوين حسب رأس المال ونسبة المخاطرة ووقف الخسارة.')">
    <form class="card grid gap-4 p-6 sm:grid-cols-2" data-calc="lot" novalidate>
        <div>
            <label class="label" for="balance">{{ __('رأس المال (دولار)') }}</label>
            <input id="balance" name="balance" type="number" step="any" min="0" value="1000" class="input" dir="ltr" inputmode="decimal">
        </div>
        <div>
            <label class="label" for="risk">{{ __('نسبة المخاطرة في الصفقة (%)') }}</label>
            <input id="risk" name="risk" type="number" step="any" min="0" value="1" class="input" dir="ltr" inputmode="decimal">
        </div>
        @include('tools._symbol')
        <div>
            <label class="label" for="stop">{{ __('وقف الخسارة (بالنقاط)') }}</label>
            <input id="stop" name="stop" type="number" step="any" min="0" value="50" class="input" dir="ltr" inputmode="decimal">
        </div>

        <div class="grid gap-3 rounded-2xl bg-gray-50 p-4 text-center sm:col-span-2 sm:grid-cols-3" aria-live="polite">
            <div><p class="text-xs text-gray-500">{{ __('حجم اللوت') }}</p><p class="text-2xl font-bold text-brand-700 tabular-nums" data-out="lots">—</p></div>
            <div><p class="text-xs text-gray-500">{{ __('المبلغ المعرّض للخسارة') }}</p><p class="text-xl font-bold tabular-nums" data-out="risk-amount">—</p></div>
            <div><p class="text-xs text-gray-500">{{ __('قيمة النقطة بهذا اللوت') }}</p><p class="text-xl font-bold tabular-nums" data-out="per-pip">—</p></div>
        </div>
    </form>

    <x-slot:explain>
        <h2 class="mb-2 font-bold text-ink">{{ __('كيف نحسبها؟') }}</h2>
        <p>{{ __('المبلغ المعرّض للخسارة = رأس المال × نسبة المخاطرة. ثم حجم اللوت = ذلك المبلغ ÷ (وقف الخسارة بالنقاط × قيمة النقطة للوت الواحد). نقرّب الناتج للأسفل لأقرب 0.01 حتى لا تتجاوز المخاطرة ما حددته.') }}</p>
        <p class="mt-2">{{ __('مثال: رأس مال 1000 دولار ومخاطرة 1% تعني 10 دولارات. على الذهب بوقف 50 نقطة (النقطة 0.10 وقيمتها 10 دولارات للوت)، حجم اللوت = 10 ÷ (50 × 10) = 0.02.') }}</p>
        <p class="mt-2 text-xs text-gray-500">{{ __('الأرقام تقريبية وقد تختلف قليلاً حسب الوسيط وحجم العقد لديه.') }}</p>
    </x-slot:explain>
</x-tool-page>
