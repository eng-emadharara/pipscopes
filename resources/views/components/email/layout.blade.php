{{-- Shared frame of every email: the site logo on top, contact links and an optional note at the bottom --}}
@props(['note' => null])
@php
    $dir = config('trust.locales.'.app()->getLocale().'.dir', 'rtl');
    $align = $dir === 'rtl' ? 'right' : 'left';
    // Admin → إعدادات الموقع: the uploaded logo (absolute URL, so mail clients can load it) and the contact links
    $logo = \App\Support\Site::fileUrl('logo');
    $showName = ! $logo || \App\Support\Site::get('show_name_with_logo', true);
    $telegramContact = \App\Support\Site::telegramContact();
    $contactEmail = \App\Support\Site::get('contact_email');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f0;font-family:Tahoma,Arial,sans-serif;color:#191919;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f0;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="background:#ffffff;padding:18px 28px;border-bottom:1px solid #eee;text-align:{{ $align }};">
                            <a href="{{ url('/') }}" style="text-decoration:none;color:#191919;">
                                @if ($logo)
                                    <img src="{{ $logo }}" alt="{{ config('app.name') }}" height="56" style="height:56px;width:auto;max-width:200px;border:0;vertical-align:middle;">
                                @else
                                    <span style="display:inline-block;background:#00b67a;color:#fff;border-radius:6px;padding:2px 8px;font-weight:bold;vertical-align:middle;">★</span>
                                @endif
                                @if ($showName)
                                    <span style="color:#191919;font-size:18px;font-weight:bold;vertical-align:middle;">&nbsp;{{ config('app.name') }}</span>
                                @endif
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td dir="{{ $dir }}" style="padding:28px;text-align:{{ $align }};font-size:15px;line-height:1.8;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;border-top:1px solid #eee;color:#999;font-size:12px;line-height:1.8;text-align:center;">
                            @if ($telegramContact || $contactEmail)
                                <div style="margin:0 0 10px;color:#555;font-size:13px;">{{ __('تحتاج مساعدة؟ تواصل معنا:') }}</div>
                                <div style="margin:0 0 12px;">
                                    @if ($telegramContact)
                                        <a href="{{ $telegramContact }}" style="display:inline-block;background:#229ed9;color:#ffffff;text-decoration:none;font-weight:bold;font-size:13px;border-radius:999px;padding:7px 16px;margin:3px;">{{ __('راسلنا على تيليجرام') }}</a>
                                    @endif
                                    @if ($contactEmail)
                                        <a href="mailto:{{ $contactEmail }}" dir="ltr" style="display:inline-block;color:#047857;text-decoration:none;font-weight:bold;font-size:13px;padding:7px 8px;margin:3px;">{{ $contactEmail }}</a>
                                    @endif
                                </div>
                            @endif
                            @if ($note)
                                <div style="margin:0 0 8px;">{{ $note }}</div>
                            @endif
                            © {{ date('Y') }} {{ config('app.name') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
