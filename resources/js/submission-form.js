// Add-channel form: checks the Telegram link while typing (already listed? waiting for review?),
// suggests the channel's name, and remembers the submitter's name/contact for the next channel.
const form = document.querySelector('form[data-submission-form]');

if (form) {
    const input = form.querySelector('[data-telegram-input]');
    const status = form.querySelector('[data-telegram-status]');
    const nameInput = form.querySelector('[data-name-input]');
    const submit = form.querySelector('button:not([type="button"])');
    const hint = status.textContent;
    const t = (key) => window.submissionText?.[key] ?? key;
    let timer;
    let lastQuery = '';
    let suggestedName = '';

    const show = (html, tone) => {
        status.className = `mt-1.5 text-xs ${tone}`;
        status.innerHTML = html;
    };
    const escape = (text) => text.replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);

    const check = async () => {
        const value = input.value.trim();
        if (value === lastQuery) return;
        lastQuery = value;
        submit.disabled = false;

        if (value.length < 4) {
            show(escape(hint), 'text-gray-500');
            return;
        }

        show(escape(t('checking')), 'text-gray-500');
        try {
            const response = await fetch(`${form.dataset.checkUrl}?telegram=${encodeURIComponent(value)}`, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (input.value.trim() !== value) return; // typed on meanwhile

            if (data.status === 'exists') {
                submit.disabled = true;
                const link = data.url ? ` <a href="${escape(data.url)}" class="font-semibold underline">${escape(t('open'))}</a>` : '';
                show(`✋ ${escape(t('exists').replace(':name', data.name))}${link}`, 'text-amber-700');
            } else if (data.status === 'pending') {
                submit.disabled = true;
                show(`⏳ ${escape(t('pending'))}`, 'text-amber-700');
            } else if (data.status === 'new') {
                input.value = data.url; // tidy "@name" into a full link
                lastQuery = data.url;
                // Fill the name unless the visitor already typed their own
                if (data.title && (!nameInput.value || nameInput.value === suggestedName)) {
                    nameInput.value = suggestedName = data.title;
                }
                show(`✓ ${escape(t('new'))}`, 'text-brand-700');
            } else {
                show(escape(t('invalid')), 'text-red-600');
            }
        } catch {
            show(escape(hint), 'text-gray-500');
        }
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(check, 600);
    });
    input.addEventListener('blur', check);
    if (input.value) check();

    // Remember who's submitting, for people adding several channels
    form.querySelectorAll('[data-remember]').forEach((field) => {
        const key = `submission:${field.name}`;
        try {
            if (!field.value) field.value = localStorage.getItem(key) ?? '';
        } catch {}
        field.addEventListener('change', () => {
            try { localStorage.setItem(key, field.value); } catch {}
        });
    });
}
