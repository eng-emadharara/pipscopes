// Small UI behaviours shared by Blade views: dialogs, tabs and "add another row" buttons.

// <button data-open-dialog="id"> opens <dialog id="id">; [data-close-dialog] closes the dialog it's in.
// A dialog with data-open-on-load opens immediately (e.g. after a failed submit).
document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-open-dialog]');
    if (opener) document.getElementById(opener.dataset.openDialog)?.showModal();

    const closer = event.target.closest('[data-close-dialog]');
    if (closer) closer.closest('dialog')?.close();
});

document.querySelectorAll('dialog[data-open-on-load]').forEach((dialog) => dialog.showModal());

// Clicking the dark backdrop closes a dialog too
document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
});

// [data-tabs] with buttons [data-tab="x"] and panels [data-panel="x"].
// A panel containing a validation error is shown first, then the one holding the URL's #target.
// Tabs may be links (?tab=x) that work without JS; with data-tabs-url the address follows the tab.
document.querySelectorAll('[data-tabs]').forEach((group) => {
    const buttons = [...group.querySelectorAll('[data-tab]')];
    const panels = [...group.querySelectorAll('[data-panel]')];

    const select = (key) => {
        buttons.forEach((b) => b.setAttribute('aria-selected', b.dataset.tab === key ? 'true' : 'false'));
        panels.forEach((p) => p.classList.toggle('hidden', p.dataset.panel !== key));
    };

    buttons.forEach((b) => b.addEventListener('click', (event) => {
        event.preventDefault();
        select(b.dataset.tab);
        if ('tabsUrl' in group.dataset && b.href) history.replaceState(null, '', b.href.split('#')[0]);
    }));

    const withError = panels.find((p) => p.querySelector('.error'));
    let target = null;
    try { target = location.hash && document.querySelector(location.hash); } catch {}
    const withTarget = target && panels.find((p) => p.contains(target));

    if (withError) select(withError.dataset.panel);
    else if (withTarget) {
        select(withTarget.dataset.panel);
        target.scrollIntoView({ block: 'start' });
    }
});

// [data-clamp] text is cut to a few lines; its [data-clamp-toggle] button appears only when
// something is actually hidden, and shows the rest
document.querySelectorAll('[data-clamp]').forEach((text) => {
    const button = text.parentElement.querySelector('[data-clamp-toggle]');
    if (!button) return;
    const check = () => button.classList.toggle('hidden', text.scrollHeight <= text.clientHeight + 1);
    check();
    // Text inside a hidden tab has no height yet: check again once it's shown
    new ResizeObserver(check).observe(text);
    button.addEventListener('click', () => {
        text.classList.remove(...text.dataset.clamp.split(' '));
        button.remove();
    });
});

// <button data-show-next="selector"> reveals the first hidden element matching the selector
document.querySelectorAll('[data-show-next]').forEach((button) => {
    button.addEventListener('click', () => {
        const next = document.querySelector(button.dataset.showNext);
        next?.classList.remove('hidden');
        if (!document.querySelector(button.dataset.showNext)) button.remove();
    });
});

// <textarea data-count="20"> shows its length in [data-count-for="its id"]: how many characters are
// still needed (data-short) until the minimum, then the count out of the maximum (data-ok). ":n" is the number.
document.querySelectorAll('textarea[data-count]').forEach((field) => {
    const output = document.querySelector(`[data-count-for="${field.id}"]`);
    if (!output) return;
    const min = Number(field.dataset.count);
    const update = () => {
        const length = field.value.trim().length;
        const short = length < min;
        output.textContent = (short ? output.dataset.short : output.dataset.ok).replace(':n', short ? min - length : length);
        output.classList.toggle('text-amber-600', short && length > 0);
    };
    field.addEventListener('input', update);
    update();
});
