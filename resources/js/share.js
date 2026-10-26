// Share menus (resources/views/components/share-buttons.blade.php)

// The phone's native share sheet, where the browser supports it
if (navigator.share) {
    document.querySelectorAll('[data-native-share]').forEach((btn) => {
        btn.classList.remove('hidden');
        btn.classList.add('flex');
        btn.addEventListener('click', () => {
            navigator.share({ text: btn.dataset.text, url: btn.dataset.url }).catch(() => {});
            btn.closest('details')?.removeAttribute('open');
        });
    });
}

document.querySelectorAll('[data-copy-url]').forEach((btn) => {
    btn.addEventListener('click', async () => {
        const label = btn.querySelector('span');
        const original = label.textContent;
        try {
            await navigator.clipboard.writeText(btn.dataset.copyUrl);
        } catch {
            // Older browsers / insecure origins: select the text in a temporary field
            const field = Object.assign(document.createElement('textarea'), { value: btn.dataset.copyUrl });
            document.body.append(field);
            field.select();
            document.execCommand('copy');
            field.remove();
        }
        label.textContent = btn.dataset.copiedLabel;
        setTimeout(() => { label.textContent = original; }, 2000);
    });
});

// Close an open menu when clicking elsewhere
document.addEventListener('click', (event) => {
    document.querySelectorAll('details[data-share][open]').forEach((menu) => {
        if (!menu.contains(event.target)) menu.removeAttribute('open');
    });
});
