// Live search suggestions for <input data-autocomplete="/search/suggest"> inside a <form>.
// Labels come from <script type="application/json" id="autocomplete-i18n"> in the layout.

const i18n = (() => {
    try {
        return JSON.parse(document.getElementById('autocomplete-i18n')?.textContent || '{}');
    } catch {
        return {};
    }
})();

const escapeHtml = (s) =>
    String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

// Arabic letter variants and diacritics are matched loosely, mirroring App\Support\SearchText
const VARIANTS = { 'ا': 'اأإآٱ', 'أ': 'اأإآٱ', 'إ': 'اأإآٱ', 'آ': 'اأإآٱ', 'ه': 'هة', 'ة': 'هة', 'ي': 'يىئ', 'ى': 'يىئ', 'و': 'وؤ' };
const DIACRITICS = '[\\u064B-\\u065F\\u0670\\u0640]*';

function highlight(text, query) {
    const words = query.trim().replace(/^@/, '').split(/\s+/).filter((w) => w.length > 1);
    if (!words.length) return escapeHtml(text);

    const pattern = words
        .map((w) => [...w].map((ch) => {
            const v = VARIANTS[ch];
            return (v ? `[${v}]` : ch.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')) + DIACRITICS;
        }).join(''))
        .join('|');

    let html = '';
    let last = 0;
    for (const m of String(text).matchAll(new RegExp(pattern, 'giu'))) {
        html += escapeHtml(text.slice(last, m.index)) + `<mark class="rounded bg-amber-100 px-0.5 text-ink">${escapeHtml(m[0])}</mark>`;
        last = m.index + m[0].length;
    }

    return html + escapeHtml(String(text).slice(last));
}

// Same "verified" check as the ui-icon Blade component
const CHECK_ICON = 'M12 2a10 10 0 100 20 10 10 0 000-20zm-1.2 14.2l-4-4 1.4-1.4 2.6 2.6 5.6-5.6 1.4 1.4-7 7z';

const label = (key, q = '') => (i18n[key] || key).replace(':q', q);

function setup(input) {
    const form = input.closest('form');
    // The dropdown hangs under [data-autocomplete-box] when present, otherwise under the whole form
    const box = input.closest('[data-autocomplete-box]') ?? form;
    const url = input.dataset.autocomplete;
    const listId = `ac-${Math.random().toString(36).slice(2, 8)}`;

    const panel = document.createElement('div');
    panel.id = listId;
    panel.setAttribute('role', 'listbox');
    panel.className = 'absolute inset-x-0 top-full z-30 mt-2 hidden max-h-[70vh] min-w-72 overflow-y-auto rounded-2xl border border-gray-200 bg-white py-2 text-start text-ink shadow-xl';
    box.classList.add('relative');
    box.appendChild(panel);

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', listId);
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('autocomplete', 'off');

    let timer = null;
    let controller = null;
    let active = -1;

    const options = () => [...panel.querySelectorAll('[role="option"]')];

    const close = () => {
        panel.classList.add('hidden');
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    };

    const open = () => {
        panel.classList.remove('hidden');
        input.setAttribute('aria-expanded', 'true');
    };

    const setActive = (index) => {
        const items = options();
        if (!items.length) return;
        active = (index + items.length) % items.length;
        items.forEach((el, i) => el.classList.toggle('bg-gray-100', i === active));
        items[active].scrollIntoView({ block: 'nearest' });
        input.setAttribute('aria-activedescendant', items[active].id);
    };

    const render = (data, q) => {
        let i = 0;
        const option = (href, inner) =>
            `<a href="${escapeHtml(href)}" id="${listId}-${i++}" role="option" class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50">${inner}</a>`;
        const heading = (text) => `<div class="px-4 pb-1 pt-2 text-xs font-semibold text-gray-400">${escapeHtml(text)}</div>`;

        let html = '';

        // Known impersonator accounts come first, in red
        (data.impersonators || []).forEach((f) => {
            html += option(f.url, `
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-red-100 text-lg text-red-700">⚠</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-semibold text-red-700"><span dir="ltr">${escapeHtml(f.handle)}</span> · ${escapeHtml(f.platform)}</span>
                    <span class="block text-xs text-red-600">${escapeHtml(f.message)}</span>
                </span>`).replace('hover:bg-gray-50', 'bg-red-50 hover:bg-red-100');
        });

        if (data.providers.length) {
            html += heading(label('providers'));
            html += data.providers.map((p) => option(p.url, `
                ${p.avatar
                    ? `<img src="${escapeHtml(p.avatar)}" alt="" class="h-10 w-10 shrink-0 rounded-lg border border-gray-200 object-cover">`
                    : `<span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg border border-brand-100 bg-brand-50 font-bold text-brand-700">${escapeHtml(p.initial)}</span>`}
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-semibold"><bdi>${highlight(p.name, q)}</bdi>${p.verified ? ` <svg viewBox="0 0 24 24" class="inline-block h-4 w-4 fill-current align-[-3px] text-brand-500" role="img" aria-label="${escapeHtml(label('verified'))}"><path d="${CHECK_ICON}"/></svg>` : ''}</span>
                    <span class="block truncate text-xs text-gray-500">
                        ${p.handle ? `<span dir="ltr" class="text-sky-700">${highlight(p.handle, q)}</span> · ` : ''}${highlight(p.subtitle, q)}
                    </span>
                </span>
                <span class="shrink-0 text-end text-xs text-gray-500">
                    ${p.rating ? `<span class="block font-semibold text-ink">★ ${escapeHtml(p.rating)}</span>` : ''}
                    <span class="block">${escapeHtml(p.reviews)}</span>
                </span>`)).join('');
        }

        if (data.categories.length) {
            html += heading(label('categories'));
            html += data.categories.map((c) => option(c.url, `
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-gray-100 text-lg">${escapeHtml(c.icon || '#')}</span>
                <span class="font-medium">${highlight(c.name, q)}</span>`)).join('');
        }

        if (!data.providers.length && !data.categories.length && !(data.impersonators || []).length) {
            html += `<div class="px-4 py-3 text-sm text-gray-500">${escapeHtml(label('empty', q))}</div>`;
        }

        // Always offer the full results page as the last option
        const allUrl = `${form.action}?${new URLSearchParams({ q })}`;
        html += `<div class="mt-1 border-t border-gray-100 pt-1">${option(allUrl, `
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700">⌕</span>
            <span class="text-sm font-semibold text-brand-700">${escapeHtml(label('all', q))}</span>`)}</div>`;

        panel.innerHTML = html;
        active = -1;
        open();
    };

    const fetchSuggestions = async () => {
        const q = input.value.trim();
        if (q.replace(/^@/, '').length < 2) return close();

        controller?.abort();
        controller = new AbortController();
        try {
            const res = await fetch(`${url}?${new URLSearchParams({ q })}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            if (!res.ok) return close();
            // Ignore responses for a query the user has already changed
            if (input.value.trim() === q) render(await res.json(), q);
        } catch (e) {
            if (e.name !== 'AbortError') close();
        }
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(fetchSuggestions, 200);
    });

    input.addEventListener('focus', () => {
        if (panel.innerHTML && input.value.trim().length >= 2) open();
    });

    input.addEventListener('keydown', (e) => {
        const isOpen = !panel.classList.contains('hidden');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            isOpen ? setActive(active + 1) : fetchSuggestions();
        } else if (e.key === 'ArrowUp' && isOpen) {
            e.preventDefault();
            setActive(active - 1);
        } else if (e.key === 'Enter' && isOpen && active >= 0) {
            e.preventDefault();
            window.location.href = options()[active].href;
        } else if (e.key === 'Escape') {
            close();
        }
    });

    document.addEventListener('click', (e) => {
        if (!box.contains(e.target)) close();
    });
}

document.querySelectorAll('input[data-autocomplete]').forEach(setup);
