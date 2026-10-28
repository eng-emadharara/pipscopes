{{-- Header button for signed-in members who haven't linked Telegram yet --}}
@php
    $user = auth()->user();
    $show = $user && \App\Support\SocialLogin::isEnabled('telegram') && ! $user->socialAccounts->contains('provider', 'telegram');
@endphp
@if ($show)
    <span class="relative inline-flex">
        <button type="button" class="tg-link" data-telegram-link
                data-bot-id="{{ \App\Support\TelegramLogin::botId() }}" data-action="{{ route('social.telegram.link') }}"
                title="{{ __('اربط حسابك بتيليجرام لتسجيل الدخول بنقرة واحدة') }}" aria-label="{{ __('ربط تيليجرام') }}">
            <x-ui-icon name="telegram" class="tg-link-icon h-4 w-4" />
            <span class="tg-link-spinner" aria-hidden="true"></span>
            <span class="hidden sm:inline">{{ __('ربط تيليجرام') }}</span>
        </button>

        {{-- Encouraging hint under the button; hidden for good once dismissed --}}
        <span class="tg-link-hint" data-telegram-hint hidden role="status">
            <span>{{ __('اربط تيليجرام لتصلك آخر التحديثات والتنبيهات أولاً بأول، وسجّل دخولك بنقرة واحدة 🚀') }}</span>
            <button type="button" class="tg-link-hint-close" data-telegram-hint-close aria-label="{{ __('إغلاق') }}">&times;</button>
        </span>
    </span>

    <script async src="https://telegram.org/js/telegram-widget.js?22"></script>
    <script>
        (() => {
            const btn = document.querySelector('[data-telegram-link]');
            if (!btn) return;

            const busy = (on) => btn.toggleAttribute('aria-busy', on);

            const hint = document.querySelector('[data-telegram-hint]');
            const hintKey = 'tg-link-hint-dismissed';
            try { hint.hidden = localStorage.getItem(hintKey) === '1'; } catch { hint.hidden = false; }
            document.querySelector('[data-telegram-hint-close]').addEventListener('click', () => {
                hint.hidden = true;
                try { localStorage.setItem(hintKey, '1'); } catch {}
            });

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
