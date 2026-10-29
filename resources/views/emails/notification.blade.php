{{-- Member notifications (App\Notifications\MemberNotification) --}}
<x-email.layout>
    <x-slot:note>
        {{ __('وصلتك هذه الرسالة لأن إشعارات «:type» مفعّلة في حسابك.', ['type' => $typeLabel]) }}
        <a href="{{ $unsubscribe }}" style="color:#999;text-decoration:underline;">{{ __('إيقاف هذه الإشعارات') }}</a>
        ·
        <a href="{{ route('notifications.edit') }}" style="color:#999;text-decoration:underline;">{{ __('إعدادات الإشعارات') }}</a>
    </x-slot:note>

    @if ($image ?? null)
        <img src="{{ $image }}" alt="" width="424" style="display:block;width:100%;max-width:424px;height:auto;border:0;border-radius:12px;margin:0 0 18px;">
    @endif
    <p style="margin:0 0 12px;">{{ __('مرحباً :name،', ['name' => $user->name]) }}</p>
    <h1 style="margin:0 0 14px;font-size:19px;line-height:1.5;">{{ $title }}</h1>
    @foreach ($lines as $line)
        <p style="margin:0 0 12px;color:#333;">{{ $line }}</p>
    @endforeach
    @if ($quote)
        <div dir="auto" style="margin:4px 0 16px;padding:12px 14px;background:#f6f6f3;border-radius:10px;color:#444;font-size:14px;white-space:pre-line;">{{ \Illuminate\Support\Str::limit($quote, 600) }}</div>
    @endif
    @if ($action)
        <p style="margin:20px 0 0;">
            <a href="{{ $action[1] }}" style="display:inline-block;background:#00b67a;color:#ffffff;text-decoration:none;font-weight:bold;border-radius:999px;padding:10px 22px;">{{ $action[0] }}</a>
        </p>
    @endif
</x-email.layout>
