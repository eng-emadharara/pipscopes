{{-- "Check a channel" on the homepage: the form goes straight to the report (ChannelCheckController) --}}
<section class="mx-auto mt-10 max-w-6xl px-4" id="channel-check" data-home-channel-check>
    <div class="card overflow-hidden border-brand-100 bg-gradient-to-l from-brand-50 to-white">
        <div class="flex flex-col gap-6 p-6 md:flex-row md:items-center md:p-8">
            <div class="md:w-5/12">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500 px-3 py-1 text-xs font-bold text-white">{{ __('مجاني وفوري') }}</span>
                <h2 class="mt-3 flex items-center gap-2 text-xl font-bold md:text-2xl">
                    <x-ui-icon name="telegram" class="h-6 w-6 text-sky-500" />
                    {{ __('افحص قناة توصيات قبل أن تشترك') }}
                </h2>
                <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ __('الصق رابط أي قناة على تيليجرام، حتى لو لم تكن على المنصة، ونعرض لك ما تقوله أرقامها خلال ثوانٍ.') }}</p>
            </div>

            <div class="flex-1">
                <form action="{{ route('channel-check') }}" method="GET" class="flex flex-col gap-2 sm:flex-row" data-check-form>
                    <input type="text" name="channel" required maxlength="255" dir="ltr" autocomplete="off"
                           class="input min-w-0 flex-1 text-start" placeholder="t.me/channel {{ __('أو') }} @channel" aria-label="{{ __('رابط القناة') }}">
                    <button type="submit" class="btn-primary shrink-0" data-track="channel_check_start" data-track-params='{"location":"home"}'>{{ __('افحص') }}</button>
                </form>
                <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-gray-600">
                    @foreach ([__('هل جمهورها حقيقي؟'), __('صفقات مسبقة أم ادعاءات بعد حدوثها؟'), __('هل تذكر خساراتها؟'), __('كم عمرها؟')] as $point)
                        <li class="flex items-center gap-1"><x-ui-icon name="check" class="h-3.5 w-3.5 text-brand-500" /> {{ $point }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</section>
