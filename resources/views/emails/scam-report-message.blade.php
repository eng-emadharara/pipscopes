<x-email.layout>
    <h1 style="margin:0 0 12px;font-size:20px;">بخصوص بلاغك{{ $target ? ' عن '.$target : '' }}</h1>
    <p dir="auto" style="margin:0 0 20px;white-space:pre-line;">{{ $body }}</p>
    <p style="margin:0 0 20px;color:#555;">يمكنك الرد على هذه الرسالة مباشرة.</p>
    <a href="{{ $url }}" style="display:inline-block;background:#00b67a;color:#fff;text-decoration:none;font-weight:bold;padding:12px 24px;border-radius:999px;">بلاغاتي</a>
</x-email.layout>
