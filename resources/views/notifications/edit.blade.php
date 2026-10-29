@php
    use App\Support\NotificationPrefs;

    $reach = [
        'mail' => NotificationPrefs::canReach($user, 'mail'),
        'telegram' => NotificationPrefs::canReach($user, 'telegram'),
    ];
    $telegramLinked = NotificationPrefs::telegramChatId($user) !== null;
    $channelMeta = [
        'mail' => ['icon' => 'mail', 'label' => __('البريد الإلكتروني'), 'tone' => 'bg-gray-100 text-gray-700'],
        'telegram' => ['icon' => 'telegram', 'label' => __('تيليجرام'), 'tone' => 'bg-[#229ED9] text-white'],
    ];
@endphp
<x-layouts.app :title="__('إعدادات الإشعارات')">
    <div class="mx-auto max-w-3xl px-4 py-8 sm:py-10">
        <a href="{{ route('profile.edit') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 hover:text-ink">
            <span class="inline-block ltr:rotate-180">→</span> {{ __('ملفي الشخصي') }}
        </a>
        <h1 class="mt-3 text-2xl font-bold sm:text-3xl">{{ __('إعدادات الإشعارات') }}</h1>
        <p class="mt-2 text-gray-600">{{ __('اختر ما يصلك منا وأين يصلك. لا نرسل إلا ما يخص حسابك ونشاطك.') }}</p>

        {{-- Where we can reach the member --}}
        <section class="mt-6 grid gap-3 sm:grid-cols-2">
            @foreach ($channels as $channel)
                <div class="card flex items-center gap-3 p-4">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $channelMeta[$channel]['tone'] }}">
                        <x-ui-icon :name="$channelMeta[$channel]['icon']" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1 text-sm">
                        <div class="font-semibold">{{ $channelMeta[$channel]['label'] }}</div>
                        @if ($reach[$channel])
                            <div class="truncate text-xs text-brand-700" @if ($channel === 'mail') dir="ltr" @endif>
                                {{ $channel === 'mail' ? $user->email : __('مرتبط، تصلك الرسائل من بوت الموقع') }}
                            </div>
                        @elseif ($channel === 'mail')
                            <a href="{{ route('verification.notice') }}" class="text-xs font-semibold text-amber-700 hover:underline">
                                {{ $user->email ? __('أكّد بريدك لتصلك الإشعارات') : __('أضف بريدك الإلكتروني (اختياري)') }}
                            </a>
                        @elseif ($telegramLinked && $user->telegram_blocked_at)
                            <div class="text-xs text-amber-700">{{ __('لا نستطيع مراسلتك. سجّل دخولك بتيليجرام مرة أخرى لتفعيل الرسائل.') }}</div>
                        @else
                            <div class="text-xs text-gray-500">{{ __('غير مرتبط. اربط حسابك من زر «ربط تيليجرام» أعلى الصفحة.') }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </section>

        <form method="POST" action="{{ route('notifications.update') }}" class="card mt-6 divide-y divide-gray-100">
            @csrf @method('PUT')

            @forelse ($types as $type => [$label, $description])
                <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold">{{ __($label) }}</div>
                        <p class="mt-0.5 text-sm text-gray-500">{{ __($description) }}</p>
                    </div>
                    <div class="flex flex-wrap gap-4">
                        @foreach ($channels as $channel)
                            @php($on = NotificationPrefs::wants($user, $type, $channel))
                            <label @class(['relative flex items-center gap-2 text-sm', 'cursor-pointer' => $reach[$channel], 'opacity-50' => ! $reach[$channel]])
                                   @unless ($reach[$channel]) title="{{ __('غير متاح لحسابك حالياً') }}" @endunless>
                                {{-- An unavailable channel sends nothing, so it stays on for when it becomes available --}}
                                @if ($reach[$channel])
                                    <input type="hidden" name="prefs[{{ $type }}][{{ $channel }}]" value="0">
                                @endif
                                <input type="checkbox" name="prefs[{{ $type }}][{{ $channel }}]" value="1" class="peer sr-only" @checked($on) @disabled(! $reach[$channel])>
                                <span class="relative h-6 w-11 shrink-0 rounded-full bg-gray-300 transition peer-checked:bg-brand-500 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 peer-focus-visible:ring-offset-2
                                             after:absolute after:top-0.5 after:start-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition
                                             peer-checked:after:translate-x-5 rtl:peer-checked:after:-translate-x-5"></span>
                                <x-ui-icon :name="$channelMeta[$channel]['icon']" class="h-4 w-4 text-gray-500" />
                                {{ $channelMeta[$channel]['label'] }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="p-6 text-center text-sm text-gray-500">{{ __('لا توجد إشعارات متاحة حالياً.') }}</p>
            @endforelse

            @if ($types->isNotEmpty())
                <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-gray-500">{{ __('رسائل الحساب المهمة، مثل رمز تأكيد البريد، تصلك دائماً.') }}</p>
                    <button class="btn-primary shrink-0">{{ __('حفظ الإعدادات') }}</button>
                </div>
            @endif
        </form>
    </div>
</x-layouts.app>
