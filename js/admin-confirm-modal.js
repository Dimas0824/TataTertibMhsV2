(function () {
    const modalElement = document.querySelector('[data-admin-confirm-modal]');
    if (!modalElement) {
        return;
    }

    const dialogElement = modalElement.querySelector('.admin-confirm-modal__dialog');
    const closeButtons = Array.from(modalElement.querySelectorAll('[data-admin-confirm-close]'));
    const confirmButton = modalElement.querySelector('[data-admin-confirm-confirm]');
    const titleElement = modalElement.querySelector('#adminConfirmTitle');
    const descElement = modalElement.querySelector('#adminConfirmDesc');

    if (!dialogElement || !confirmButton || !titleElement || !descElement) {
        return;
    }

    let activeElementBeforeOpen = null;
    let pendingAction = null;

    const getFocusableElements = () => Array.from(
        modalElement.querySelectorAll(
            'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )
    );

    const closeModal = () => {
        if (!modalElement.classList.contains('is-open')) {
            return;
        }

        modalElement.classList.remove('is-open');
        modalElement.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('admin-confirm-modal-open');

        if (activeElementBeforeOpen && typeof activeElementBeforeOpen.focus === 'function') {
            activeElementBeforeOpen.focus();
        }

        activeElementBeforeOpen = null;
        pendingAction = null;
    };

    const openModal = (triggerElement) => {
        activeElementBeforeOpen = document.activeElement instanceof HTMLElement ? document.activeElement : null;

        const title = String(triggerElement.getAttribute('data-admin-confirm-title') || 'Konfirmasi Aksi');
        const message = String(triggerElement.getAttribute('data-admin-confirm-message') || 'Apakah Anda yakin ingin melanjutkan aksi ini?');
        const confirmLabel = String(triggerElement.getAttribute('data-admin-confirm-label') || 'Ya, Lanjutkan');
        const action = String(triggerElement.getAttribute('data-admin-confirm-action') || 'submit-form');
        const target = String(triggerElement.getAttribute('data-admin-confirm-target') || '');
        const variantRaw = String(triggerElement.getAttribute('data-admin-confirm-variant') || 'danger').trim().toLowerCase();
        const variant = variantRaw === 'primary' ? 'primary' : 'danger';

        titleElement.textContent = title;
        descElement.textContent = message;
        confirmButton.textContent = confirmLabel;
        confirmButton.classList.remove('admin-confirm-modal__btn--danger', 'admin-confirm-modal__btn--primary');
        confirmButton.classList.add(variant === 'primary' ? 'admin-confirm-modal__btn--primary' : 'admin-confirm-modal__btn--danger');

        pendingAction = () => {
            if (action === 'navigate' && target !== '') {
                window.location.href = target;
                return;
            }

            const formElement = triggerElement.closest('form');
            if (!formElement) {
                return;
            }

            const triggerName = String(triggerElement.getAttribute('name') || '').trim();
            if (triggerName !== '') {
                const existingInput = formElement.querySelector('input[data-admin-confirm-injected="1"][name="' + triggerName + '"]');
                if (!existingInput) {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = triggerName;
                    hiddenInput.value = String(triggerElement.getAttribute('value') || '1');
                    hiddenInput.setAttribute('data-admin-confirm-injected', '1');
                    formElement.appendChild(hiddenInput);
                }
            }

            if (typeof formElement.requestSubmit === 'function') {
                formElement.requestSubmit();
                return;
            }

            formElement.submit();
        };

        modalElement.classList.add('is-open');
        modalElement.setAttribute('aria-hidden', 'false');
        document.body.classList.add('admin-confirm-modal-open');

        window.requestAnimationFrame(() => {
            const [firstElement] = getFocusableElements();
            if (firstElement) {
                firstElement.focus();
                return;
            }
            dialogElement.focus();
        });
    };

    document.addEventListener('click', (event) => {
        const triggerElement = event.target instanceof Element
            ? event.target.closest('[data-admin-confirm-trigger]')
            : null;

        if (!triggerElement) {
            return;
        }

        event.preventDefault();
        openModal(triggerElement);
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    confirmButton.addEventListener('click', () => {
        const action = pendingAction;
        closeModal();
        if (typeof action === 'function') {
            action();
        }
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
