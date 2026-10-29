@php
    $selectedPros = old('pros', $review->pros ?? []);
    $selectedCons = old('cons', $review->cons ?? []);
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

        <h1 class="mb-6 text-2xl font-bold">{{ $review->exists ? __('تعديل تقييمك') : __('شارك تجربتك') }}</h1>

        <form method="POST" action="{{ route('reviews.store', $provider) }}" class="card space-y-7 p-6">
            @csrf

            {{-- Overall rating --}}
            <div>
                <span class="label">{{ __('تقييمك العام') }} <span class="text-red-500">*</span></span>
                <div class="star-picker">
                    @for ($i = 5; $i >= 1; $i--)
                        <input type="radio" id="rating-{{ $i }}" name="rating" value="{{ $i }}" @checked((int) old('rating', $review->rating) === $i) required>
                        <label for="rating-{{ $i }}" title="{{ trans_choice(':count نجوم', $i) }}"><x-ui-icon name="star" /></label>
                    @endfor
                </div>
                @error('rating') <p class="error">{{ $message }}</p> @enderror
            </div>

            {{-- Criteria --}}
            <div>
                <span class="label">{{ __('قيّم حسب المعايير') }} <span class="font-normal text-gray-500">({{ __('اختياري، يساعد في تحليل التقييم') }})</span></span>
                <div class="mt-2 space-y-3">
                    @foreach (__('trust.criteria') as $field => $label)
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-sm">{{ $label }}</span>
                            <div class="star-picker" style="--s: 28px">
                                @for ($i = 5; $i >= 1; $i--)
                                    <input type="radio" id="{{ $field }}-{{ $i }}" name="{{ $field }}" value="{{ $i }}" @checked((int) old($field, $review->{$field}) === $i)>
                                    <label for="{{ $field }}-{{ $i }}" title="{{ $i }}"><x-ui-icon name="star" /></label>
                                @endfor
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Reasons --}}
            <div>
                <span class="label">{{ __('ما الذي أعجبك؟') }}</span>
                <div class="flex flex-wrap gap-2">
                    @foreach (__('trust.pros') as $key => $label)
                        <label class="chip chip-pro">
                            <input type="checkbox" name="pros[]" value="{{ $key }}" class="sr-only" @checked(in_array($key, $selectedPros))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <span class="label">{{ __('ما الذي لم يعجبك؟') }}</span>
                <div class="flex flex-wrap gap-2">
                    @foreach (__('trust.cons') as $key => $label)
                        <label class="chip chip-con">
                            <input type="checkbox" name="cons[]" value="{{ $key }}" class="sr-only" @checked(in_array($key, $selectedCons))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="label" for="experience_duration">{{ __('مدة تجربتك مع الخدمة') }}</label>
                <select id="experience_duration" name="experience_duration" class="input">
                    <option value="">{{ __('— اختر —') }}</option>
                    @foreach (__('trust.experience_durations') as $key => $label)
                        <option value="{{ $key }}" @selected(old('experience_duration', $review->experience_duration) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="body">{{ __('تفاصيل تجربتك') }} <span class="text-red-500">*</span></label>
                <textarea id="body" name="body" dir="auto" rows="6" class="input" minlength="20" maxlength="3000" placeholder="{{ __('كيف كانت النتائج؟ هل التزموا بما وعدوا؟ كيف كان التعامل؟') }}" required>{{ old('body', $review->body) }}</textarea>
                @error('body') <p class="error">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-start gap-3 rounded-xl bg-gray-50 p-4">
                <input type="hidden" name="show_name" value="0">
                <input type="checkbox" name="show_name" value="1" class="mt-1 accent-brand-500" @checked(old('show_name', $review->show_name))>
                <span>
                    <span class="block text-sm font-semibold">{{ __('إظهار اسمي (:name) مع التقييم', ['name' => auth()->user()->name]) }}</span>
                    <span class="block text-xs text-gray-500">{{ __('عند إلغاء التحديد سيظهر تقييمك باسم «مستخدم مجهول». الإدارة فقط تستطيع رؤية هويتك.') }}</span>
                </span>
            </label>

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
