{{--
    Owner mode: "edit channel details" window on the provider page.
    Name, categories and verification are shown read-only; every saved change is logged for admins.
--}}
@php
    $bag = $errors->getBag('ownerEdit');
    $locales = config('trust.locales');
    $baseLocale = array_key_first($locales);
    $selectedMarkets = old('market_ids', $provider->markets->pluck('id')->all());
    $extraLinks = old('extra_links', $provider->extra_links ?? []);
    $err = fn (string $field) => $bag->has($field) ? '<p class="error">'.e($bag->first($field)).'</p>' : '';
@endphp
<dialog id="owner-edit" @if ($bag->any()) data-open-on-load @endif
        class="m-auto w-[min(48rem,calc(100vw-2rem))] rounded-2xl p-0 text-ink shadow-2xl backdrop:bg-black/50">
    <form method="POST" action="{{ route('owner.update', $provider) }}" enctype="multipart/form-data" class="flex max-h-[90vh] flex-col">
        @csrf @method('PUT')

        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h2 class="text-lg font-bold">{{ __('تعديل بيانات القناة') }}</h2>
            <button type="button" data-close-dialog class="rounded-full p-2 text-gray-500 hover:bg-gray-100" aria-label="{{ __('إغلاق') }}">✕</button>
        </div>

        <div class="space-y-6 overflow-y-auto px-6 py-5">
            @if ($bag->any())
                <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ __('راجع الحقول المظللة أدناه.') }}</div>
            @endif

            {{-- Managed by the platform --}}
            <div class="rounded-xl bg-gray-50 p-4 text-sm">
                <div class="grid gap-2 sm:grid-cols-3">
                    <div><span class="text-gray-500">{{ __('اسم القناة') }}:</span> <strong><bdi>{{ $provider->name }}</bdi></strong></div>
                    <div><span class="text-gray-500">{{ trans_choice('التصنيفات', 2) }}:</span> {{ $provider->categories->map->tr('name')->join(__('trust.analysis.list_separator')) }}</div>
                    <div><span class="text-gray-500">{{ __('التوثيق') }}:</span> {{ $provider->is_verified ? __('موثّق') : __('غير موثّق') }}</div>
                </div>
                <p class="mt-2 text-xs text-gray-500">{{ __('لتعديل الاسم أو التصنيفات أو التوثيق تواصل مع إدارة المنصة.') }}</p>
            </div>

            {{-- Description, one tab per language --}}
            <fieldset data-tabs>
                <legend class="label">{{ __('الوصف') }}</legend>
                <div class="mb-3 inline-flex rounded-xl bg-gray-100 p-1 text-sm">
                    @foreach ($locales as $code => $meta)
                        <button type="button" data-tab="{{ $code }}" class="rounded-lg px-4 py-1.5 font-medium aria-selected:bg-white aria-selected:shadow-sm"
                                aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $meta['name'] }}</button>
                    @endforeach
                </div>
                @foreach ($locales as $code => $meta)
                    @php
                        $isBase = $code === $baseLocale;
                        $prefix = $isBase ? '' : "translations.$code.";
                        $name = fn ($field) => $isBase ? $field : "translations[$code][$field]";
                        $value = fn ($field) => old($prefix.$field, $isBase ? $provider->{$field} : ($provider->translations[$code][$field] ?? ''));
                    @endphp
                    <div data-panel="{{ $code }}" @class(['space-y-3', 'hidden' => ! $loop->first])>
                        <div>
                            <label class="label" for="svc-{{ $code }}">{{ __('نوع الخدمة') }} @if ($isBase)<span class="text-red-500">*</span>@endif</label>
                            <input id="svc-{{ $code }}" name="{{ $name('service_type') }}" value="{{ $value('service_type') }}" dir="{{ $meta['dir'] }}"
                                   maxlength="150" class="input" @required($isBase) placeholder="{{ $isBase ? __('مثال: توصيات ذهب وفوركس') : '' }}">
                            {!! $err($prefix.'service_type') !!}
                        </div>
                        <div>
                            <label class="label" for="bio-{{ $code }}">{{ __('نبذة عن القناة') }}</label>
                            <textarea id="bio-{{ $code }}" name="{{ $name('bio') }}" rows="4" maxlength="3000" dir="{{ $meta['dir'] }}" class="input">{{ $value('bio') }}</textarea>
                            {!! $err($prefix.'bio') !!}
                        </div>
                        @unless ($isBase)
                            <p class="text-xs text-gray-500">{{ __('اختياري: إذا تُرك فارغاً يظهر النص العربي.') }}</p>
                        @endunless
                    </div>
                @endforeach
            </fieldset>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="own-pricing">{{ __('التسعير') }}</label>
                    <select id="own-pricing" name="pricing" class="input">
                        <option value="">{{ __('— اختر —') }}</option>
                        @foreach (__('trust.pricing_options') as $key => $label)
                            <option value="{{ $key }}" @selected(old('pricing', $provider->pricing) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <span class="label">{{ __('الشعار') }}</span>
                    <div class="flex items-center gap-3">
                        <x-avatar :provider="$provider" size="h-12 w-12 text-lg" />
                        <input type="file" name="avatar" accept="image/*" class="min-w-0 flex-1 text-xs">
                    </div>
                    @if ($provider->avatar)
                        <label class="mt-1 flex items-center gap-2 text-xs text-gray-600">
                            <input type="checkbox" name="remove_avatar" value="1" class="accent-red-600"> {{ __('حذف الشعار الحالي') }}
                        </label>
                    @endif
                    {!! $err('avatar') !!}
                </div>
            </div>

            <fieldset>
                <legend class="label">{{ __('الأسواق') }}</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach ($markets as $market)
                        <label class="chip chip-pro">
                            <input type="checkbox" name="market_ids[]" value="{{ $market->id }}" class="sr-only" @checked(in_array($market->id, $selectedMarkets))>
                            {{ $market->icon }} {{ $market->tr('name') }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- Official links --}}
            <fieldset class="space-y-3">
                <legend class="label">{{ __('الروابط الرسمية') }}</legend>
                <p class="-mt-1 text-xs text-gray-500">{{ __('تظهر للزوار كالروابط الرسمية الوحيدة لقناتك. أي تعديل عليها يُسجَّل ويُراجع من الإدارة.') }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach (array_keys(config('trust.socials')) as $field)
                        <div>
                            <label class="label" for="own-{{ $field }}">{{ __("trust.socials.$field") }} @if ($field === 'telegram_url')<span class="text-red-500">*</span>@endif</label>
                            <input id="own-{{ $field }}" type="url" name="{{ $field }}" value="{{ old($field, $provider->{$field}) }}" dir="ltr" class="input" placeholder="https://" @required($field === 'telegram_url')>
                            {!! $err($field) !!}
                        </div>
                    @endforeach
                    <div>
                        <label class="label" for="own-members">{{ __('مشتركو التيليجرام') }}</label>
                        <input id="own-members" type="number" min="0" name="telegram_members" value="{{ old('telegram_members', $provider->telegram_members) }}" dir="ltr" class="input">
                        {!! $err('telegram_members') !!}
                    </div>
                </div>

                <div>
                    <span class="label">{{ __('روابط رسمية إضافية') }} <span class="font-normal text-gray-500">({{ __('مثل حساب الدعم أو بوت الاشتراك') }})</span></span>
                    <div class="space-y-2">
                        @for ($i = 0; $i < 5; $i++)
                            <div @class(['grid gap-2 sm:grid-cols-[1fr_2fr]', 'hidden' => $i > count($extraLinks)]) data-extra-link>
                                <input name="extra_links[{{ $i }}][label]" value="{{ $extraLinks[$i]['label'] ?? '' }}" maxlength="60" class="input" placeholder="{{ __('الوصف') }}">
                                <input type="url" name="extra_links[{{ $i }}][url]" value="{{ $extraLinks[$i]['url'] ?? '' }}" dir="ltr" class="input" placeholder="https://">
                                {!! $err("extra_links.$i.url") !!}
                            </div>
                        @endfor
                    </div>
                    @if (count($extraLinks) < 4)
                        <button type="button" data-show-next="[data-extra-link].hidden" class="mt-2 text-sm font-semibold text-sky-700 hover:underline">+ {{ __('إضافة رابط') }}</button>
                    @endif
                </div>
            </fieldset>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 px-6 py-4">
            <button type="button" data-close-dialog class="btn-outline">{{ __('إلغاء') }}</button>
            <button class="btn bg-sky-600 px-5 py-2.5 text-white hover:bg-sky-700">{{ __('حفظ التغييرات') }}</button>
        </div>
    </form>
</dialog>
