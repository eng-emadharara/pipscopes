{{-- The free checks on the homepage, side by side from lg up: check a channel (wider) and compare two.
     Both forms go straight to their page (ChannelCheckController, ChannelCompareController) --}}
<section class="mx-auto mt-10 grid max-w-6xl gap-5 px-4 lg:grid-cols-5" id="channel-check">
    <div class="card flex flex-col border-brand-100 bg-gradient-to-l from-brand-50 to-white p-6 md:p-8 lg:col-span-3" data-home-channel-check>
        <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-brand-500 px-3 py-1 text-xs font-bold text-white">{{ __('مجاني وفوري') }}</span>
        <h2 class="mt-3 flex items-center gap-2 text-xl font-bold md:text-2xl">
            <x-ui-icon name="telegram" class="h-6 w-6 text-sky-500" />
            {{ __('افحص قناة توصيات قبل أن تشترك') }}
        </h2>
        <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ __('الصق رابط أي قناة على تيليجرام، حتى لو لم تكن على المنصة، ونعرض لك ما تقوله أرقامها خلال ثوانٍ.') }}</p>

        <form action="{{ route('channel-check') }}" method="GET" class="mt-5 flex flex-col gap-2 sm:flex-row" data-check-form>
            <input type="text" name="channel" required maxlength="255" dir="ltr" autocomplete="off"
                   class="input min-w-0 flex-1 text-start" placeholder="t.me/channel {{ __('أو') }} @channel" aria-label="{{ __('رابط القناة') }}">
            <button type="submit" class="btn-primary shrink-0" data-track="channel_check_start" data-track-params='{"location":"home"}'>{{ __('افحص') }}</button>
        </form>
        <ul class="mt-auto flex flex-wrap gap-x-4 gap-y-1.5 pt-4 text-xs text-gray-600">
            @foreach ([__('هل جمهورها حقيقي؟'), __('صفقات مسبقة أم ادعاءات بعد حدوثها؟'), __('هل تذكر خساراتها؟'), __('كم عمرها؟')] as $point)
                <li class="flex items-center gap-1"><x-ui-icon name="check" class="h-3.5 w-3.5 text-brand-500" /> {{ $point }}</li>
            @endforeach
        </ul>
    </div>

    <div class="card flex flex-col p-6 md:p-8 lg:col-span-2" id="channel-compare" data-home-channel-compare>
        <h2 class="flex items-center gap-2 text-xl font-bold md:text-2xl"><span aria-hidden="true">⚖️</span> {{ __('محتار بين قناتين؟') }}</h2>
        <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ __('ضعهما جنباً إلى جنب: التفاعل الحقيقي، الصفقات مقابل الادعاءات، الخسائر، والعمر.') }}</p>

        <form action="{{ route('channel-compare') }}" method="GET" class="mt-5 flex flex-col gap-2" data-check-form>
            <input type="text" name="a" required maxlength="255" dir="ltr" autocomplete="off" class="input text-start" placeholder="@channel1" aria-label="{{ __('القناة الأولى') }}">
            <span class="text-center text-xs font-bold text-gray-400" aria-hidden="true">{{ __('مقابل') }}</span>
            <input type="text" name="b" required maxlength="255" dir="ltr" autocomplete="off" class="input text-start" placeholder="@channel2" aria-label="{{ __('القناة الثانية') }}">
            <button type="submit" class="btn-primary mt-1" data-track="compare_start" data-track-params='{"location":"home"}'>{{ __('قارن') }}</button>
        </form>
    </div>
</section>
