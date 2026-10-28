// Profile page (resources/views/profile/edit.blade.php): photo preview, social link rows and the save bar.

const form = document.querySelector('[data-profile-form]');

if (form) {
    const images = form.querySelectorAll('[data-avatar-img]');
    const initials = form.querySelectorAll('[data-avatar-initials]');
    const fileInput = form.querySelector('[data-avatar-input]');
    const removeButton = form.querySelector('[data-avatar-remove]');
    const removeInput = form.querySelector('[data-avatar-remove-input]');
    const avatarError = form.querySelector('[data-avatar-error]');
    let previewUrl = null;

    const showAvatar = (src) => {
        images.forEach((img) => {
            if (src) img.src = src;
            img.classList.toggle('hidden', !src);
        });
        initials.forEach((el) => el.classList.toggle('hidden', !!src));
        removeButton.classList.toggle('hidden', !src);
    };

    const setAvatarError = (message) => {
        avatarError.textContent = message || '';
        avatarError.classList.toggle('hidden', !message);
    };

    fileInput.addEventListener('change', () => {
        const file = fileInput.files[0];
        if (!file) return;
        if (file.size > Number(fileInput.dataset.maxSize)) {
            fileInput.value = '';
            setAvatarError(fileInput.dataset.tooBig);
            return;
        }
        setAvatarError(null);
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = URL.createObjectURL(file);
        removeInput.value = '0';
        showAvatar(previewUrl);
    });

    removeButton.addEventListener('click', () => {
        fileInput.value = '';
        removeInput.value = '1';
        setAvatarError(null);
        showAvatar(null);
        markDirty();
    });

    // The name in the header card follows the field as it's typed
    const nameInput = form.querySelector('[data-name-input]');
    const namePreview = form.querySelector('[data-name-preview]');
    nameInput.addEventListener('input', () => {
        namePreview.textContent = nameInput.value.trim() || nameInput.defaultValue;
    });

    // Social links: a row per platform, shown when added and hidden (and emptied) when removed
    const emptyNote = form.querySelector('[data-social-empty]');
    const rows = [...form.querySelectorAll('[data-social-row]')];
    const syncEmpty = () => emptyNote.classList.toggle('hidden', rows.some((row) => !row.classList.contains('hidden')));

    form.querySelectorAll('[data-social-add]').forEach((chip) => {
        chip.addEventListener('click', () => {
            const row = form.querySelector(`[data-social-row="${chip.dataset.socialAdd}"]`);
            row.classList.remove('hidden');
            chip.classList.add('hidden');
            syncEmpty();
            row.querySelector('input').focus();
        });
    });

    rows.forEach((row) => {
        row.querySelector('[data-social-remove]').addEventListener('click', () => {
            const input = row.querySelector('input');
            input.value = '';
            row.classList.add('hidden');
            row.querySelector('.error')?.remove();
            form.querySelector(`[data-social-add="${row.dataset.socialRow}"]`).classList.remove('hidden');
            syncEmpty();
            markDirty();
        });
    });

    // Save bar: highlights once anything changes, and warns before leaving with unsaved changes
    const bar = form.querySelector('[data-save-bar]');
    const status = form.querySelector('[data-save-status]');
    const reset = form.querySelector('[data-save-reset]');
    let dirty = false;

    function markDirty() {
        if (dirty) return;
        dirty = true;
        bar.classList.add('ring-2', 'ring-brand-500/30', 'border-brand-500');
        status.textContent = status.dataset.dirtyText;
        status.classList.replace('text-gray-500', 'text-ink');
        status.classList.add('font-semibold');
        reset.classList.remove('hidden');
    }

    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);
    window.addEventListener('beforeunload', (event) => {
        if (dirty && !form.dataset.submitting) event.preventDefault();
    });

    // Came back from a failed submit: the values on screen aren't saved yet
    if (form.querySelector('.error:not(.hidden)')) markDirty();
}
