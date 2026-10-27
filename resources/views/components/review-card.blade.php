@props(['review', 'provider', 'voted' => false, 'reported' => false, 'managing' => false])
{{-- One review, used on the provider page and on the single-review share page --}}
<article id="review-{{ $review->id }}" class="card scroll-mt-6 p-4">
    <div class="mb-2 flex items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-2.5">
            <div class="grid h-8 w-8 shrink-0 place-items-center rounded-full {{ $review->show_name ? 'bg-gray-200' : 'bg-gray-800 text-white' }} text-sm font-bold">
                {{ $review->show_name ? mb_substr($review->displayName(), 0, 1) : '?' }}
            </div>
            <div class="min-w-0">
                <div class="truncate text-sm font-semibold">{{ $review->displayName() }}</div>
                <div class="truncate text-xs text-gray-500">
                    {{ $review->created_at->diffForHumans() }}
                    @if ($review->experience_duration)
                        · {{ __('مدة التجربة:') }} {{ __('trust.experience_durations.'.$review->experience_duration) }}
                    @endif
                </div>
            </div>
        </div>
        <x-stars :rating="$review->rating" size="sm" />
    </div>
    <h3 dir="auto" class="text-start text-[15px] font-bold">{{ $review->title }}</h3>
    {{-- Long reviews show 4 lines first; the button (resources/js/ui.js) appears only when text is cut --}}
    <div>
        <p dir="auto" class="mt-1 line-clamp-4 whitespace-pre-line text-start text-sm leading-relaxed text-gray-700" data-clamp="line-clamp-4">{{ $review->body }}</p>
        <button type="button" class="mt-1 hidden text-xs font-semibold text-brand-700 hover:underline" data-clamp-toggle>{{ __('عرض المزيد') }}</button>
    </div>

    @if ($review->prosLabels() || $review->consLabels())
        <div class="mt-2.5 flex flex-wrap gap-1 text-xs">
            @foreach ($review->prosLabels() as $label)
                <span class="rounded-full bg-brand-50 px-2 py-0.5 text-brand-700">+ {{ $label }}</span>
            @endforeach
            @foreach ($review->consLabels() as $label)
                <span class="rounded-full bg-red-50 px-2 py-0.5 text-red-700">− {{ $label }}</span>
            @endforeach
        </div>
    @endif

    @if ($review->provider_reply)
        <div class="mt-3 rounded-e-lg border-s-4 {{ $review->provider_reply_owner_id ? 'border-sky-500 bg-sky-50/60' : 'border-gray-300 bg-gray-50' }} px-3 py-2.5 text-[13px]">
            <div class="mb-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                <span class="font-semibold">{{ __('رد :name', ['name' => $provider->name]) }}</span>
                {{-- Written by the channel owner in the owner portal, not by the platform --}}
                @if ($review->provider_reply_owner_id)
                    <span class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-800">
                        <x-ui-icon name="check" class="h-3.5 w-3.5" /> {{ __('رد رسمي من صاحب القناة') }}
                    </span>
                @endif
                @if ($review->provider_replied_at)
                    <span class="text-xs text-gray-500">{{ $review->provider_replied_at->diffForHumans() }}</span>
                @endif
            </div>
            <p dir="auto" class="whitespace-pre-line text-start text-gray-700">{{ $review->provider_reply }}</p>
        </div>
    @endif

    @if ($managing)
        @include('owners._reply-form', ['review' => $review, 'provider' => $provider])
    @endif

    <x-review-feedback :review="$review" :provider="$provider" :managing="$managing" :voted="$voted" :reported="$reported" />
</article>
