// components/review-invite: searching for the channel to review, and the small card inviting to it.

// --- The picker: empty field shows the popular channels, typing searches ---
const picker = document.querySelector('[data-review-picker]');

if (picker) {
    const search = picker.querySelector('[data-review-picker-search]');
    const list = picker.querySelector('[data-review-picker-list]');
    const empty = picker.querySelector('[data-review-picker-empty]');
    let popular = null;
    let timer = null;
    let latest = 0;

    const row = (channel) => {
        const link = Object.assign(document.createElement('a'), { href: channel.review_url });
        link.className = 'flex items-center gap-3 rounded-lg p-2 hover:bg-gray-50';
        link.dataset.track = 'review_picker_choose';

        const avatar = document.createElement(channel.avatar ? 'img' : 'span');
        avatar.className = 'grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-50 object-cover font-bold text-brand-700';
        if (channel.avatar) {
            avatar.src = channel.avatar;
            avatar.alt = '';
        } else {
            avatar.textContent = channel.initial;
        }

        const name = document.createElement('span');
        name.className = 'min-w-0 flex-1 truncate font-semibold';
        name.append(Object.assign(document.createElement('bdi'), { textContent: channel.name }));

        const action = document.createElement('span');
        action.className = 'shrink-0 text-sm font-semibold text-brand-700';
        action.textContent = picker.dataset.rateLabel;

        link.append(avatar, name, action);
        const item = document.createElement('li');
        item.append(link);

        return item;
    };

    const show = (items, none = false) => {
        list.replaceChildren(...items);
        empty.classList.toggle('hidden', !none);
    };

    const load = async (query) => {
        const response = await fetch(`${picker.dataset.suggestUrl}?${query}`, { headers: { Accept: 'application/json' } });
        const { providers = [] } = await response.json();

        return providers;
    };

    const showPopular = async () => {
        const round = ++latest;
        try {
            popular ??= await load('popular=1');
            if (round === latest) show(popular.map(row));
        } catch {
            // Offline for a moment: they can still type, or try again
        }
    };

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-open-dialog="review-picker"]') && !search.value.trim()) showPopular();
    });

    search.addEventListener('input', () => {
        clearTimeout(timer);
        const term = search.value.trim();

        if (term.length < 2) {
            showPopular();
            return;
        }

        const round = ++latest;
        timer = setTimeout(async () => {
            try {
                const providers = await load(`q=${encodeURIComponent(term)}`);
                if (round === latest) show(providers.map(row), !providers.length);
            } catch {
                // Offline for a moment: the list stays as it was
            }
        }, 250);
    });
}

// --- The card: once, a little after arriving, then not for 30 days ---
const card = document.querySelector('[data-review-invite]');
const KEY = 'tt:review-invite-shown';
const QUIET_DAYS = 30;

if (card) {
    const shownAt = () => { try { return Number(localStorage.getItem(KEY)) || 0; } catch { return 0; } };
    let done = Date.now() - shownAt() < QUIET_DAYS * 86400000;
    let timer = null;

    const remember = () => { try { localStorage.setItem(KEY, String(Date.now())); } catch {} };

    const reveal = () => {
        if (done) return;

        // Not on top of something they're in the middle of
        if (document.querySelector('dialog[open], details[data-share][open], [data-telegram-app-panel]')) {
            timer = setTimeout(reveal, 5000);
            return;
        }

        done = true;
        clearTimeout(timer);
        window.removeEventListener('scroll', onScroll);
        card.classList.remove('hidden');
        remember();
    };

    const onScroll = () => {
        const room = document.documentElement.scrollHeight - window.innerHeight;
        if (room > 0 && window.scrollY / room >= 0.5) reveal();
    };

    if (!done) {
        timer = setTimeout(reveal, 20000);
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    card.querySelector('[data-review-invite-close]').addEventListener('click', () => card.remove());

    // Opening the picker from anywhere answers the invitation
    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-open-dialog="review-picker"]')) return;
        done = true;
        clearTimeout(timer);
        card.remove();
        remember();
    });
}
