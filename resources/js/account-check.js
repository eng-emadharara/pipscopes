// Homepage "Is this account real?": shows the result in place instead of loading a new page
import { track } from './analytics';

document.querySelectorAll('form[data-account-check]').forEach((form) => {
    const output = form.closest('#account-check')?.querySelector('[data-account-check-result]');
    if (!output) return;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        const url = `${form.action}?${new URLSearchParams(new FormData(form))}`;

        button.disabled = true;
        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(response.status);
            output.innerHTML = await response.text();

            // account_check event, described by the server (App\Http\Controllers\AccountCheckController)
            const event = response.headers.get('X-Analytics-Event');
            if (event) {
                const { name, params } = JSON.parse(event);
                track(name, params);
            }
        } catch {
            form.submit(); // fall back to the full page
        } finally {
            button.disabled = false;
        }
    });
});
