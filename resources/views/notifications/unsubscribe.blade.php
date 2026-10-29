<x-layouts.app :title="__('إيقاف الإشعارات')">
    <div class="mx-auto max-w-md px-4 py-16">
        <div class="card p-6 text-center sm:p-8">
            <span @class(['mx-auto grid h-14 w-14 place-items-center rounded-full', 'bg-brand-50 text-brand-700' => $done, 'bg-gray-100 text-gray-600' => ! $done])>
                <x-ui-icon :name="$done ? 'check' : 'mail'" class="h-7 w-7" />
            </span>

            @if ($done)
                <h1 class="mt-4 text-xl font-bold">{{ __('تم إيقاف هذه الإشعارات') }}</h1>
                <p class="mt-2 text-sm leading-relaxed text-gray-600">
                    {{ __('لن تصلك إشعارات «:type» على :channel بعد الآن.', ['type' => $typeLabel, 'channel' => $channelLabel]) }}
                </p>
            @else
                <h1 class="mt-4 text-xl font-bold">{{ __('إيقاف إشعارات «:type»؟', ['type' => $typeLabel]) }}</h1>
                <p class="mt-2 text-sm leading-relaxed text-gray-600">
                    {{ __('لن تصلك هذه الإشعارات على :channel. يمكنك إعادة تفعيلها متى شئت.', ['channel' => $channelLabel]) }}
                </p>
                <form method="POST" action="{{ request()->fullUrl() }}" class="mt-6">
                    @csrf
                    <button class="btn-primary w-full justify-center">{{ __('نعم، أوقفها') }}</button>
                </form>
            @endif

            <a href="{{ route('notifications.edit') }}" class="mt-4 inline-block text-sm font-semibold text-brand-700 hover:underline">{{ __('كل إعدادات الإشعارات') }}</a>
        </div>
    </div>
</x-layouts.app>
