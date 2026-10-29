@php
    $published = $review->status === \App\Models\Review::STATUS_APPROVED;
@endphp
<x-layouts.app :title="__('شكراً على تقييمك')">
    <div class="mx-auto max-w-2xl px-4 py-10">
        <div class="card p-6 text-center md:p-8">
            <div @class(['mx-auto grid h-14 w-14 place-items-center rounded-full', 'bg-brand-50 text-brand-600' => $published, 'bg-amber-50 text-amber-600' => ! $published])>
                <x-ui-icon :name="$published ? 'check' : 'shield'" class="h-7 w-7" />
            </div>

            @if ($published)
                <h1 class="mt-4 text-2xl font-bold">{{ $edited ? __('تم حفظ تعديلات تقييمك') : __('شكراً لك! تم نشر تقييمك') }}</h1>
                <p class="mt-2 text-gray-600">{{ __('تقييمك لـ :name ظاهر الآن للجميع، ويساعد متداولين آخرين قبل أن يشتركوا.', ['name' => $provider->name]) }}</p>
            @else
                <h1 class="mt-4 text-2xl font-bold">{{ __('وصلنا تقييمك، شكراً لك') }}</h1>
                <p class="mt-2 text-gray-600">{{ __('سيظهر تقييمك خلال 24 ساعة بعد مراجعته. نراجع تقييمات الحسابات الجديدة لحماية الموقع من التقييمات الوهمية، ولا يعني ذلك أن في تقييمك مشكلة.') }}</p>
                @if (\App\Support\NotificationPrefs::channels(auth()->user(), 'review_status'))
                    <p class="mt-2 text-sm text-gray-500">{{ __('سنرسل لك إشعاراً عند نشره.') }}</p>
                @endif
            @endif

            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                @if ($published)
                    <x-share-buttons :url="$review->shareUrl()" :text="$review->shareText()" />
                @endif
                <a href="{{ route('providers.show', $provider) }}#review-{{ $review->id }}" class="btn-outline">{{ __('عرض صفحة القناة') }}</a>
                <a href="{{ route('reviews.create', $provider) }}" class="text-sm text-gray-500 hover:text-ink hover:underline">{{ __('تعديل تقييمي') }}</a>
            </div>
        </div>

        @if ($missing)
            <form method="POST" action="{{ route('reviews.details', $provider) }}" class="card mt-6 space-y-5 border-brand-100 p-6">
                @csrf @method('PATCH')
                <div>
                    <h2 class="text-lg font-bold">{{ __('أكمل تقييمك بنقرتين') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ __('هذه الإجابات تدخل في تحليل القناة الذي يقرؤه كل زائر، وتجعل تقييمك أكثر فائدة.') }}</p>
                </div>

                @if (in_array('reasons', $missing))
                    @if ($review->rating <= 3)
                        @include('reviews._reason-chips', ['type' => 'cons', 'selected' => []])
                    @endif
                    @if ($review->rating >= 3)
                        @include('reviews._reason-chips', ['type' => 'pros', 'selected' => []])
                    @endif
                @endif

                @if (in_array('duration', $missing))
                    @include('reviews._duration-chips', ['selected' => null])
                @endif

                <button class="btn-primary">{{ __('حفظ التفاصيل') }}</button>
            </form>
        @endif

        @if ($suggestions->isNotEmpty())
            <section class="mt-10">
                <h2 class="text-lg font-bold">{{ __('جرّبت قناة أخرى؟ قيّمها أيضاً') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ __('كل تقييم يجعل الصورة أوضح لغيرك.') }}</p>
                <ul class="mt-4 divide-y divide-gray-100 rounded-2xl border border-gray-200 bg-white">
                    @foreach ($suggestions as $other)
                        <li class="flex items-center gap-3 p-3">
                            <x-avatar :provider="$other" size="h-10 w-10 text-base" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-semibold"><bdi>{{ $other->name }}</bdi></div>
                                <div class="truncate text-xs text-gray-500">{{ $other->tr('service_type') }}</div>
                            </div>
                            <a href="{{ route('reviews.create', $other) }}" class="btn-outline shrink-0 px-3 py-1.5 text-sm">{{ __('قيّم') }}</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.app>
