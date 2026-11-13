<x-layouts.app :title="__('أدوات المتداول المجانية')"
               :description="__('حاسبات تداول مجانية بالعربي: حجم اللوت، قيمة النقطة، والربح والخسارة، مع فحص مجاني لقنوات التوصيات على تيليجرام.')">
    <section class="bg-ink text-white">
        <div class="mx-auto max-w-3xl px-4 py-10 text-center">
            <h1 class="text-2xl font-bold md:text-4xl">{{ __('أدوات المتداول المجانية') }}</h1>
            <p class="mx-auto mt-3 max-w-xl text-sm text-gray-300 md:text-base">{{ __('حاسبات بسيطة تحتاجها قبل كل صفقة، وفحص لأي قناة توصيات قبل أن تتبعها.') }}</p>
        </div>
    </section>

    <div class="mx-auto grid max-w-4xl gap-4 px-4 py-8 sm:grid-cols-2" data-tools>
        @foreach (array_filter([
            ['channel-check', 'shield', __('افحص قناة توصيات'), __('التفاعل الحقيقي، الصفقات مقابل الادعاءات، الخسائر، والعمر.')],
            ['channel-compare', 'users', __('قارن بين قناتين'), __('ضع قناتين جنباً إلى جنب وشاهد الفرق.')],
            \App\Support\HomeSections::enabled('account_check')
                ? ['account-check', 'alert', __('هل هذا الحساب حقيقي؟'), __('راسلك شخص يدّعي أنه من قناة؟ تحقق قبل أن ترسل أي أموال.')] : null,
            ['scam-help', 'alert', __('نُصب عليك؟ ماذا تفعل'), __('خطوات عاجلة حسب ما حدث وطريقة دفعك، وبلاغ يُجمع مع بلاغات الآخرين.')],
            ['tools.money-management', 'shield', __('حاسبة إدارة رأس المال'), __('خطة كاملة حسب رأس مالك وأسلوبك: المخاطرة، اللوت، ومتى تتوقف.')],
            ['tools.lot-size', 'calendar', __('حاسبة حجم اللوت'), __('كم لوت تفتح حسب رأس مالك ونسبة المخاطرة؟')],
            ['tools.pip-value', 'star', __('حاسبة قيمة النقطة'), __('كم دولاراً تساوي النقطة في صفقتك؟')],
            ['tools.profit', 'check', __('حاسبة الربح والخسارة'), __('كم تربح أو تخسر بين سعر الدخول والخروج؟')],
        ]) as [$route, $icon, $name, $text])
            <a href="{{ route($route) }}" class="card flex items-start gap-4 p-5 hover:border-brand-500">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-ui-icon :name="$icon" class="h-5 w-5" /></span>
                <span>
                    <span class="block font-bold">{{ $name }}</span>
                    <span class="mt-0.5 block text-sm text-gray-600">{{ $text }}</span>
                </span>
            </a>
        @endforeach
    </div>
</x-layouts.app>
