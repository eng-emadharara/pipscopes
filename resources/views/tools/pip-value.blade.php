<x-tool-page :title="__('حاسبة قيمة النقطة')" current="tools.pip-value"
             :intro="__('اختر الأداة وحجم اللوت، ونعرض لك كم تساوي النقطة الواحدة بالدولار، وكم تساوي 10 و100 نقطة.')"
             :description="__('حاسبة قيمة النقطة (Pip Value) مجاناً بالعربي للذهب والفضة وأزواج الفوركس والبيتكوين حسب حجم اللوت.')">
    <form class="card grid gap-4 p-6 sm:grid-cols-2" data-calc="pip" novalidate>
        @include('tools._symbol')
        <div>
            <label class="label" for="lots">{{ __('حجم اللوت') }}</label>
            <input id="lots" name="lots" type="number" step="any" min="0" value="0.1" class="input" dir="ltr" inputmode="decimal">
        </div>

        <div class="grid gap-3 rounded-2xl bg-gray-50 p-4 text-center sm:col-span-2 sm:grid-cols-3" aria-live="polite">
            <div><p class="text-xs text-gray-500">{{ __('قيمة النقطة') }}</p><p class="text-2xl font-bold text-brand-700 tabular-nums" data-out="per-pip">—</p></div>
            <div><p class="text-xs text-gray-500">{{ __('قيمة 10 نقاط') }}</p><p class="text-xl font-bold tabular-nums" data-out="per-10">—</p></div>
            <div><p class="text-xs text-gray-500">{{ __('قيمة 100 نقطة') }}</p><p class="text-xl font-bold tabular-nums" data-out="per-100">—</p></div>
        </div>
    </form>

    <x-slot:explain>
        <h2 class="mb-2 font-bold text-ink">{{ __('ما هي قيمة النقطة؟') }}</h2>
        <p>{{ __('النقطة أصغر حركة معتادة في سعر الأداة: 0.0001 في أغلب أزواج الفوركس، و0.01 في أزواج الين، و0.10 في الذهب. قيمتها بالدولار تعتمد على حجم العقد وحجم اللوت.') }}</p>
        <p class="mt-2">{{ __('عندما تقول قناة «ربحنا 100 نقطة»، اضرب 100 في قيمة النقطة بلوتك لتعرف كم كان سيربح حسابك فعلاً، وكم كان سيخسر لو ضرب الستوب.') }}</p>
        <p class="mt-2 text-xs text-gray-500">{{ __('بعض القنوات تحسب نقطة الذهب 0.01 بدل 0.10، فتبدو أرقامها أكبر بعشر مرات. تأكد كيف تحسبها القناة.') }}</p>
    </x-slot:explain>
</x-tool-page>
