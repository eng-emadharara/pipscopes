import './bootstrap';
import './autocomplete';
import './countdown';
import './review-feedback';
import './share';
import './account-check';
import './submission-form';
import './analytics';
import './provider-filters';
import './ui';
import './profile';

// Prevent double submission (double-click / Enter + click) on every form
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (event.defaultPrevented || form.dataset.submitting) {
        if (form.dataset.submitting) event.preventDefault();
        return;
    }
    form.dataset.submitting = '1';
    // Disable after the browser has collected the form data
    setTimeout(() => {
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach((btn) => {
            btn.disabled = true;
            btn.classList.add('opacity-60', 'cursor-wait');
        });
    }, 0);
});

// <div data-show-when="field=value"> is visible only while the named field has that value
document.addEventListener('change', (event) => {
    if (!event.target.name) return;
    document.querySelectorAll('[data-show-when]').forEach((el) => {
        const [field, value] = el.dataset.showWhen.split('=');
        if (field === event.target.name) el.classList.toggle('hidden', event.target.value !== value);
    });
});

// After a failed submit, bring the first validation error into view (forms can sit far down the page)
document.querySelector('p.error')?.closest('div, fieldset')?.scrollIntoView({ block: 'center' });

// Re-enable forms when the page is restored from the back/forward cache
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    document.querySelectorAll('form[data-submitting]').forEach((form) => {
        delete form.dataset.submitting;
        form.querySelectorAll('button').forEach((btn) => {
            btn.disabled = false;
            btn.classList.remove('opacity-60', 'cursor-wait');
        });
    });
});
