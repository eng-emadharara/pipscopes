// Signing in through the Telegram app (App\Support\TelegramAppLogin): <a data-telegram-app> opens the bot's chat,
// and this page keeps asking the site until the member has pressed Confirm there. Phones may reload the page while
// the app is open, so the wait is remembered for the tab. The panel also offers the old popup (phone number).

const KEY = 'tt:telegram-app-wait';
const WAIT_MS = 5 * 60 * 1000;
const POLL_MS = 2500;

const remembered = {
    get() { try { return Number(sessionStorage.getItem(KEY)) || 0; } catch { return 0; } },
    set() { try { sessionStorage.setItem(KEY, String(Date.now())); } catch {} },
    clear() { try { sessionStorage.removeItem(KEY); } catch {} },
};

let timer = null;
let panel = null;
let asking = false; // One question at a time: a second one could use up the message the first one just set

const stop = () => {
    clearInterval(timer);
    timer = null;
    panel?.remove();
    panel = null;
    remembered.clear();
};

const check = async (link) => {
    if (Date.now() - remembered.get() > WAIT_MS) { stop(); return; }
    if (asking) return;

    asking = true;
    try {
        const response = await fetch(link.dataset.statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const result = await response.json();
        if (timer && result.status === 'done') {
            stop();
            window.location.href = result.redirect;
        }
    } catch {
        // Offline for a moment (common while switching apps): the next round tries again
    } finally {
        asking = false;
    }
};

// The old way: the browser popup. It posts the signed fields as a normal form.
const usePopup = (link) => {
    const login = window.Telegram?.Login;
    if (!login) { window.alert(link.dataset.popupError); return; }

    stop();
    login.auth({ bot_id: link.dataset.botId, request_access: 'write', lang: document.documentElement.lang }, (user) => {
        if (!user) return; // closed the popup
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = link.dataset.action;
        form.hidden = true;
        Object.entries({ ...user, _token: link.dataset.csrf }).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.name = name;
            input.value = value;
            form.append(input);
        });
        document.body.append(form);
        form.submit();
    });
};

const showPanel = (link) => {
    if (panel) return;

    panel = document.createElement('div');
    panel.setAttribute('role', 'status');
    panel.dataset.telegramAppPanel = '';
    panel.className = 'fixed inset-x-4 bottom-4 z-50 mx-auto max-w-md rounded-2xl bg-white p-4 text-sm text-ink shadow-xl ring-1 ring-black/10';

    const row = document.createElement('div');
    row.className = 'flex items-start gap-3';

    const spinner = document.createElement('span');
    spinner.className = 'mt-0.5 h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-[#229ED9] border-t-transparent';

    const text = document.createElement('p');
    text.className = 'flex-1 font-medium leading-relaxed';
    text.textContent = link.dataset.waitText;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'shrink-0 px-1 text-lg leading-none text-gray-400 hover:text-gray-700';
    close.setAttribute('aria-label', '×');
    close.textContent = '×';
    close.addEventListener('click', stop);

    const fallback = document.createElement('button');
    fallback.type = 'button';
    fallback.className = 'mt-2 text-xs font-semibold text-gray-500 underline hover:text-ink';
    fallback.textContent = link.dataset.fallbackText;
    fallback.addEventListener('click', () => usePopup(link));

    row.append(spinner, text, close);
    panel.append(row, fallback);
    document.body.append(panel);
};

const wait = (link) => {
    showPanel(link);
    clearInterval(timer);
    timer = setInterval(() => check(link), POLL_MS);
    check(link);
};

const links = document.querySelectorAll('a[data-telegram-app]');

links.forEach((link) => {
    link.addEventListener('click', () => {
        // On phones the app takes over and this page stays; a new tab would leave them on an empty one when they return
        if (window.matchMedia('(pointer: coarse)').matches) link.removeAttribute('target');
        remembered.set();
        wait(link);
    });
});

if (links.length && Date.now() - remembered.get() < WAIT_MS) wait(links[0]);

// Back from the app: answer at once instead of at the next round
document.addEventListener('visibilitychange', () => {
    if (!document.hidden && timer && links.length) check(links[0]);
});
