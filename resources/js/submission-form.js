// Add-channel form: checks the Telegram link while typing (public channel? already listed or
// waiting for review?) and shows the channel found on Telegram before it's sent.
const form = document.querySelector('form[data-submission-form]');

if (form) {
    const input = form.querySelector('[data-telegram-input]');
    const status = form.querySelector('[data-telegram-status]');
    const preview = form.querySelector('[data-channel-preview]');
    const photo = form.querySelector('[data-channel-photo]');
    const initial = form.querySelector('[data-channel-initial]');
    const submit = form.querySelector('button:not([type="button"])');
    const hint = status.textContent;
    const t = (key) => window.submissionText?.[key] ?? key;
    let timer;
    let lastQuery = '';

    const show = (html, tone) => {
        status.className = `mt-1.5 text-xs ${tone}`;
        status.innerHTML = html;
    };
    const escape = (text) => String(text).replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);
    const hidePreview = () => {
        preview.classList.add('hidden');
        preview.classList.remove('flex');
    };

    const check = async () => {
        const value = input.value.trim();
        if (value === lastQuery) return;
        lastQuery = value;
        submit.disabled = false;
        hidePreview();

        if (value.length < 4) {
            show(escape(hint), 'text-gray-500');
            return;
        }

        show(escape(t('checking')), 'text-gray-500');
        try {
            const response = await fetch(`${form.dataset.checkUrl}?telegram=${encodeURIComponent(value)}`, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (input.value.trim() !== value) return; // typed on meanwhile

            if (data.status === 'new') {
                input.value = data.url; // tidy "@name" into a full link
                lastQuery = data.url;

                form.querySelector('[data-channel-title]').textContent = data.title;
                form.querySelector('[data-channel-meta]').textContent = [data.subscribers, data.verified ? t('verified') : null].filter(Boolean).join(' · ');
                photo.classList.toggle('hidden', !data.photo);
                initial.classList.toggle('hidden', !!data.photo);
                if (data.photo) photo.src = data.photo;
                preview.classList.remove('hidden');
                preview.classList.add('flex');

                show(`✓ ${escape(t('found'))}`, 'text-brand-700');
                return;
            }

            // Anything else can't be sent: say why
            submit.disabled = data.status !== 'unreachable';
            const link = data.url ? ` <a href="${escape(data.url)}" class="font-semibold underline">${escape(t('open'))}</a>` : '';
            show(`${escape(data.message)}${link}`, data.status === 'unreachable' ? 'text-gray-600' : 'text-red-600');
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
}
