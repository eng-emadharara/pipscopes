{{-- Header button for signed-in members who haven't linked Telegram yet --}}
@php
    $user = auth()->user();
    $show = $user && \App\Support\SocialLogin::isEnabled('telegram') && ! $user->socialAccounts->contains('provider', 'telegram');
@endphp
@if ($show)
    <button type="button" class="tg-link" data-telegram-link
            data-bot-id="{{ \App\Support\TelegramLogin::botId() }}" data-action="{{ route('social.telegram.link') }}"
            title="{{ __('اربط حسابك بتيليجرام لتسجيل الدخول بنقرة واحدة') }}" aria-label="{{ __('ربط تيليجرام') }}">
        <x-ui-icon name="telegram" class="tg-link-icon h-4 w-4" />
        <span class="tg-link-spinner" aria-hidden="true"></span>
        <span class="hidden sm:inline">{{ __('ربط تيليجرام') }}</span>
    </button>

    <script async src="https://telegram.org/js/telegram-widget.js?22"></script>
    <script>
        (() => {
            const btn = document.querySelector('[data-telegram-link]');
            if (!btn) return;

            const busy = (on) => btn.toggleAttribute('aria-busy', on);

            btn.addEventListener('click', () => {
                if (btn.hasAttribute('aria-busy')) return;
                const login = window.Telegram?.Login;
                if (!login) { alert(@json(__('تعذّر فتح نافذة تيليجرام. اسمح بالنوافذ المنبثقة وحاول مرة أخرى.'))); return; }

                busy(true);
                login.auth({ bot_id: btn.dataset.botId, request_access: 'write', lang: document.documentElement.lang }, (user) => {
                    if (!user) { busy(false); return; } // closed the popup
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

            window.addEventListener('pageshow', (e) => { if (e.persisted) busy(false); });
        })();
    </script>
@endif
