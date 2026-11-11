// The channel check takes a few seconds on the server (it reads the channel from Telegram), so the
// page that sent it shows what's happening meanwhile: resources/views/components/check-loading.blade.php.
// Forms with [data-check-form] always show it; [data-check-if-link] ones only when a Telegram link was pasted
// (the search sends those to the check).

const overlay = document.querySelector('[data-check-loading]');
const TELEGRAM_LINK = /^(https?:\/\/)?(www\.)?(t\.me|telegram\.me|telegram\.dog)\/\S+$/i;
const STEP_MS = 1600;

let timer = null;

function show() {
    if (!overlay) return;
    const steps = JSON.parse(overlay.dataset.steps || '[]');
    const text = overlay.querySelector('[data-check-loading-text]');
    const dots = overlay.querySelectorAll('[data-check-loading-dot]');
    let step = 0;

    const render = () => {
        text.textContent = steps[step] ?? '';
        dots.forEach((dot, i) => {
            dot.classList.toggle('bg-brand-500', i <= step);
            dot.classList.toggle('bg-gray-200', i > step);
        });
    };

    render();
    overlay.classList.remove('hidden');
    overlay.classList.add('grid');

    clearInterval(timer);
    // Moves on through the steps and stays on the last one until the report arrives
    timer = setInterval(() => {
        if (step < steps.length - 1) {
            step++;
            render();
        }
    }, STEP_MS);
}

function hide() {
    clearInterval(timer);
    overlay?.classList.add('hidden');
    overlay?.classList.remove('grid');
}

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || e.defaultPrevented) return;

    if (form.matches('[data-check-form]')) {
        show();
    } else if (form.matches('[data-check-if-link]')) {
        const value = (new FormData(form).get('q') || '').toString().trim();
        if (TELEGRAM_LINK.test(value)) show();
    }
});

// Back from the report (the browser restores this page as it was): don't leave it covered
window.addEventListener('pageshow', hide);
