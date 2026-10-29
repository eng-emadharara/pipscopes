<x-email.layout>
    <p style="margin:0 0 12px;">{{ __('مرحباً :name،', ['name' => $user->name]) }}</p>
    <p style="margin:0 0 20px;">{{ __('استخدم الرمز التالي لتأكيد بريدك الإلكتروني وتفعيل حسابك:') }}</p>
    <div dir="ltr" style="text-align:center;font-size:34px;font-weight:bold;letter-spacing:10px;background:#ecfdf5;color:#047857;border-radius:12px;padding:16px 8px;margin:0 0 20px;">{{ $code }}</div>
    <p style="margin:0 0 8px;color:#555;">{{ __('الرمز صالح لمدة :minutes دقيقة.', ['minutes' => $minutes]) }}</p>
    <p style="margin:0;color:#555;">{{ __('إذا لم تقم بإنشاء حساب، تجاهل هذه الرسالة.') }}</p>
</x-email.layout>
