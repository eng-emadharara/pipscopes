// Browse page filters (resources/views/providers/index.blade.php): every change applies at once.
// The page is fetched again and its live regions ([data-live-region]) are swapped in place, so
// the results, the counts and the active-filter chips stay in step; the address follows along.
import { track } from './analytics';

const form = document.querySelector('form[data-live-filters]');

if (form) {
    const panel = document.querySelector('[data-filters-panel]');
    const search = document.querySelector('[data-filter-search]');
    let timer;
    let controller;

    const url = () => {
        const params = new URLSearchParams();
        for (const [name, value] of new FormData(form)) {
            if (value !== '') params.append(name, value);
        }
        const query = params.toString();
        return form.action + (query ? `?${query}` : '');
    };

    // Placeholder cards while new results load; only after a short wait, so fast answers don't flicker
    const skeleton = document.querySelector('template[data-skeleton]');
    const showSkeleton = () => {
        const grid = document.querySelector('[data-results-grid]');
        if (!grid || !skeleton) return;
        grid.className = 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4';
        grid.replaceChildren(skeleton.content.cloneNode(true));
    };

    const update = async () => {
        clearTimeout(timer);
        controller?.abort();
        controller = new AbortController();

        const target = url();
        const loading = setTimeout(showSkeleton, 150);

        try {
            const response = await fetch(target, { signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const html = new DOMParser().parseFromString(await response.text(), 'text/html');

            document.querySelectorAll('[data-live-region]').forEach((region) => {
                const fresh = html.querySelector(`[data-live-region="${region.dataset.liveRegion}"]`);
                if (fresh) {
                    // The categories strip scrolls sideways: keep where it was
                    const scroll = region.scrollLeft;
                    region.innerHTML = fresh.innerHTML;
                    region.className = fresh.className;
                    region.scrollLeft = scroll;
                }
            });
            updateArrows();
            document.title = html.title || document.title;
            history.replaceState(null, '', response.url || target);

            // The fetched page's analytics events (a search, …) would otherwise never be sent
            const data = [...html.scripts].map((s) => s.textContent.match(/window\.__analytics = (\{.*\});/s)).find(Boolean);
            if (data) JSON.parse(data[1]).events.forEach(({ name, params }) => track(name, params));
        } catch (error) {
            if (error.name !== 'AbortError') window.location = target; // fall back to a normal load
        } finally {
            clearTimeout(loading);
        }
    };

    // Categories strip: arrows on desktop when the tabs don't all fit
    const scroller = document.querySelector('[data-strip-scroller]');
    const arrows = document.querySelectorAll('[data-strip-scroll]');
    function updateArrows() {
        if (!scroller) return;
        const max = scroller.scrollWidth - scroller.clientWidth;
        // scrollLeft is 0 at the start and negative towards the end in right-to-left pages
        const pos = Math.abs(scroller.scrollLeft);
        arrows.forEach((arrow) => {
            const toEnd = arrow.dataset.stripScroll === '1';
            const show = window.matchMedia('(min-width: 1024px)').matches && max > 4 && (toEnd ? pos < max - 4 : pos > 4);
            arrow.classList.toggle('hidden', !show);
            arrow.classList.toggle('flex', show);
        });
    }
    arrows.forEach((arrow) => arrow.addEventListener('click', () => {
        const rtl = getComputedStyle(scroller).direction === 'rtl';
        scroller.scrollBy({ left: Number(arrow.dataset.stripScroll) * (rtl ? -1 : 1) * scroller.clientWidth * 0.7, behavior: 'smooth' });
    }));
    scroller?.addEventListener('scroll', updateArrows, { passive: true });
    window.addEventListener('resize', updateArrows);
    updateArrows();


    // Checkboxes, radios and selects (also those outside the form tag, linked with form="…")
    document.addEventListener('change', (event) => {
        if (event.target.form === form && event.target !== search) update();
    });

    // Typing: wait for a pause; Enter: right away
    search?.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(update, 450);
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        update();
        search?.blur(); // closes the phone keyboard
    });

    // Desktop dropdown buttons (markets, rating): one open at a time, closed by a click outside or Esc
    const closePopovers = (except = null) => document.querySelectorAll('[data-popover][data-open]').forEach((pop) => {
        if (pop === except) return;
        pop.removeAttribute('data-open');
        pop.querySelector('[data-popover-toggle]')?.setAttribute('aria-expanded', 'false');
    });
    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-popover-toggle]');
        if (toggle) {
            const pop = toggle.closest('[data-popover]');
            const opening = !pop.hasAttribute('data-open');
            closePopovers(pop);
            pop.toggleAttribute('data-open', opening);
            toggle.setAttribute('aria-expanded', String(opening));
            return;
        }
        if (!event.target.closest('[data-popover]')) closePopovers();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closePopovers();
    });

    // Active-filter chips: undo one filter
    document.addEventListener('click', (event) => {
        // "All" in a group (the categories tabs): untick every option of it
        const group = event.target.closest('[data-clear-group]');
        if (group) {
            [...form.elements].filter((el) => el.name === group.dataset.clearGroup).forEach((el) => { el.checked = false; });
            update();
            return;
        }

        // Phones' sheet: a category chip picks the matching tab of the strip
        const pick = event.target.closest('[data-pick-category]');
        if (pick) {
            const radio = [...form.elements].find((el) => el.name === 'categories[]' && el.value === pick.dataset.pickCategory);
            if (radio && !radio.checked) {
                radio.checked = true;
                update();
            }
            return;
        }

        const chip = event.target.closest('[data-clear-filter]');
        if (chip) {
            const { name, value } = chip.dataset;
            [...form.elements].filter((el) => el.name === name).forEach((el) => {
                if (el.type === 'radio') el.checked = el.value === '';
                else if (el.value === value) el.checked = false;
            });
            update();
            return;
        }

        // "Clear all": every filter off (the search words stay)
        const clearAll = event.target.closest('[data-clear-all]');
        if (clearAll) {
            event.preventDefault();
            [...form.elements].forEach((el) => {
                if (el.type === 'checkbox') el.checked = false;
                if (el.type === 'radio') el.checked = el.value === '';
                if (el.tagName === 'SELECT') el.selectedIndex = 0;
            });
            update();
        }
    });

    // Phones: the filters sheet
    const open = () => {
        panel.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    };
    const close = () => {
        panel.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    };
    document.querySelectorAll('[data-open-filters]').forEach((button) => button.addEventListener('click', open));
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-close-filters]')) close();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.classList.contains('hidden')) close();
    });
}
