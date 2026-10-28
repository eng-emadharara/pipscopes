// Maintenance page: the countdown (shared with the pre-launch page) and a quiet check that
// brings visitors back to the site as soon as maintenance is switched off.
import './countdown';

const page = document.querySelector('[data-maintenance]');

// Not in the admin preview: there the site is open, so the check would leave the preview at once
if (page && !page.dataset.preview) {
    const check = async () => {
        try {
            const response = await fetch(window.location.href, { method: 'HEAD', cache: 'no-store', credentials: 'same-origin' });
            if (response.status !== 503) window.location.reload();
        } catch {
            // Offline or the server is restarting: try again on the next round
        }
    };

    setInterval(check, 30000);
    // Coming back to the tab is a good moment to look again
    document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && check());
}
