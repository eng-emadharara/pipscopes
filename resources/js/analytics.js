// Google Analytics events (App\Support\Analytics). Does nothing unless the layout loaded gtag,
// which it does only when a Google Analytics ID is set in the site settings.
const data = window.__analytics ?? { events: [], context: {} };

export function track(name, params = {}) {
    if (typeof window.gtag !== 'function') return;
    window.gtag('event', name, { ...data.context, ...params });
}

// Events the server decided for this page (a search, a review sent before the redirect, …)
data.events.forEach(({ name, params }) => track(name, params));

// <a data-track="telegram_join" data-track-params='{"location":"home"}'>: sent on click
document.addEventListener('click', (event) => {
    const el = event.target.closest('[data-track]');
    if (!el) return;
    let params = {};
    try { params = JSON.parse(el.dataset.trackParams || '{}'); } catch {}
    track(el.dataset.track, params);
});
