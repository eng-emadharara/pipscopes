{{-- "I was scammed, what now?": pick what happened and how you paid, get the steps (App\Support\ScamHelp). Works without JavaScript --}}
@php
    $types = \App\Support\ScamHelp::types();
    $payments = \App\Support\ScamHelp::payments();
    $reportUrl = route('scam-reports.create', array_filter(['type' => $type, 'pay' => $payment, 'paid_at' => $paidAt?->toDateString()]));
@endphp
<x-layouts.app :title="__('نُصب عليك من قناة توصيات؟ ماذا تفعل الآن')"
               :description="__('خطوات واضحة ومجانية إذا نصبت عليك قناة توصيات أو حساب على تيليجرام: كيف تحاول استرجاع أموالك، وأين تبلّغ، وكيف تحمي نفسك من النصبة التالية.')">
    <section class="bg-ink pb-20 pt-12 text-white">
        <div class="mx-auto max-w-3xl px-4 text-center">
            <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-gray-200">🛟 {{ __('مساعدة مجانية وبدون تسجيل') }}</span>
            <h1 class="mt-4 text-3xl font-bold leading-tight md:text-5xl">{{ __('نُصب عليك من قناة توصيات؟') }}</h1>
            <p class="mx-auto mt-4 max-w-2xl text-base leading-relaxed text-gray-300 md:text-lg">
                {{ __('لست وحدك، وليس الذنب ذنبك. اختر ما حدث معك وكيف دفعت، ونعطيك خطوات واضحة تبدأ بها الآن.') }}
            </p>
            {{-- Reporting is offered right away, not only under the steps --}}
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3" data-scam-hero-actions>
                <a href="{{ $reportUrl }}" class="btn-primary" data-scam-report-link>🚨 {{ __('بلّغ عمّا حدث معك') }}</a>
                @auth
                    <a href="{{ route('scam-reports.index') }}" class="btn border border-white/30 text-white hover:bg-white/10">{{ __('بلاغاتي') }}</a>
                @endauth
            </div>
            <p class="mt-3 text-xs text-gray-400">{{ __('بلاغك يُجمع مع بلاغات الآخرين عن نفس الجهة، ونحاول التحرك به.') }}</p>
        </div>
    </section>

    <div class="mx-auto -mt-12 max-w-4xl space-y-6 px-4 pb-12">
        <form method="GET" action="{{ route('scam-help') }}#advice" class="card space-y-7 p-5 shadow-sm md:p-8" data-scam-form>
            <fieldset>
                <legend class="text-lg font-bold">1. {{ __('ماذا حدث؟') }}</legend>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ($types as $key => [$emoji, $label, $hint])
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 hover:border-brand-500 has-checked:border-brand-500 has-checked:bg-brand-50">
                            <input type="radio" name="type" value="{{ $key }}" class="mt-1 accent-brand-500" @checked($type === $key)>
                            <span>
                                <span class="block font-semibold"><span aria-hidden="true">{{ $emoji }}</span> {{ $label }}</span>
                                <span class="mt-0.5 block text-xs leading-relaxed text-gray-600">{{ $hint }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset>
                <legend class="text-lg font-bold">2. {{ __('كيف دفعت؟') }}</legend>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($payments as $key => $label)
                        <label class="cursor-pointer rounded-full border border-gray-300 px-4 py-2 text-sm hover:border-brand-500 has-checked:border-brand-500 has-checked:bg-brand-50 has-checked:font-semibold has-checked:text-brand-700">
                            <input type="radio" name="pay" value="{{ $key }}" class="sr-only" @checked($payment === $key)>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <div class="mt-4 max-w-xs">
                    <label class="label" for="paid_at">{{ __('متى دفعت؟ (تقريباً)') }}</label>
                    <input id="paid_at" type="date" name="paid_at" value="{{ $paidAt?->toDateString() }}" max="{{ now()->toDateString() }}" class="input" dir="ltr">
                    <p class="mt-1 text-xs text-gray-500">{{ __('يهمنا التاريخ لأن بعض طرق الاسترجاع لها مهلة.') }}</p>
                </div>
            </fieldset>

            <button type="submit" class="btn-primary w-full sm:w-auto">{{ __('اعرض ما يجب أن أفعله') }}</button>
        </form>

        @if ($advice)
            <section id="advice" class="scroll-mt-4 space-y-4" data-scam-advice>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-2xl font-bold">{{ __('ماذا تفعل الآن') }}</h2>
                    <a href="{{ $reportUrl }}" class="btn-primary">🚨 {{ __('بلّغ عمّا حدث معك') }}</a>
                </div>
                <ol class="space-y-3">
                    @foreach ($advice as $i => $step)
                        <li @class(['card flex items-start gap-4 p-5', 'border-red-200 bg-red-50/60' => $step['urgent']]) data-step="{{ $step['key'] }}">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white text-xl shadow-sm" aria-hidden="true">{{ $step['icon'] }}</span>
                            <div>
                                <h3 class="font-bold">
                                    {{ $i + 1 }}. {{ $step['title'] }}
                                    @if ($step['urgent'])
                                        <span class="ms-1 rounded-full bg-red-600 px-2 py-0.5 align-middle text-[11px] font-semibold text-white">{{ __('عاجل') }}</span>
                                    @endif
                                </h3>
                                <p class="mt-1 text-sm leading-relaxed text-gray-700">{{ $step['text'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        {{-- Our part: the report joins one case with the others about the same account, and we try to act on it --}}
        <section class="card overflow-hidden border-brand-100 p-0" data-scam-together>
            <div class="bg-brand-50 p-6">
                <h2 class="text-xl font-bold text-brand-700">{{ __('لست وحدك. بلّغ وساعد غيرك') }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-brand-700">
                    {{ __('بلاغك يُضاف إلى ملف واحد مع بلاغات الآخرين عن نفس الحساب أو المحفظة أو الوسيط. بلاغ واحد قد لا يلتفت إليه أحد، لكن ملفاً فيه بلاغات كثيرة بإثباتات أقوى بكثير.') }}
                </p>
            </div>
            <div class="grid gap-4 p-6 md:grid-cols-3">
                @foreach ([
                    ['📁', __('نجمع البلاغات'), __('كل بلاغ مؤكد يُضاف لملف الجهة نفسها، وترى كم بلاغاً فيه.')],
                    ['📣', __('نحاول التحرك'), __('عندما يكتمل الملف نرفعه إلى تيليجرام والجهة الرقابية ومنصة التحويل، وننشر تحذيراً.')],
                    ['🧾', __('نعطيك نسخة'), __('ملف جماعي بدون أسماء تقدّمه مع شكواك للبنك أو الشرطة.')],
                ] as [$icon, $title, $text])
                    <div>
                        <div class="text-2xl" aria-hidden="true">{{ $icon }}</div>
                        <h3 class="mt-1 font-bold">{{ $title }}</h3>
                        <p class="mt-1 text-sm leading-relaxed text-gray-600">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-3 border-t border-gray-100 p-6">
                <a href="{{ $reportUrl }}" class="btn-primary" data-scam-report-link>{{ __('بلّغ عمّا حدث معك') }}</a>
                @auth
                    <a href="{{ route('scam-reports.index') }}" class="btn-outline">{{ __('بلاغاتي') }}</a>
                @endauth
                <p class="w-full text-xs text-gray-500">{{ __('يحتاج البلاغ تسجيل دخول حتى نتواصل معك ونخبرك بما يحدث في الملف. إثباتاتك لا تُنشر، ولا يراها إلا فريق المراجعة.') }}</p>
            </div>
        </section>

        <section class="card p-6">
            <h2 class="font-bold">{{ __('قبل أن تتعامل مع أي قناة مرة أخرى') }}</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('channel-check') }}" class="btn-outline">{{ __('افحص قناة توصيات') }}</a>
                <a href="{{ route('account-check') }}" class="btn-outline">{{ __('هل هذا الحساب حقيقي؟') }}</a>
            </div>
        </section>

        <p class="rounded-xl bg-gray-100 p-4 text-xs leading-relaxed text-gray-600" data-scam-disclaimer>
            {{ __('لا نعدك باسترجاع أموالك، ولا نطلب منك أي مبلغ في أي مرحلة. هذه نصائح عامة وليست استشارة قانونية. أي شخص يتواصل معك باسم PipScopes ويطلب مالاً فهو منتحل.') }}
        </p>
    </div>
</x-layouts.app>
