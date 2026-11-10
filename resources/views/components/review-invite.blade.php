{{-- "Which channel did you try?" (opened by [data-open-dialog="review-picker"]), and the small card that invites to it. Behaviour: resources/js/review-invite.js --}}
@php
    $showCard = \App\Support\ReviewInvite::showCard();
    $needed = $showCard || (request()->routeIs('home') && \App\Support\ReviewInvite::available());
@endphp
@if ($needed)
    <dialog id="review-picker" class="mx-auto mt-16 w-[min(32rem,calc(100vw-2rem))] rounded-2xl p-0 text-ink shadow-2xl backdrop:bg-black/50"
            aria-label="{{ __('اختر القناة التي جرّبتها') }}" data-review-picker
            data-suggest-url="{{ route('search.suggest') }}" data-rate-label="{{ __('قيّم') }}">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
            <h2 class="font-bold">{{ __('اختر القناة التي جرّبتها') }}</h2>
            <x-sheet-close data-close-dialog />
        </div>
        <div class="p-4">
            <input type="search" class="input" dir="auto" autocomplete="off" autofocus data-review-picker-search
                   placeholder="{{ __('ابحث باسم القناة أو رابط التيليجرام…') }}" aria-label="{{ __('بحث') }}">

            <ul class="mt-3 max-h-[50vh] divide-y divide-gray-100 overflow-y-auto" data-review-picker-list></ul>
            <p class="hidden py-4 text-center text-sm text-gray-500" data-review-picker-empty>{{ __('لم نجد هذه القناة.') }}</p>
        </div>
        <div class="border-t border-gray-100 px-5 py-3 text-center text-sm text-gray-600">
            {{ __('قناتك غير موجودة؟') }}
            <a href="{{ route('submissions.create') }}" class="font-semibold text-brand-700 hover:underline" data-track="add_channel_click" data-track-params='{"location":"review_picker"}'>{{ __('أضفها الآن') }}</a>
        </div>
    </dialog>

    @if ($showCard)
        <aside class="fixed inset-x-4 bottom-4 z-40 mx-auto hidden max-w-sm rounded-2xl bg-white p-4 text-ink shadow-2xl ring-1 ring-black/10 sm:end-4 sm:start-auto sm:mx-0 sm:w-96" data-review-invite aria-label="{{ __('شاركنا تجربتك') }}">
            <div class="flex items-start gap-3">
                <span class="text-2xl leading-none" aria-hidden="true">👋</span>
                <div class="min-w-0 flex-1">
                    <p class="font-bold">{{ __('هل اشتركت في قناة توصيات من قبل؟') }}</p>
                    <p class="mt-0.5 text-sm text-gray-600">{{ __('شاركنا تجربتك في دقيقة، حتى لو كانت سيئة.') }}</p>
                    <a href="{{ route('providers.index') }}" class="btn-primary mt-3 px-5 py-2 text-sm" data-open-dialog="review-picker"
                       data-track="review_invite_click" data-track-params='{"location":"card"}'>{{ __('قيّم تجربتك') }}</a>
                </div>
                <button type="button" class="shrink-0 px-1 text-xl leading-none text-gray-400 hover:text-gray-700" data-review-invite-close aria-label="{{ __('إغلاق') }}">&times;</button>
            </div>
        </aside>
    @endif
@endif
