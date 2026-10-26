// Sends "helpful" votes and review reports without reloading the page.
// The forms post normally when JS is unavailable.

const csrf = () => document.querySelector('input[name="_token"]')?.value;

async function post(form) {
    const res = await fetch(form.action, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: new FormData(form),
    });
    if (!res.ok) throw res;
    return res.json();
}

document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (form.matches('[data-helpful-form]')) {
        event.preventDefault();
        const button = form.querySelector('button');
        button.disabled = true;
        try {
            const { voted, count } = await post(form);
            button.setAttribute('aria-pressed', voted ? 'true' : 'false');
            form.querySelector('[data-helpful-count]').textContent = count || '';
        } catch {
            form.submit();
        } finally {
            button.disabled = false;
        }
    }

    if (form.matches('[data-report-form]')) {
        event.preventDefault();
        const details = form.closest('[data-report]');
        try {
            const { message } = await post(form);
            const done = document.createElement('span');
            done.className = 'py-1.5 text-brand-700';
            done.textContent = message;
            details.replaceWith(done);
        } catch {
            form.submit();
        }
    }
}, true); // capture phase, ahead of the global double-submit guard
