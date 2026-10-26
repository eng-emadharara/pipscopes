@php
    $dir = config('trust.locales.'.app()->getLocale().'.dir', 'rtl');
    $align = $dir === 'rtl' ? 'right' : 'left';
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
                        <td style="background:#191919;padding:20px 28px;text-align:{{ $align }};">
                            <span style="display:inline-block;background:#00b67a;color:#fff;border-radius:6px;padding:2px 8px;font-weight:bold;">★</span>
                            <span style="color:#fff;font-size:18px;font-weight:bold;">&nbsp;{{ config('app.name') }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td dir="{{ $dir }}" style="padding:28px;text-align:{{ $align }};font-size:15px;line-height:1.8;">
                            <p style="margin:0 0 12px;">{{ __('مرحباً :name،', ['name' => $user->name]) }}</p>
                            <p style="margin:0 0 20px;">{{ __('استخدم الرمز التالي لتأكيد بريدك الإلكتروني وتفعيل حسابك:') }}</p>
                            <div dir="ltr" style="text-align:center;font-size:34px;font-weight:bold;letter-spacing:10px;background:#ecfdf5;color:#047857;border-radius:12px;padding:16px 8px;margin:0 0 20px;">{{ $code }}</div>
                            <p style="margin:0 0 8px;color:#555;">{{ __('الرمز صالح لمدة :minutes دقيقة.', ['minutes' => $minutes]) }}</p>
                            <p style="margin:0;color:#555;">{{ __('إذا لم تقم بإنشاء حساب، تجاهل هذه الرسالة.') }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px;border-top:1px solid #eee;color:#999;font-size:12px;text-align:center;">
                            © {{ date('Y') }} {{ config('app.name') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
