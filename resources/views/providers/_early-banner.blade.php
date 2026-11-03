{{-- Pre-launch: why this one channel is open. The date is shown in the visitor's own time zone (resources/js/ui.js) --}}
@if (\App\Support\Launch::earlyOpen($provider))
    @php
        $launchAt = \App\Support\Launch::launchAt();
        $launchDate = $launchAt?->isFuture()
            ? '<time data-local-date datetime="'.$launchAt->toIso8601String().'" class="font-bold">'
                .e($launchAt->copy()->setTimezone(\App\Models\Setting::get('launch_timezone') ?: config('app.timezone'))->translatedFormat('l j F Y، g:i A')).'</time>'
            : null;
    @endphp
    <div class="border-b border-amber-200 bg-amber-50 text-amber-950" data-early-banner>
        <p class="mx-auto max-w-6xl px-4 py-2.5 text-center text-sm font-medium leading-relaxed">
            {!! $launchDate
                ? __('PipScopes قيد الإطلاق، وموعد الإطلاق :date. تم فتح هذه القناة مبكراً بموافقة صاحبها، وستستطيع تصفح جميع القنوات بعد الإطلاق. كن من أوائل المقيّمين، وتجربتك رح تساعد غيرك يختار صح.', ['date' => $launchDate])
                : e(__('PipScopes قيد الإطلاق. تم فتح هذه القناة مبكراً بموافقة صاحبها، وستستطيع تصفح جميع القنوات بعد الإطلاق. كن من أوائل المقيّمين، وتجربتك رح تساعد غيرك يختار صح.')) !!}
        </p>
    </div>
@endif
