(() => {
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const addButton = target.closest('[data-household-add]');
        if (addButton instanceof HTMLButtonElement) {
            const kind = addButton.dataset.householdAdd;
            const list = document.querySelector(`[data-household-list="${kind}"]`);
            const template = document.querySelector(`template[data-household-template="${kind}"]`);
            if (!(list instanceof HTMLElement) || !(template instanceof HTMLTemplateElement)) {
                return;
            }
            if (list.querySelectorAll('[data-household-entry]').length >= 25) {
                return;
            }
            const index = Number.parseInt(list.dataset.nextIndex || '0', 10);
            const row = document.createElement('template');
            row.innerHTML = template.innerHTML.replace(/__INDEX__/g, String(Number.isInteger(index) ? index : 0));
            list.append(row.content);
            list.dataset.nextIndex = String((Number.isInteger(index) ? index : 0) + 1);
            list.lastElementChild?.querySelector('input:not([type="hidden"])')?.focus();
            return;
        }

        const removeButton = target.closest('[data-household-remove]');
        if (removeButton instanceof HTMLButtonElement) {
            const row = removeButton.closest('[data-household-entry]');
            if (!(row instanceof HTMLElement)) {
                return;
            }
            if (row.dataset.persisted === '1' && !window.confirm('¿Retirar este registro del hogar?')) {
                return;
            }
            row.remove();
        }
    });

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
