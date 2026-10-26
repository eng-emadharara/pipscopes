// Countdown for <div data-countdown="ISO-8601 time" data-reload-on-end="1|0"> on the pre-launch page.
// Time is computed in the visitor's own clock/time zone from the absolute launch moment.

document.querySelectorAll('[data-countdown]').forEach((root) => {
    const target = new Date(root.dataset.countdown).getTime();
    if (Number.isNaN(target)) return;

    const units = Object.fromEntries([...root.querySelectorAll('[data-unit]')].map((el) => [el.dataset.unit, el]));
    const pad = (n) => String(n).padStart(2, '0');

    // Launch date in the visitor's local time and the page language
    const dateEl = root.querySelector('[data-launch-date]');
    if (dateEl) {
        dateEl.textContent = new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
            dateStyle: 'full',
            timeStyle: 'short',
            numberingSystem: 'latn',
        }).format(target);
    }

    let timer = null;
    const tick = () => {
        const left = Math.max(0, Math.floor((target - Date.now()) / 1000));

        units.days.textContent = Math.floor(left / 86400);
        units.hours.textContent = pad(Math.floor((left % 86400) / 3600));
        units.minutes.textContent = pad(Math.floor((left % 3600) / 60));
        units.seconds.textContent = pad(left % 60);

        if (left === 0) {
            clearInterval(timer);
            root.querySelector('[data-ended]')?.classList.remove('hidden');
            // When the site opens automatically, reload so visitors land on the live site
            if (root.dataset.reloadOnEnd === '1') setTimeout(() => window.location.reload(), 3000);
        }
    };

    tick();
    timer = setInterval(tick, 1000);
});
