@php
    $user = auth()->user();
    $selectedSubject = old('subject', $subject);
@endphp
<x-layouts.app :title="$page?->tr('title') ?? __('تواصل معنا')" :description="$page?->tr('subtitle')">
    <section class="bg-ink pb-20 pt-14 text-white">
        <div class="mx-auto max-w-3xl px-4 text-center">
            <h1 class="text-3xl font-bold leading-tight md:text-5xl">{{ $page?->tr('title') ?? __('تواصل معنا') }}</h1>
            <p class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-gray-300">
                {{ $page?->tr('subtitle') ?? __('لديك سؤال أو ملاحظة أو بلاغ؟ راسلنا وسنرد عليك في أقرب وقت.') }}
            </p>
        </div>
    </section>

    <div class="mx-auto -mt-10 grid max-w-5xl gap-5 px-4 lg:grid-cols-[1fr_320px]">
        <form method="POST" action="{{ route('contact.store') }}" class="card space-y-5 p-6 shadow-sm md:p-8">
            @csrf
            {{-- Honeypot --}}
            <input type="text" name="website_confirm" class="hidden" tabindex="-1" autocomplete="off">

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="label" for="name">{{ __('الاسم') }} <span class="text-red-500">*</span></label>
                    <input id="name" name="name" dir="auto" value="{{ old('name', $user?->name) }}" class="input" maxlength="100" required>
                    @error('name') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="email">{{ __('البريد الإلكتروني') }} <span class="text-red-500">*</span></label>
                    <input id="email" type="email" name="email" dir="ltr" value="{{ old('email', $user?->email) }}" class="input" required>
                    @error('email') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="label" for="subject">{{ __('الموضوع') }} <span class="text-red-500">*</span></label>
                <select id="subject" name="subject" class="input" required>
                    <option value="">{{ __('— اختر —') }}</option>
                    @foreach (__('trust.contact_subjects') as $key => $label)
                        <option value="{{ $key }}" @selected($selectedSubject === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('subject') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div data-show-when="subject=account_deletion" @class(['rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-relaxed text-amber-900', 'hidden' => $selectedSubject !== 'account_deletion'])>
                {{ __('لطلب حذف الحساب: أرسل الطلب من نفس البريد الإلكتروني المسجّل في حسابك حتى نتمكن من التحقق منه. سيتم حذف حسابك وتقييماتك وبياناتك الشخصية نهائياً ولا يمكن التراجع عن ذلك.') }}
            </div>

            <div>
                <label class="label" for="message">{{ __('الرسالة') }} <span class="text-red-500">*</span></label>
                <textarea id="message" name="message" dir="auto" rows="7" class="input" minlength="10" maxlength="5000" required>{{ old('message') }}</textarea>
                @error('message') <p class="error">{{ $message }}</p> @enderror
            </div>

            <button class="btn-primary">{{ __('إرسال الرسالة') }}</button>
        </form>

        <aside class="space-y-4">
            @if ($content && ($content['intro'] || $content['sections']))
                <div class="card rich-content p-5 text-sm">
                    {!! $content['intro'] !!}
                    @foreach ($content['sections'] as $section)
                        <h3>{{ $section['heading'] }}</h3>
                        {!! $section['html'] !!}
                    @endforeach
                </div>
            @endif
            <div class="card p-5 text-sm leading-relaxed text-gray-600">
                <div class="mb-1 font-semibold text-ink">{{ __('ملاحظة') }}</div>
                {{ __('نحن لا نقدم توصيات أو نصائح مالية، ولا نتوسط بين المستخدمين ومقدمي الخدمات. للبلاغ عن تقييم معين أرفق رابط صفحة مقدم الخدمة.') }}
            </div>
        </aside>
    </div>
</x-layouts.app>
