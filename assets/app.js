(() => {
    const dialog = document.getElementById('apps-dialog');
    const opener = document.querySelector('[data-apps-open]');

    if (!(dialog instanceof HTMLDialogElement) || !(opener instanceof HTMLElement)) {
        return;
    }

    opener.addEventListener('click', () => dialog.showModal());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
})();
