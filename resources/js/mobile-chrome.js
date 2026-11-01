// Phones only. The site header leaves while reading down and comes back on the way up; on a channel page the
// write-a-review button stays reachable at the bottom once the one in the page has scrolled away.

const phone = window.matchMedia('(max-width: 47.99rem)');
const root = document.documentElement;
const typing = () => document.activeElement?.matches('input:not([type=checkbox],[type=radio]), textarea, select');

// --- Header ---
const header = document.querySelector('[data-site-header]');
const SETTLE_PX = 12; // Finger jitter shouldn't move it

if (header) {
    let last = Math.max(window.scrollY, 0);
    let travelled = 0;
    let scheduled = false;

    const setHidden = (hidden) => {
        header.classList.toggle('is-hidden', hidden);
        // Sticky toolbars sit right below the header while it's there
        root.style.setProperty('--site-header-offset', hidden ? '0px' : `${header.offsetHeight}px`);
    };

    const update = () => {
        scheduled = false;

        if (!phone.matches) {
            header.classList.remove('is-hidden');
            root.style.removeProperty('--site-header-offset');
            return;
        }

        const y = Math.max(window.scrollY, 0); // iOS bounces past the top
        const delta = y - last;
        last = y;

        if (y <= header.offsetHeight) { travelled = 0; setHidden(false); return; }
        if (typing()) { setHidden(true); return; } // The keyboard leaves little room already
        if (header.querySelector('details[open]')) return; // Its menu is in use

        travelled = Math.sign(delta) === Math.sign(travelled) ? travelled + delta : delta;
        if (Math.abs(travelled) >= SETTLE_PX) setHidden(travelled > 0);
    };

    const schedule = () => {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(update);
    };

    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);
    document.addEventListener('focusin', schedule);
    update();
}

// --- Write-a-review bar ---
const bar = document.querySelector('[data-review-bar]');
const anchor = document.querySelector('[data-review-anchor]');

if (bar && anchor && 'IntersectionObserver' in window) {
    const footer = document.querySelector('footer');
    let anchorInView = true;
    let footerInView = false;

    const refresh = () => bar.classList.toggle('is-shown', !anchorInView && !footerInView && !typing());

    new IntersectionObserver(([entry]) => { anchorInView = entry.isIntersecting; refresh(); }).observe(anchor);
    if (footer) new IntersectionObserver(([entry]) => { footerInView = entry.isIntersecting; refresh(); }).observe(footer);

    document.addEventListener('focusin', refresh);
    document.addEventListener('focusout', () => setTimeout(refresh));
}
