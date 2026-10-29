{{ __('مرحباً :name،', ['name' => $user->name]) }}

{{ __('استخدم الرمز التالي لتأكيد بريدك الإلكتروني وتفعيل حسابك:') }}

{{ $code }}

{{ __('الرمز صالح لمدة :minutes دقيقة.', ['minutes' => $minutes]) }}
{{ __('إذا لم تقم بإنشاء حساب، تجاهل هذه الرسالة.') }}
@if (\App\Support\Site::telegramContact() || \App\Support\Site::get('contact_email'))

{{ __('تحتاج مساعدة؟ تواصل معنا:') }}
@if ($telegramContact = \App\Support\Site::telegramContact())
{{ __('تيليجرام') }}: {{ $telegramContact }}
@endif
@if ($contactEmail = \App\Support\Site::get('contact_email'))
{{ __('البريد الإلكتروني') }}: {{ $contactEmail }}
@endif
@endif

{{ config('app.name') }} - {{ url('/') }}
