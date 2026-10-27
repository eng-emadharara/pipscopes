@props(['divider' => __('أو بالبريد الإلكتروني')])
@php
    // Every method is always listed; one without credentials yet shows as "coming soon"
    $providers = array_keys(\App\Support\SocialLogin::LABELS);
    $enabled = \App\Support\SocialLogin::enabled();
@endphp
@if ($providers)
    <div class="space-y-3" data-social-login>
        @foreach ($providers as $provider)
            @php $label = __('المتابعة باستخدام :provider', ['provider' => \App\Support\SocialLogin::LABELS[$provider]]); @endphp
            @if (! in_array($provider, $enabled, true))
                <button type="button" class="social-btn social-btn-{{ $provider }}" disabled aria-disabled="true" title="{{ __('قريباً') }}">
            @elseif ($provider === 'telegram')
                <button type="button" class="social-btn social-btn-telegram" data-social="telegram"
                        data-bot-id="{{ \App\Support\TelegramLogin::botId() }}" data-action="{{ route('social.telegram') }}">
            @else
                <a href="{{ route('social.redirect', $provider) }}" class="social-btn social-btn-{{ $provider }}" data-social="{{ $provider }}">
            @endif
                <span class="social-icon">
                    @if ($provider === 'google')
                        <svg viewBox="0 0 48 48" aria-hidden="true" class="h-5 w-5">
                            <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.6-.4-3.5z"/>
                            <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
                            <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/>
                            <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.6-.4-3.5z"/>
                        </svg>
                    @else
                        <x-ui-icon :name="$provider" class="h-5 w-5" />
                    @endif
                </span>
                <span class="social-spinner" aria-hidden="true"></span>
                <span>{{ $label }}</span>
                @if (in_array($provider, $enabled, true))
                    <span class="social-last" data-last-used hidden>{{ __('آخر استخدام') }}</span>
                @else
                    <span class="social-last social-soon">{{ __('قريباً') }}</span>
                @endif
            @if ($provider === 'telegram' || ! in_array($provider, $enabled, true))
                </button>
            @else
                </a>
            @endif
        @endforeach

        <p class="hidden text-center text-xs text-red-600" data-social-error role="alert">{{ __('تعذّر فتح نافذة تيليجرام. اسمح بالنوافذ المنبثقة وحاول مرة أخرى.') }}</p>
        <p class="flex items-center justify-center gap-1.5 text-center text-xs text-gray-500">
            <x-ui-icon name="shield" class="h-3.5 w-3.5 text-brand-500" />
            {{ __('لن ننشر أي شيء على حساباتك، ويمكنك إخفاء اسمك في التقييمات.') }}
        </p>
    </div>

    <div class="my-6 flex items-center gap-3 text-xs text-gray-400">
        <span class="h-px flex-1 bg-gray-200"></span>
        {{ $divider }}
        <span class="h-px flex-1 bg-gray-200"></span>
    </div>

    @if (in_array('telegram', $enabled, true))
        <script async src="https://telegram.org/js/telegram-widget.js?22"></script>
    @endif
    <script>
        (() => {
            const root = document.querySelector('[data-social-login]');
            if (!root || root.dataset.ready) return;
            root.dataset.ready = '1';

            const KEY = 'tt:last-social-login';
            const store = {
                get() { try { return localStorage.getItem(KEY); } catch { return null; } },
                set(v) { try { localStorage.setItem(KEY, v); } catch {} },
            };
            const error = root.querySelector('[data-social-error]');

            const busy = (btn, on) => {
                root.toggleAttribute('data-busy', on);
                btn.toggleAttribute('aria-busy', on);
            };

            const last = store.get();
            root.querySelector(`[data-social="${last}"] [data-last-used]`)?.removeAttribute('hidden');

            root.querySelectorAll('[data-social]').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    if (root.hasAttribute('data-busy')) { e.preventDefault(); return; }
                    store.set(btn.dataset.social);
                    error.classList.add('hidden');
                    busy(btn, true);
                    if (btn.dataset.social !== 'telegram') return; // the link navigates to the provider

                    const login = window.Telegram?.Login;
                    if (!login) { busy(btn, false); error.classList.remove('hidden'); return; }

                    login.auth({ bot_id: btn.dataset.botId, request_access: 'write', lang: document.documentElement.lang }, (user) => {
                        if (!user) { busy(btn, false); return; } // closed the popup
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = btn.dataset.action;
                        form.hidden = true;
                        Object.entries({ ...user, _token: @json(csrf_token()) }).forEach(([name, value]) => {
                            const input = document.createElement('input');
                            input.name = name;
                            input.value = value;
                            form.append(input);
                        });
                        document.body.append(form);
                        form.submit();
                    });
                });
            });

            // Coming back with the browser's Back button restores the page as it was left: busy
            window.addEventListener('pageshow', (e) => {
                if (e.persisted) root.querySelectorAll('[aria-busy]').forEach((btn) => busy(btn, false));
            });
        })();
    </script>
@endif
