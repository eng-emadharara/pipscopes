{{-- "Check a channel" (ChannelCheckController / App\Support\ChannelReport). The URL carries the channel, so a report can be shared. --}}
@php
    $channel = $report['channel'] ?? null;
    $provider = $report['provider'] ?? null;
    // Our long-term indicators (deletions) join the instant ones when we already follow the channel
    $cards = ($report['cards'] ?? []) + array_intersect_key($report['followed'] ?? [], ['deleted' => true]);
@endphp
<x-layouts.app :title="$channel ? __('فحص قناة :name', ['name' => $channel['title']]) : __('افحص قناة توصيات قبل أن تشترك')"
               :description="__('الصق رابط أي قناة توصيات على تيليجرام واحصل فوراً على تقرير مجاني: نسبة التفاعل الحقيقية، النشاط، والمنشورات المعدّلة.')">
    <section class="bg-ink text-white">
        <div class="mx-auto max-w-2xl px-4 py-10 text-center md:py-14">
            <h1 class="text-2xl font-bold md:text-4xl">{{ __('افحص قناة توصيات قبل أن تشترك') }}</h1>
            <p class="mx-auto mt-3 max-w-xl text-sm text-gray-300 md:text-base">{{ __('الصق رابط أي قناة على تيليجرام، ونعرض لك خلال ثوانٍ ما تقوله أرقامها: هل جمهورها حقيقي؟ هل ما زالت نشطة؟') }}</p>

            <form method="GET" action="{{ route('channel-check') }}" class="mx-auto mt-6 flex max-w-xl items-center rounded-full bg-white p-1.5 shadow-lg shadow-black/30" data-channel-check-form>
                <x-ui-icon name="telegram" class="mx-3 h-5 w-5 shrink-0 text-sky-500" />
                <input type="text" name="channel" value="{{ $input }}" dir="ltr" required maxlength="255" autocomplete="off"
                       placeholder="t.me/channel {{ __('أو') }} @channel" aria-label="{{ __('رابط القناة') }}"
                       class="min-w-0 flex-1 bg-transparent py-2 text-sm text-ink placeholder-gray-400 focus:outline-none">
                <button class="btn-primary shrink-0">{{ __('افحص') }}</button>
            </form>
        </div>
    </section>

    <div @class(['mx-auto px-4 py-8', ($report['status'] ?? null) === 'report' ? 'max-w-5xl' : 'max-w-2xl']) @if ($report) data-channel-report="{{ $report['status'] }}" @endif>
        @if (! $report)
            <ul class="grid gap-3 text-sm text-gray-600 sm:grid-cols-3">
                @foreach ([__('نسبة التفاعل: هل المشتركون حقيقيون؟'), __('النشاط: متى نشرت آخر مرة وكم تنشر؟'), __('التعديلات: كم منشوراً عُدّل بعد نشره؟')] as $point)
                    <li class="card flex items-start gap-2 p-4"><x-ui-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-brand-500" /> {{ $point }}</li>
                @endforeach
            </ul>

        @elseif ($report['status'] === 'impersonator')
            <div class="card border-red-200 bg-red-50 p-6 text-red-800" role="alert">
                <h2 class="flex items-center gap-2 text-lg font-bold"><x-ui-icon name="alert" class="h-5 w-5" /> {{ __('تحذير: هذا حساب منتحل معروف') }}</h2>
                <p class="mt-2 text-sm">{{ __('هذا الحساب ينتحل اسم قناة أخرى. لا ترسل له أموالاً ولا تشترك من خلاله.') }}</p>
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($report['impersonators'] as $impersonator)
                        <li>{{ __('ينتحل:') }} <a href="{{ route('providers.show', $impersonator->provider) }}" class="font-semibold underline"><bdi>{{ $impersonator->provider->name }}</bdi></a></li>
                    @endforeach
                </ul>
            </div>

        @elseif ($report['status'] !== 'report')
            <div class="card flex items-start gap-2 border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
                <x-ui-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" /> {{ $report['message'] }}
            </div>

        @else
            {{-- Wide screens: the report on one side, what to do next beside it (sticky). Phones: the next step right under the channel --}}
            @php($readyAt = $provider && ($report['followed']['deleted']['level'] ?? null) === 'pending' ? \App\Support\TelegramInsights::deletedReadyAt($provider) : null)
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start">
                {{-- The channel itself --}}
                <section class="card p-5 lg:col-start-1">
                    <div class="flex items-start gap-4">
                        @if ($provider)
                            <x-avatar :provider="$provider" size="h-16 w-16 text-2xl" />
                        @elseif ($channel['photo_url'])
                            <img src="{{ $channel['photo_url'] }}" alt="" class="h-16 w-16 shrink-0 rounded-2xl border border-gray-200 object-cover" referrerpolicy="no-referrer">
                        @else
                            <span class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-sky-50 text-sky-500"><x-ui-icon name="telegram" class="h-8 w-8" /></span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <h2 class="break-words text-lg font-bold">
                                <bdi>{{ $channel['title'] }}</bdi>
                                @if ($channel['verified'])
                                    <x-ui-icon name="check" class="inline h-4 w-4 text-sky-500" :title="__('موثّقة من تيليجرام')" />
                                @endif
                            </h2>
                            <a href="{{ $channel['url'] }}" target="_blank" rel="noopener nofollow" class="text-sm text-sky-700 hover:underline" dir="ltr">@<span>{{ $channel['username'] }}</span></a>
                            @if ($channel['subscribers'])
                                <p class="mt-1 text-sm text-gray-600">{{ trans_choice(':count مشترك', $channel['subscribers'], ['count' => number_format($channel['subscribers'])]) }}</p>
                            @endif
                        </div>
                    </div>
                    @if ($channel['description'])
                        <p class="mt-4 line-clamp-3 text-sm leading-relaxed text-gray-600" dir="auto">{{ $channel['description'] }}</p>
                    @endif
                </section>

                {{-- The next step: follow it on PipScopes (listed) or add it for monitoring --}}
                <aside class="lg:sticky lg:top-4 lg:col-start-2 lg:row-span-2 lg:row-start-1">
                    @if ($provider)
                        <section class="card overflow-hidden border-brand-100 p-0" data-report-listed>
                            <div class="bg-brand-50 p-5">
                                <h2 class="flex items-center gap-2 font-bold text-brand-700"><x-ui-icon name="check" class="h-5 w-5" /> {{ __('القناة موجودة على PipScopes') }}</h2>
                                @if ($provider->reviews_count)
                                    <p class="mt-1 text-sm text-brand-700">★ {{ number_format((float) $provider->rating_avg, 1) }} · {{ trans_choice(':count تقييم', $provider->reviews_count) }}</p>
                                @endif
                            </div>
                            <div class="space-y-2 p-5">
                                <a href="{{ route('providers.show', $provider) }}" class="btn-primary w-full">{{ __('صفحة القناة والتقييمات') }}</a>
                                <a href="{{ route('reviews.create', $provider) }}" class="btn-outline w-full">{{ __('اكتب تجربتك') }}</a>
                            </div>
                            {{-- Followed, but the deletions aren't counted yet: when to come back --}}
                            @if ($readyAt)
                                <div class="flex items-start gap-3 border-t border-sky-100 bg-sky-50 p-5 text-sky-900" data-report-recheck>
                                    <x-ui-icon name="calendar" class="mt-0.5 h-5 w-5 shrink-0 text-sky-600" />
                                    <div>
                                        <h3 class="font-bold">{{ __('نتابع هذه القناة يومياً') }}</h3>
                                        <p class="mt-1 text-sm leading-relaxed">
                                            {{ __('عدد المنشورات المحذوفة يجهز يوم :date، أي بعد :n يوماً. ارجع إلى هذا الرابط حينها لترى النتيجة.', [
                                                'date' => $readyAt->translatedFormat('l j F'),
                                                'n' => max(1, (int) ceil(now()->diffInDays($readyAt))),
                                            ]) }}
                                        </p>
                                    </div>
                                </div>
                            @endif
                        </section>
                    @else
                        <section class="card overflow-hidden border-brand-100 p-0" data-report-monitoring>
                            <div class="bg-brand-50 p-5">
                                <h2 class="flex items-center gap-2 font-bold text-brand-700"><span aria-hidden="true">🔒</span> {{ __('تحتاج متابعة مستمرة') }}</h2>
                                <ul class="mt-3 space-y-1.5 text-sm text-gray-700">
                                    <li>• {{ __('المنشورات المحذوفة: هل تحذف القناة صفقاتها الخاسرة؟') }}</li>
                                    <li>• {{ __('تغيّر عدد المشتركين مع الوقت، والقفزات المفاجئة') }}</li>
                                    <li>• {{ __('تقييمات المتداولين الذين جرّبوها') }}</li>
                                </ul>
                            </div>
                            <div class="p-5">
                                @if ($report['pending'])
                                    <p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900" data-report-pending>{{ __('هذه القناة قيد المراجعة الآن. نبدأ متابعتها يومياً فور إضافتها، وتجهز النتائج بعد أسبوعين من الإضافة، فارجع إلى هذا الرابط حينها.') }}</p>
                                @else
                                    <form method="POST" action="{{ route('submissions.store') }}">
                                        @csrf
                                        <input type="hidden" name="telegram_url" value="{{ $report['url'] }}">
                                        @error('telegram_url') <p class="error mb-3">{{ $message }}</p> @enderror
                                        <button class="btn-primary w-full" data-track="add_channel_click" data-track-params='{"location":"channel_check"}'>
                                            <x-ui-icon name="plus" class="h-4 w-4" /> {{ __('أضف القناة للمراقبة') }}
                                        </button>
                                        <p class="mt-2 text-xs leading-relaxed text-gray-500">{{ __('مجاناً وبضغطة واحدة. بعد مراجعتها نتابعها يومياً، وتجهز النتائج بعد أسبوعين من إضافتها على هذا الرابط نفسه.') }}</p>
                                    </form>
                                @endif
                            </div>
                        </section>
                    @endif
                </aside>

                {{-- What the numbers say --}}
                @if ($cards)
                    <section class="lg:col-start-1">
                        <h2 class="mb-3 font-bold">{{ __('ماذا تقول أرقامها؟') }}</h2>
                        <ul class="card divide-y divide-gray-100 p-0" data-report-cards>
                            @foreach ($cards as $card)
                                @include('channel-check._row', ['card' => $card])
                            @endforeach
                        </ul>
                    </section>
                @elseif (! $report['preview'])
                    <p class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 lg:col-start-1" data-report-no-preview>
                        {{ __('صاحب القناة أغلق معاينة المنشورات للعامة، لذلك لا يمكن قياس التفاعل والنشاط. نعرض بيانات القناة الأساسية فقط.') }}
                    </p>
                @endif
            </div>

            <p class="mt-6 text-center text-xs leading-relaxed text-gray-400">{{ __('التقرير محسوب تلقائياً من بيانات القناة العامة على تيليجرام، وهو ليس نصيحة مالية.') }}</p>
        @endif
    </div>
</x-layouts.app>
