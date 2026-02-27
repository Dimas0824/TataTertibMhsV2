(function () {
    const modalElement = document.getElementById('appFeedbackModal');
    if (!modalElement) {
        return;
    }

    const dialogElement = modalElement.querySelector('.app-modal__dialog');
    const titleElement = modalElement.querySelector('[data-app-modal-title]');
    const messageElement = modalElement.querySelector('[data-app-modal-message]');
    const iconElement = modalElement.querySelector('[data-app-modal-icon]');
    const toneElement = modalElement.querySelector('[data-app-modal-tone]');
    const confirmButton = modalElement.querySelector('[data-app-modal-confirm]');
    const closeButtons = Array.from(modalElement.querySelectorAll('[data-app-modal-close]'));

    if (!dialogElement || !titleElement || !messageElement || !iconElement || !toneElement || !confirmButton) {
        return;
    }

    const variantClassNames = [
        'app-modal--success',
        'app-modal--error',
        'app-modal--warning',
        'app-modal--info',
    ];

    const variants = {
        success: {
            title: 'Berhasil',
            tone: 'Sukses',
            icon: '✓',
            buttonLabel: 'Lanjutkan',
        },
        error: {
            title: 'Terjadi Kesalahan',
            tone: 'Error',
            icon: '!',
            buttonLabel: 'Tutup',
        },
        warning: {
            title: 'Perhatian',
            tone: 'Peringatan',
            icon: '!',
            buttonLabel: 'Mengerti',
        },
        info: {
            title: 'Informasi',
            tone: 'Info',
            icon: 'i',
            buttonLabel: 'Baik',
        },
    };

    let activeElementBeforeOpen = null;

    const normalizeType = (type) => {
        const normalized = String(type || '').toLowerCase();
        return Object.prototype.hasOwnProperty.call(variants, normalized) ? normalized : 'info';
    };

    const clearVariantClass = () => {
        modalElement.classList.remove(...variantClassNames);
    };

    const focusFirstElement = () => {
        window.requestAnimationFrame(() => {
            const focusable = modalElement.querySelector(
                'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );
            if (focusable && typeof focusable.focus === 'function') {
                focusable.focus();
                return;
            }
            dialogElement.focus();
        });
    };

    const hide = () => {
        if (!modalElement.classList.contains('is-open')) {
            return;
        }

        modalElement.classList.remove('is-open');
        modalElement.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('app-modal-open');

        if (activeElementBeforeOpen && typeof activeElementBeforeOpen.focus === 'function') {
            activeElementBeforeOpen.focus();
        }
        activeElementBeforeOpen = null;
    };

    const show = (payload) => {
        const type = normalizeType(payload && payload.type);
        const variant = variants[type];

        activeElementBeforeOpen = document.activeElement instanceof HTMLElement ? document.activeElement : null;

        clearVariantClass();
        modalElement.classList.add(`app-modal--${type}`);

        titleElement.textContent = payload && payload.title ? String(payload.title) : variant.title;
        toneElement.textContent = variant.tone;
        messageElement.textContent = payload && payload.message ? String(payload.message) : '';
        iconElement.textContent = variant.icon;
        confirmButton.textContent = variant.buttonLabel;

        modalElement.classList.add('is-open');
        modalElement.setAttribute('aria-hidden', 'false');
        document.body.classList.add('app-modal-open');

        focusFirstElement();
    };

    const handleKeydown = (event) => {
        if (!modalElement.classList.contains('is-open')) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            hide();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusableElements = Array.from(
            modalElement.querySelectorAll(
                'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )
        );

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
    };

    closeButtons.forEach((button) => {
        button.addEventListener('click', hide);
    });

    document.addEventListener('keydown', handleKeydown);

    window.AppModal = {
        show,
        hide,
    };

    if (window.__APP_FLASH_MODAL) {
        show(window.__APP_FLASH_MODAL);
        delete window.__APP_FLASH_MODAL;
    }
})();
