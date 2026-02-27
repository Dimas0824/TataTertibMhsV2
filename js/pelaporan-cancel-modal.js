(function () {
    const modalElement = document.querySelector('[data-cancel-report-modal]');
    const openButtons = Array.from(document.querySelectorAll('[data-open-cancel-report-modal]'));

    if (!modalElement || openButtons.length === 0) {
        return;
    }

    const dialogElement = modalElement.querySelector('.cancel-report-modal__dialog');
    const closeButtons = Array.from(modalElement.querySelectorAll('[data-cancel-report-close]'));
    const confirmButton = modalElement.querySelector('[data-cancel-report-confirm]');
    const redirectHref = String(modalElement.getAttribute('data-redirect-href') || 'pelanggaran_dosen.php');

    if (!dialogElement || !confirmButton) {
        return;
    }

    let activeElementBeforeOpen = null;

    const getFocusableElements = () => Array.from(
        modalElement.querySelectorAll(
            'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )
    );

    const openModal = () => {
        activeElementBeforeOpen = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        modalElement.classList.add('is-open');
        modalElement.setAttribute('aria-hidden', 'false');
        document.body.classList.add('cancel-report-modal-open');

        window.requestAnimationFrame(() => {
            const [firstElement] = getFocusableElements();
            if (firstElement) {
                firstElement.focus();
                return;
            }
            dialogElement.focus();
        });
    };

    const closeModal = () => {
        if (!modalElement.classList.contains('is-open')) {
            return;
        }

        modalElement.classList.remove('is-open');
        modalElement.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('cancel-report-modal-open');

        if (activeElementBeforeOpen && typeof activeElementBeforeOpen.focus === 'function') {
            activeElementBeforeOpen.focus();
        }
        activeElementBeforeOpen = null;
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', openModal);
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    confirmButton.addEventListener('click', () => {
        window.location.href = redirectHref;
    });

    document.addEventListener('keydown', (event) => {
        if (!modalElement.classList.contains('is-open')) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusableElements = getFocusableElements();
        if (focusableElements.length === 0) {
            event.preventDefault();
            dialogElement.focus();
            return;
        }

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (event.shiftKey && document.activeElement === firstElement) {
            event.preventDefault();
            lastElement.focus();
            return;
        }

        if (!event.shiftKey && document.activeElement === lastElement) {
            event.preventDefault();
            firstElement.focus();
        }
    });
})();
