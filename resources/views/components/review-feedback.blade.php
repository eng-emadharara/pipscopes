@props(['review', 'provider' => null, 'voted' => false, 'reported' => false, 'managing' => false])
@php
    $isOwn = auth()->check() && auth()->id() === $review->user_id;
    // A signed-in channel owner never votes, and only reports reviews of their own channel
    $owner = \App\Support\OwnerPortal::owner();
    $canReport = ! $owner || $owner->provider_id === $review->provider_id;
@endphp
{{-- Works without JS (plain form posts); resources/js/review-feedback.js upgrades it to in-place updates --}}
<div class="mt-3 flex flex-wrap items-start justify-between gap-3 border-t border-gray-100 pt-2.5 text-sm" data-review-feedback>
    @if ($isOwn)
        <span class="text-gray-500">
            {{ trans_choice(':count شخص وجد تقييمك مفيداً', $review->helpful_count) }}
        </span>
    @elseif ($managing || $owner)
        {{-- Channel owners don't vote on reviews (any channel); they still see the count --}}
        <span class="py-1.5 text-gray-500">{{ $review->helpful_count ? trans_choice(':count شخص وجد التقييم مفيداً', $review->helpful_count) : '' }}</span>
    @else
        <form method="POST" action="{{ route('reviews.helpful', $review) }}" data-helpful-form>
            @csrf
            <button type="submit" aria-pressed="{{ $voted ? 'true' : 'false' }}"
                    class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 transition aria-pressed:border-brand-500 aria-pressed:bg-brand-50 aria-pressed:text-brand-700 border-gray-300 text-gray-700 hover:border-gray-500">
                <svg viewBox="0 0 24 24" class="h-4 w-4 fill-current" aria-hidden="true"><path d="M2 21h4V9H2v12zm20-11a2 2 0 00-2-2h-6.3l1-4.6v-.3c0-.4-.2-.8-.4-1.1L13.2 1 6.6 7.6c-.4.3-.6.8-.6 1.4v10c0 1.1.9 2 2 2h9c.8 0 1.5-.5 1.8-1.2l3-7.1c.1-.2.2-.5.2-.7v-2z"/></svg>
                <span>{{ __('مفيد') }}</span>
                <span class="font-semibold tabular-nums" data-helpful-count>{{ $review->helpful_count ?: '' }}</span>
            </button>
        </form>
    @endif

    <div class="flex flex-wrap items-start gap-4">
        {{-- Authors can share their own review too --}}
        <x-share-buttons :url="$review->shareUrl()" :text="$review->shareText()" variant="link" />

        @if ($isOwn || ! $canReport)
            {{-- No reporting your own review, nor (as an owner) other channels' reviews --}}
        @elseif ($reported)
            <span class="py-1.5 text-gray-500">{{ __('تم الإبلاغ') }}</span>
        @else
            <details class="group" data-report>
                <summary class="cursor-pointer list-none py-1.5 text-gray-500 hover:text-red-600">
                    <svg viewBox="0 0 24 24" class="inline h-4 w-4 fill-current" aria-hidden="true"><path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6z"/></svg>
                    {{ __('إبلاغ') }}
                </summary>
                <form method="POST" action="{{ route('reviews.report', $review) }}" class="mt-2 w-full max-w-sm space-y-3 rounded-xl border border-gray-200 bg-gray-50 p-4" data-report-form>
                    @csrf
                    <fieldset>
                        <legend class="label">{{ __('سبب البلاغ') }}</legend>
                        <div class="space-y-1.5">
                            @foreach (__('trust.report_reasons') as $key => $label)
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="reason" value="{{ $key }}" class="accent-red-600" required>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <textarea name="details" rows="2" maxlength="1000" dir="auto" class="input" placeholder="{{ __('تفاصيل إضافية (اختياري)') }}"></textarea>
                    <button type="submit" class="btn bg-red-600 px-4 py-2 text-white hover:bg-red-700">{{ __('إرسال البلاغ') }}</button>
                </form>
            </details>
        @endif
    </div>
</div>
