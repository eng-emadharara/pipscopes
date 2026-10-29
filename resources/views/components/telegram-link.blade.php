{{--
    "Link Telegram" for signed-in members who haven't linked it yet: the pill in the header, or a small
    button inside a page (variant="inline", e.g. profile and notification settings).
    "relink" shows it to an already linked member too, to give the bot permission to write again.
--}}
@props(['variant' => 'header', 'label' => null, 'relink' => false])
@php
    $user = auth()->user();
    $show = $user && \App\Support\SocialLogin::isEnabled('telegram') && \App\Support\SocialLogin::isShown('telegram')
        && ($relink || ! $user->socialAccounts->contains('provider', 'telegram'));
@endphp
@if ($show)
    @if ($variant === 'header')
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
    @else
        <button type="button" {{ $attributes->merge(['class' => 'tg-link tg-link-inline']) }} data-telegram-link
                data-bot-id="{{ \App\Support\TelegramLogin::botId() }}" data-action="{{ route('social.telegram.link') }}">
            <x-ui-icon name="telegram" class="tg-link-icon h-4 w-4" />
            <span class="tg-link-spinner" aria-hidden="true"></span>
            <span>{{ $label ?? __('ربط') }}</span>
        </button>
    @endif

    @once
        <script async src="https://telegram.org/js/telegram-widget.js?22"></script>
        <script>
            // Waits for the page: the first button may come after this script (header and page both use the component)
            document.addEventListener('DOMContentLoaded', () => {
                const hint = document.querySelector('[data-telegram-hint]');
                if (hint) {
                    const hintKey = 'tg-link-hint-dismissed';
                    try { hint.hidden = localStorage.getItem(hintKey) === '1'; } catch { hint.hidden = false; }
                    document.querySelector('[data-telegram-hint-close]').addEventListener('click', () => {
                        hint.hidden = true;
                        try { localStorage.setItem(hintKey, '1'); } catch {}
                    });
                }

                const buttons = [...document.querySelectorAll('[data-telegram-link]')];
                const busy = (btn, on) => btn.toggleAttribute('aria-busy', on);

                buttons.forEach((btn) => btn.addEventListener('click', () => {
                    if (btn.hasAttribute('aria-busy')) return;
                    const login = window.Telegram?.Login;
                    if (!login) { alert(@json(__('تعذّر فتح نافذة تيليجرام. اسمح بالنوافذ المنبثقة وحاول مرة أخرى.'))); return; }

                    busy(btn, true);
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
                }));

                window.addEventListener('pageshow', (e) => { if (e.persisted) buttons.forEach((btn) => busy(btn, false)); });
            });
        </script>
    @endonce
@endif
