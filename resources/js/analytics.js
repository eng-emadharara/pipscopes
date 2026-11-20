// Google Analytics events (App\Support\Analytics). Does nothing unless the layout loaded gtag,
// which it does only when a Google Analytics ID is set in the site settings.
// The events below are also sent to the Meta Pixel when one is installed (Settings → head_code), so ads
// can be measured and optimised on them ("Lead" = a scam report sent).
const data = window.__analytics ?? { events: [], context: {} };

const META = {
    scam_help_view: ['track', 'ViewContent'],
    scam_report_start: ['trackCustom', 'ScamReportStart'],
    scam_report_form: ['trackCustom', 'ScamReportForm'],
    scam_report_form_start: ['trackCustom', 'ScamReportFormStart'],
    scam_report_submit: ['track', 'Lead'],
    sign_up: ['track', 'CompleteRegistration'],
};

export function track(name, params = {}) {
    if (typeof window.gtag === 'function') window.gtag('event', name, { ...data.context, ...params });
    if (META[name] && typeof window.fbq === 'function') window.fbq(...META[name], params);
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

// <form data-track-start="scam_report_form_start">: sent once, the first time someone types or picks something in it
document.querySelectorAll('[data-track-start]').forEach((form) => {
    const start = () => track(form.dataset.trackStart);
    form.addEventListener('input', start, { once: true });
});
