// The loading bar at the top of the page (components/layouts/app): from leaving a page until the next one arrives.
// Listens on window, the last stop of an event, so anything handled by another script (dialogs, live filters,
// a declined confirm) has already called preventDefault by then and doesn't start it.

const bar = document.querySelector('[data-page-loader]');
const SHOW_AFTER_MS = 120; // A page that arrives at once shouldn't flash it
const GIVE_UP_AFTER_MS = 20000; // In case the browser stays on this page (a download, a failed connection)

if (bar) {
    let showTimer = null;
    let giveUpTimer = null;

    const stop = () => {
        clearTimeout(showTimer);
        clearTimeout(giveUpTimer);
        bar.classList.remove('is-loading');
    };

    const start = () => {
        stop();
        showTimer = setTimeout(() => bar.classList.add('is-loading'), SHOW_AFTER_MS);
        giveUpTimer = setTimeout(stop, GIVE_UP_AFTER_MS);
    };

    const leavesThePage = (link) => {
        if (link.target && link.target !== '_self') return false;
        if (link.hasAttribute('download') || link.origin !== window.location.origin) return false;

        // Only moving within this page
        return !(link.hash && link.pathname === window.location.pathname && link.search === window.location.search);
    };

    window.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

        const link = event.target.closest?.('a[href]');
        if (link && leavesThePage(link)) start();
    });

    window.addEventListener('submit', (event) => {
        if (!event.defaultPrevented && event.target.target !== '_blank') start();
    });

    // Back to a page the browser kept as it was
    window.addEventListener('pageshow', stop);
}
