(() => {
    const dialog = document.getElementById('apps-dialog');
    const opener = document.querySelector('[data-apps-open]');

    if (dialog instanceof HTMLDialogElement && opener instanceof HTMLButtonElement) {
        opener.addEventListener('click', () => {
            dialog.showModal();
            opener.setAttribute('aria-expanded', 'true');
        });
        dialog.addEventListener('cancel', () => opener.focus());
        dialog.addEventListener('close', () => {
            opener.setAttribute('aria-expanded', 'false');
            opener.focus();
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    }

    const typeSelect = document.querySelector('[data-unit-type-select]');
    if (!(typeSelect instanceof HTMLSelectElement)) {
        return;
    }

    const fields = document.querySelectorAll('[data-unit-type-field]');
    const syncUnitTypeFields = () => {
        fields.forEach((field) => {
            if (field instanceof HTMLElement) {
                field.hidden = field.dataset.unitTypeField !== typeSelect.value;
            }
        });
    };

    typeSelect.addEventListener('change', syncUnitTypeFields);
    syncUnitTypeFields();
})();
