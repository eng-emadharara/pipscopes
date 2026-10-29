{{ __('مرحباً :name،', ['name' => $user->name]) }}

{{ $title }}

@foreach ($lines as $line)
{{ $line }}

@endforeach
@if ($quote)
"{{ \Illuminate\Support\Str::limit($quote, 600) }}"

@endif
@if ($action)
{{ $action[0] }}: {{ $action[1] }}

@endif
{{ __('إيقاف هذه الإشعارات') }}: {{ $unsubscribe }}

{{ config('app.name') }} - {{ url('/') }}
