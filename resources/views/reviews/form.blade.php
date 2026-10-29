@php
    $selectedPros = old('pros', $review->pros ?? []);
    $selectedCons = old('cons', $review->cons ?? []);
    $showName = (bool) old('show_name', $review->show_name);
    $ratingWords = [1 => __('سيئة جداً'), 2 => __('سيئة'), 3 => __('مقبولة'), 4 => __('جيدة'), 5 => __('ممتازة')];
    $criteria = array_keys(__('trust.criteria'));
@endphp
<x-layouts.app :title="__('تقييم :name', ['name' => $provider->name])">
    <div class="mx-auto max-w-2xl px-4 py-10">
        <a href="{{ route('providers.show', $provider) }}" class="mb-6 flex items-center gap-3">
            <x-avatar :provider="$provider" size="h-12 w-12 text-lg" />
            <div class="min-w-0">
                <div class="break-words font-bold"><bdi>{{ $provider->name }}</bdi></div>
                <div class="text-sm text-gray-500">{{ $provider->tr('service_type') }}</div>
            </div>
        </a>

        <h1 class="mb-1 text-2xl font-bold">{{ $review->exists ? __('تعديل تقييمك') : __('شارك تجربتك') }}</h1>
        <p class="mb-6 text-sm text-gray-500">{{ __('النجوم وسطران عن تجربتك يكفيان. التفاصيل الإضافية اختيارية.') }}</p>

        {{-- .review-form shows the pros and/or cons that fit the chosen stars (resources/css/app.css) --}}
        <form method="POST" action="{{ route('reviews.store', $provider) }}" class="review-form card space-y-7 p-6">
            @csrf

            {{-- Overall rating; the word beside the stars follows the choice --}}
            <div class="rating-field">
                <span class="label">{{ __('تقييمك العام') }} <span class="text-red-500">*</span></span>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div class="star-picker">
                        @for ($i = 5; $i >= 1; $i--)
                            <input type="radio" id="rating-{{ $i }}" name="rating" value="{{ $i }}" @checked((int) old('rating', $review->rating) === $i) required>
                            <label for="rating-{{ $i }}" title="{{ $ratingWords[$i] }}"><x-ui-icon name="star" /></label>
                        @endfor
                    </div>
                    <span class="rating-word text-sm text-gray-400" data-word="0">{{ __('اختر من 1 إلى 5 نجوم') }}</span>
                    @foreach ($ratingWords as $i => $word)
                        <span class="rating-word text-base font-bold" data-word="{{ $i }}">{{ $word }}</span>
                    @endforeach
                </div>
                @error('rating') <p class="error">{{ $message }}</p> @enderror
            </div>

            {{-- 1-3 stars: what went wrong; 3-5 stars: what went well. Ticked chips stay visible whatever the stars. --}}
            <div class="reasons space-y-5">
                @include('reviews._reason-chips', ['type' => 'cons', 'selected' => $selectedCons])
                @include('reviews._reason-chips', ['type' => 'pros', 'selected' => $selectedPros])
            </div>

            {{-- The detailed criteria feed the channel analysis, so they sit near the top --}}
            <div>
                <span class="label">{{ __('قيّم حسب المعايير') }} <span class="font-normal text-gray-500">({{ __('اختياري') }})</span></span>
                <div class="mt-2 divide-y divide-gray-100 rounded-2xl border border-gray-200 px-4">
                    @foreach (__('trust.criteria') as $field => $label)
                        <div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <span class="text-sm">{{ $label }}</span>
                            <div class="star-picker" style="--s: 28px">
                                @for ($i = 5; $i >= 1; $i--)
                                    <input type="radio" id="{{ $field }}-{{ $i }}" name="{{ $field }}" value="{{ $i }}" @checked((int) old($field, $review->{$field}) === $i)>
                                    <label for="{{ $field }}-{{ $i }}" title="{{ $ratingWords[$i] }}"><x-ui-icon name="star" /></label>
                                @endfor
                            </div>
                        </div>
                    @endforeach
                </div>
                @foreach ($criteria as $field)
                    @error($field) <p class="error">{{ $message }}</p> @enderror
                @endforeach
            </div>

            <div>
                <label class="label" for="body">{{ __('تجربتك') }} <span class="text-red-500">*</span></label>
                <textarea id="body" name="body" dir="auto" rows="6" class="input" minlength="20" maxlength="3000" data-count="20"
                          placeholder="{{ __('كيف كانت النتائج؟ هل التزموا بما وعدوا؟ كيف كان التعامل؟') }}" required>{{ old('body', $review->body) }}</textarea>
                <div class="mt-1 flex items-start justify-between gap-3 text-xs">
                    <div>@error('body') <p class="error !mt-0">{{ $message }}</p> @enderror</div>
                    {{-- Filled in by resources/js/ui.js --}}
                    <span class="shrink-0 text-gray-500" data-count-for="body"
                          data-short="{{ __('متبقٍّ للحد الأدنى: :n') }}" data-ok="{{ __(':n / 3000') }}"></span>
                </div>
            </div>

            @include('reviews._duration-chips', ['selected' => old('experience_duration', $review->experience_duration)])

            {{-- How the name shows --}}
            <fieldset>
                <legend class="label">{{ __('كيف يظهر تقييمك؟') }}</legend>
                <div class="grid grid-cols-2 gap-3">
                    <label class="choice-card">
                        <input type="radio" name="show_name" value="1" class="sr-only" @checked($showName)>
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-500 text-sm font-bold text-white">{{ auth()->user()->initials() }}</span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold">{{ __('باسمي') }}</span>
                            <span class="block truncate text-xs text-gray-500"><bdi>{{ auth()->user()->name }}</bdi></span>
                        </span>
                    </label>
                    <label class="choice-card">
                        <input type="radio" name="show_name" value="0" class="sr-only" @checked(! $showName)>
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gray-200 text-sm font-bold text-gray-500">?</span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold">{{ __('مجهول') }}</span>
                            <span class="block text-xs text-gray-500">{{ __('مستخدم مجهول') }}</span>
                        </span>
                    </label>
                </div>
                <p class="mt-2 text-xs text-gray-500">{{ __('في الحالتين لا يرى صاحب القناة بريدك، والإدارة فقط تعرف هويتك.') }}</p>
            </fieldset>

            <div class="flex flex-wrap items-center gap-3">
                <button class="btn-primary">{{ $review->exists ? __('حفظ التعديلات') : __('نشر التقييم') }}</button>
                <a href="{{ route('providers.show', $provider) }}" class="btn-outline">{{ __('إلغاء') }}</a>
            </div>
        </form>

        @if ($review->exists)
            <form method="POST" action="{{ route('reviews.destroy', $provider) }}" class="mt-4" onsubmit="return confirm(@js(__('هل تريد حذف تقييمك نهائياً؟')))">
                @csrf @method('DELETE')
                <button class="text-sm text-red-600 hover:underline">{{ __('حذف تقييمي') }}</button>
            </form>
        @endif
    </div>
</x-layouts.app>
