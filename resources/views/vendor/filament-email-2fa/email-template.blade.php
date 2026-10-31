{{-- The admin panel's sign-in code (App\Filament\Auth\EmailTwoFactor); replaces the package's English email --}}
<div dir="rtl" style="font-family: Tahoma, Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #111827;">
    <p>مرحباً {{ $name }}،</p>
    <p>رمز الدخول إلى لوحة التحكم:</p>
    <p style="font-size: 26px; font-weight: bold; letter-spacing: 6px; direction: ltr; text-align: right;">{{ $code }}</p>
    <p>صالح لمدة {{ (int) config('filament-email-2fa.expiry_time_by_mins', 10) }} دقائق. إن لم تكن أنت من حاول الدخول فغيّر كلمة المرور فوراً.</p>
</div>
