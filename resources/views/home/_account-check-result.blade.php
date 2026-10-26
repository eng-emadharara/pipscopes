@php
    $platforms = fn (array $keys) => collect($keys)->map(fn ($p) => __("trust.account_platforms.$p"))->implode('، ');
@endphp
@if (! $result)
@elseif ($result['status'] === 'invalid')
    <div class="border-t border-gray-100 bg-gray-50 px-6 py-4 text-sm text-gray-600 md:px-8">
        {{ __('أدخل رابط الحساب أو اسم المستخدم (مثل @gold_signals) أو رقم الهاتف.') }}
    </div>
@else
    @php $shown = $result['query']['display']; @endphp
    <div class="space-y-3 border-t border-gray-100 px-6 py-5 md:px-8">
        @foreach ($result['impersonators'] as $fake)
            <div class="rounded-xl border border-red-200 bg-red-50 p-4">
                <p class="flex items-center gap-2 font-bold text-red-800">
                    <x-ui-icon name="alert" class="h-5 w-5 shrink-0 text-red-600" />
                    {{ __('احذر: هذا حساب منتحل') }}
                </p>
                <p class="mt-1 text-sm leading-relaxed text-red-900">
                    {!! __('الحساب :account على :platform ينتحل صفة :provider وليس تابعاً له. لا تتواصل معه ولا ترسل له أموالاً.', [
                        'account' => '<bdi dir="ltr" class="font-mono font-semibold">'.e($fake->handle).'</bdi>',
                        'platform' => e($fake->platformLabel()),
                        'provider' => '<bdi class="font-semibold">'.e($fake->provider->name).'</bdi>',
                    ]) !!}
                </p>
                @if ($fake->public_note)
                    <p class="mt-1 text-xs text-red-800">{{ $fake->public_note }}</p>
                @endif
                <a href="{{ route('providers.show', $fake->provider) }}#impersonators" class="mt-2 inline-block text-sm font-semibold text-red-700 hover:underline">{{ __('الحسابات الرسمية للقناة') }} <span class="inline-block rtl:rotate-180">→</span></a>
            </div>
        @endforeach

        @foreach ($result['official'] as $match)
            <div class="rounded-xl border border-brand-200 bg-brand-50 p-4">
                <p class="flex items-center gap-2 font-bold text-brand-800">
                    <x-ui-icon name="check" class="h-5 w-5 shrink-0 text-brand-600" />
                    {{ __('حساب رسمي') }}
                </p>
                <p class="mt-1 text-sm leading-relaxed text-gray-800">
                    {!! __(':account حساب رسمي لـ :provider على :platform.', [
                        'account' => '<bdi dir="ltr" class="font-mono font-semibold">'.e($shown).'</bdi>',
                        'provider' => '<bdi class="font-semibold">'.e($match['provider']->name).'</bdi>',
                        'platform' => e($platforms($match['platforms'])),
                    ]) !!}
                </p>
                @if (! $result['query']['platform'])
                    {{-- A bare "@name": the same name may belong to someone else on another platform --}}
                    <p class="mt-1 text-xs leading-relaxed text-gray-600">{{ __('تأكد أن من يتواصل معك يستخدم المنصة نفسها، فقد يستخدم منتحل الاسم ذاته على منصة أخرى.') }}</p>
                @endif
                <a href="{{ route('providers.show', $match['provider']) }}" class="mt-2 inline-block text-sm font-semibold text-brand-700 hover:underline">{{ __('صفحة القناة وتقييماتها') }} <span class="inline-block rtl:rotate-180">→</span></a>
            </div>
        @endforeach

        @if ($result['status'] === 'unknown')
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="font-bold text-amber-900">{{ __('لا نعرف هذا الحساب') }}</p>
                <p class="mt-1 text-sm leading-relaxed text-gray-800">
                    {!! __(':account ليس مسجلاً لدينا كحساب رسمي لأي قناة، ولا كحساب منتحل. هذا لا يعني أنه آمن:', [
                        'account' => '<bdi dir="ltr" class="font-mono font-semibold">'.e($shown).'</bdi>',
                    ]) !!}
                </p>
                <ul class="mt-2 list-disc space-y-1 ps-5 text-sm text-gray-700">
                    <li>{{ __('لا ترسل أموالاً أو بيانات دخول لأي شخص يتواصل معك بشكل خاص.') }}</li>
                    <li>{{ __('ادخل إلى صفحة القناة عندنا وتواصل معها عبر روابطها الرسمية فقط.') }}</li>
                    <li>{{ __('إن تأكدت أنه ينتحل صفة قناة معينة، أبلغ عنه من صفحة تلك القناة.') }}</li>
                </ul>
                <a href="{{ route('providers.index') }}" class="mt-2 inline-block text-sm font-semibold text-amber-900 hover:underline">{{ __('ابحث عن القناة') }} <span class="inline-block rtl:rotate-180">→</span></a>
            </div>
        @endif
    </div>
@endif
