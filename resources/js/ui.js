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
// A panel containing a validation error is shown first.
document.querySelectorAll('[data-tabs]').forEach((group) => {
    const buttons = [...group.querySelectorAll('[data-tab]')];
    const panels = [...group.querySelectorAll('[data-panel]')];

    const select = (key) => {
        buttons.forEach((b) => b.setAttribute('aria-selected', b.dataset.tab === key ? 'true' : 'false'));
        panels.forEach((p) => p.classList.toggle('hidden', p.dataset.panel !== key));
    };

    buttons.forEach((b) => b.addEventListener('click', () => select(b.dataset.tab)));

    const withError = panels.find((p) => p.querySelector('.error'));
    if (withError) select(withError.dataset.panel);
});

// <button data-show-next="selector"> reveals the first hidden element matching the selector
document.querySelectorAll('[data-show-next]').forEach((button) => {
    button.addEventListener('click', () => {
        const next = document.querySelector(button.dataset.showNext);
        next?.classList.remove('hidden');
        if (!document.querySelector(button.dataset.showNext)) button.remove();
    });
});
