@php
    // Brand colour of each platform's icon tile
    $tones = [
        'telegram_url' => 'bg-[#229ED9] text-white',
        'website_url' => 'bg-brand-500 text-white',
        'twitter_url' => 'bg-ink text-white',
        'instagram_url' => 'bg-gradient-to-tr from-[#F58529] via-[#DD2A7B] to-[#8134AF] text-white',
        'youtube_url' => 'bg-[#FF0000] text-white',
        'tiktok_url' => 'bg-ink text-white',
        'facebook_url' => 'bg-[#1877F2] text-white',
    ];
    $placeholders = ['website_url' => 'example.com'];
    $saved = $user->social_links ?? [];
    $value = fn (string $field) => old("social_links.$field", $saved[$field] ?? '');
    // A row is open when it has a value, or came back from a failed submit
    $open = fn (string $field) => filled($value($field)) || $errors->has("social_links.$field");
    $linked = $user->socialAccounts->keyBy('provider');
    $avatar = $user->avatarUrl();
@endphp
<x-layouts.app :title="__('ملفي الشخصي')">
    <form id="profile-form" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" data-profile-form>
        @csrf @method('PUT')

        {{-- Cover --}}
        <div class="relative h-36 overflow-hidden bg-ink sm:h-44">
            <div class="absolute inset-0 bg-[radial-gradient(60rem_20rem_at_80%_-20%,rgb(0_182_122/.45),transparent),radial-gradient(40rem_16rem_at_0%_120%,rgb(0_182_122/.25),transparent)]"></div>
            <div class="absolute inset-0 opacity-[.07] [background-image:linear-gradient(#fff_1px,transparent_1px),linear-gradient(90deg,#fff_1px,transparent_1px)] [background-size:32px_32px]"></div>
        </div>

        <div class="mx-auto max-w-5xl px-4">
            {{-- Identity --}}
            <section class="card relative -mt-16 p-5 sm:-mt-20 sm:p-7">
                <div class="flex flex-col items-center gap-5 text-center sm:flex-row sm:items-end sm:text-start">
                    <div class="relative -mt-16 shrink-0 sm:-mt-20">
                        <label for="avatar" class="group relative block h-28 w-28 cursor-pointer overflow-hidden rounded-full bg-white ring-4 ring-white shadow-lg sm:h-32 sm:w-32"
                               title="{{ __('تغيير الصورة') }}">
                            <img src="{{ $avatar }}" alt="{{ $user->name }}" referrerpolicy="no-referrer" data-avatar-img
                                 @class(['h-full w-full object-cover', 'hidden' => ! $avatar])>
                            <span data-avatar-initials @class(['grid h-full w-full place-items-center bg-brand-50 text-4xl font-bold text-brand-700', 'hidden' => $avatar])>{{ $user->initials() }}</span>
                            <span class="absolute inset-0 grid place-items-center bg-ink/55 text-white opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100">
                                <x-ui-icon name="camera" class="h-7 w-7" />
                            </span>
                        </label>
                        <label for="avatar" class="absolute bottom-1 end-1 grid h-9 w-9 cursor-pointer place-items-center rounded-full bg-brand-500 text-white shadow-md ring-4 ring-white transition hover:bg-brand-600" aria-label="{{ __('تغيير الصورة') }}">
                            <x-ui-icon name="camera" class="h-4 w-4" />
                        </label>
                    </div>

                    <div class="min-w-0 flex-1">
                        <h1 class="flex items-center justify-center gap-2 text-2xl font-bold sm:justify-start">
                            <bdi class="truncate" data-name-preview>{{ $user->name }}</bdi>
                            @if ($user->isVerifiedMember())
                                <x-ui-icon name="check" class="h-5 w-5 shrink-0 text-brand-500" title="{{ __('حساب موثّق') }}" />
                            @endif
                        </h1>
                        <div class="mt-2 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-sm text-gray-500 sm:justify-start">
                            @if ($user->email)
                                <span class="inline-flex items-center gap-1.5"><x-ui-icon name="mail" class="h-4 w-4" /> <span dir="ltr">{{ $user->email }}</span></span>
                            @endif
                            <span class="inline-flex items-center gap-1.5"><x-ui-icon name="calendar" class="h-4 w-4" /> {{ __('عضو منذ :date', ['date' => $user->created_at->translatedFormat('F Y')]) }}</span>
                            <span class="inline-flex items-center gap-1.5"><x-ui-icon name="star" class="h-4 w-4" /> {{ trans_choice(':count تقييم', $reviewsCount) }}</span>
                        </div>
                    </div>

                    @if ($links = $user->socialLinks())
                        <div class="flex flex-wrap justify-center gap-2">
                            @foreach ($links as $link)
                                <a href="{{ $link['url'] }}" target="_blank" rel="noopener nofollow" title="{{ $link['label'] }}" aria-label="{{ $link['label'] }}"
                                   class="grid h-9 w-9 place-items-center rounded-full border border-gray-200 text-gray-600 transition hover:border-ink hover:text-ink">
                                    <x-ui-icon :name="$link['icon']" class="h-4 w-4" />
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            @if ($errors->any())
                <div class="mt-6 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">
                    <x-ui-icon name="alert" class="h-5 w-5 shrink-0" /> {{ __('راجع الحقول المظللة أدناه.') }}
                </div>
            @endif

            <div class="mt-6 grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    {{-- Photo and name --}}
                    <section class="card p-5 sm:p-6">
                        <h2 class="text-lg font-bold">{{ __('المعلومات الشخصية') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ __('يظهر اسمك وصورتك بجانب تقييماتك، إلا إذا اخترت النشر باسم مخفي.') }}</p>

                        <div class="mt-6 flex flex-col gap-4 rounded-xl bg-gray-50 p-4 sm:flex-row sm:items-center">
                            <div class="h-16 w-16 shrink-0 overflow-hidden rounded-full bg-white ring-1 ring-gray-200">
                                <img src="{{ $avatar }}" alt="" referrerpolicy="no-referrer" data-avatar-img @class(['h-full w-full object-cover', 'hidden' => ! $avatar])>
                                <span data-avatar-initials @class(['grid h-full w-full place-items-center bg-brand-50 text-xl font-bold text-brand-700', 'hidden' => $avatar])>{{ $user->initials() }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold">{{ __('الصورة الشخصية') }}</div>
                                <div class="mt-0.5 text-xs text-gray-500">{{ __('JPG أو PNG أو WEBP، بحد أقصى 2 ميغابايت. يُفضّل صورة مربعة.') }}</div>
                                <p class="error {{ $errors->has('avatar') ? '' : 'hidden' }}" data-avatar-error>{{ $errors->first('avatar') }}</p>
                            </div>
                            <div class="flex gap-2">
                                <label for="avatar" class="btn-outline cursor-pointer !px-4 !py-2">
                                    <x-ui-icon name="camera" class="h-4 w-4" /> {{ $avatar ? __('تغيير') : __('رفع صورة') }}
                                </label>
                                <button type="button" data-avatar-remove @class(['btn !px-3 !py-2 text-red-600 hover:bg-red-50', 'hidden' => ! $avatar]) aria-label="{{ __('حذف الصورة') }}" title="{{ __('حذف الصورة') }}">
                                    <x-ui-icon name="trash" class="h-4 w-4" />
                                </button>
                            </div>
                            <input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="sr-only" data-avatar-input data-max-size="2097152"
                                   data-too-big="{{ __('حجم الصورة أكبر من 2 ميغابايت.') }}">
                            <input type="hidden" name="remove_avatar" value="0" data-avatar-remove-input>
                        </div>

                        <div class="mt-5">
                            <label class="label" for="name">{{ __('الاسم') }}</label>
                            <input id="name" name="name" dir="auto" value="{{ old('name', $user->name) }}" class="input" maxlength="100" autocomplete="name" required data-name-input>
                            @error('name') <p class="error">{{ $message }}</p> @enderror
                        </div>
                    </section>

                    {{-- Social links --}}
                    <section class="card p-5 sm:p-6">
                        <h2 class="text-lg font-bold">{{ __('وسائل التواصل الاجتماعي') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ __('أضف حساباتك ليتعرّف عليك الآخرون. يمكنك كتابة الرابط كاملاً أو اسم المستخدم مثل @username.') }}</p>

                        <div class="mt-5 space-y-3" data-social-list>
                            <p @class(['rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500', 'hidden' => collect(config('trust.socials'))->keys()->contains($open)]) data-social-empty>
                                {{ __('لم تضف أي حساب بعد. اختر منصة من الأسفل للبدء.') }}
                            </p>

                            @foreach (config('trust.socials') as $field => $icon)
                                <div data-social-row="{{ $field }}" @class(['hidden' => ! $open($field)])>
                                    <label for="social-{{ $field }}" class="sr-only">{{ __("trust.socials.$field") }}</label>
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl {{ $tones[$field] ?? 'bg-gray-100 text-gray-700' }}" title="{{ __("trust.socials.$field") }}">
                                            <x-ui-icon :name="$icon" class="h-5 w-5" />
                                        </span>
                                        <div class="relative min-w-0 flex-1">
                                            <span class="pointer-events-none absolute -top-2 start-3 bg-white px-1 text-[11px] font-semibold text-gray-500">{{ __("trust.socials.$field") }}</span>
                                            <input id="social-{{ $field }}" name="social_links[{{ $field }}]" value="{{ $value($field) }}" dir="ltr" maxlength="255"
                                                   inputmode="url" autocomplete="url" placeholder="{{ $placeholders[$field] ?? '@username' }}"
                                                   @class(['input !py-3', '!border-red-400' => $errors->has("social_links.$field")])>
                                        </div>
                                        <button type="button" data-social-remove class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-gray-400 transition hover:bg-red-50 hover:text-red-600"
                                                aria-label="{{ __('إزالة :name', ['name' => __("trust.socials.$field")]) }}">
                                            <x-ui-icon name="trash" class="h-5 w-5" />
                                        </button>
                                    </div>
                                    @error("social_links.$field") <p class="error ms-14">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-5 border-t border-gray-100 pt-4">
                            <div class="mb-2 text-xs font-semibold text-gray-500">{{ __('إضافة حساب') }}</div>
                            <div class="flex flex-wrap gap-2">
                                @foreach (config('trust.socials') as $field => $icon)
                                    <button type="button" data-social-add="{{ $field }}" @class(['chip gap-2 hover:border-brand-500 hover:text-brand-700', 'hidden' => $open($field)])>
                                        <x-ui-icon name="plus" class="h-3.5 w-3.5" />
                                        <x-ui-icon :name="$icon" class="h-4 w-4" />
                                        {{ __("trust.socials.$field") }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="space-y-6">
                    {{-- Sign-in methods --}}
                    <section class="card p-5">
                        <h2 class="font-bold">{{ __('طرق تسجيل الدخول') }}</h2>
                        <ul class="mt-4 space-y-3 text-sm">
                            @if ($user->email)
                                <li class="flex items-center gap-3">
                                    <span class="grid h-9 w-9 place-items-center rounded-full bg-gray-100 text-gray-700"><x-ui-icon name="mail" class="h-4 w-4" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-semibold">{{ __('البريد الإلكتروني') }}</span>
                                        <span class="block truncate text-xs text-gray-500" dir="ltr">{{ $user->email }}</span>
                                    </span>
                                    @if ($user->hasVerifiedEmail())
                                        <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700">{{ __('مؤكَّد') }}</span>
                                    @else
                                        <a href="{{ route('verification.notice') }}" class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 hover:underline">{{ __('غير مؤكَّد') }}</a>
                                    @endif
                                </li>
                            @endif
                            @foreach (\App\Support\SocialLogin::LABELS as $provider => $label)
                                @continue(! $linked->has($provider) && ! \App\Support\SocialLogin::isEnabled($provider))
                                <li class="flex items-center gap-3">
                                    <span class="grid h-9 w-9 place-items-center rounded-full bg-gray-100 text-gray-700">
                                        @if ($provider === 'google')
                                            <span class="text-sm font-bold text-[#4285F4]">G</span>
                                        @else
                                            <x-ui-icon :name="$provider" class="h-4 w-4" />
                                        @endif
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-semibold">{{ $label }}</span>
                                        @if ($account = $linked->get($provider))
                                            <span class="block truncate text-xs text-gray-500" dir="ltr">{{ $account->nickname ? '@'.$account->nickname : ($account->email ?? $account->name) }}</span>
                                        @endif
                                    </span>
                                    @if ($linked->has($provider))
                                        <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700">{{ __('مرتبط') }}</span>
                                    @elseif ($provider === 'telegram')
                                        <x-telegram-link variant="inline" />
                                    @else
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">{{ __('غير مرتبط') }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>

                    <a href="{{ route('notifications.edit') }}" class="card flex items-center gap-3 p-5 transition hover:border-gray-400">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700"><x-ui-icon name="bell" class="h-5 w-5" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-bold">{{ __('الإشعارات') }}</span>
                            <span class="block text-xs text-gray-500">{{ __('اختر ما يصلك على البريد وتيليجرام') }}</span>
                        </span>
                        <span class="inline-block text-gray-400 rtl:rotate-180">→</span>
                    </a>

                    <section class="card p-5">
                        <div class="font-bold">{{ __('الأجهزة') }}</div>
                        <p class="mt-1 text-xs leading-relaxed text-gray-500">
                            {{ __('تبقى مسجّلاً على هذا الجهاز حتى تسجّل الخروج.') }}
                            @if ($otherSessions)
                                {{ trans_choice('حسابك مفتوح أيضاً على :count جهاز أو متصفح آخر.', $otherSessions) }}
                            @endif
                        </p>
                        <form method="POST" action="{{ route('profile.sign-out-others') }}" class="mt-3"
                              onsubmit="return confirm(@js(__('تسجيل الخروج من كل الأجهزة الأخرى؟ ستبقى مسجّلاً على هذا الجهاز فقط.')))">
                            @csrf
                            <button class="text-sm font-semibold text-red-600 hover:underline">{{ __('تسجيل الخروج من كل الأجهزة الأخرى') }}</button>
                        </form>
                    </section>

                    <section class="rounded-2xl border border-brand-100 bg-brand-50 p-5 text-sm text-brand-700">
                        <div class="flex items-center gap-2 font-bold"><x-ui-icon name="shield" class="h-5 w-5" /> {{ __('خصوصيتك') }}</div>
                        <p class="mt-2 leading-relaxed">{{ __('لا نعرض بريدك الإلكتروني لأحد. يمكنك نشر أي تقييم باسم مخفي متى شئت.') }}</p>
                    </section>
                </aside>
            </div>

            {{-- Save bar --}}
            <div class="sticky bottom-4 z-20 mt-6 grid gap-6 lg:grid-cols-3">
                <div class="card flex items-center justify-between gap-3 px-4 py-3 shadow-lg shadow-black/5 transition lg:col-span-2" data-save-bar>
                    <span class="text-sm text-gray-500" data-save-status data-dirty-text="{{ __('لديك تغييرات غير محفوظة') }}">{{ __('كل التغييرات محفوظة') }}</span>
                    <div class="flex gap-2">
                        <a href="{{ route('profile.edit') }}" class="btn-outline hidden" data-save-reset>{{ __('تجاهل') }}</a>
                        <button class="btn-primary">{{ __('حفظ التغييرات') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</x-layouts.app>
