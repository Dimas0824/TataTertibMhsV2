(function () {
    const modalElement = document.getElementById('appFeedbackModal');
    if (!modalElement) {
        return;
    }

    const dialogElement = modalElement.querySelector('.app-modal__dialog');
    const titleElement = modalElement.querySelector('[data-app-modal-title]');
    const messageElement = modalElement.querySelector('[data-app-modal-message]');
    const badgeElement = modalElement.querySelector('[data-app-modal-icon]');

    const titleByType = {
        success: 'Berhasil',
        error: 'Gagal',
        warning: 'Perhatian',
        info: 'Informasi',
    };

    const iconByType = {
        success: '✓',
        error: '!',
        warning: '!',
        info: 'i',
    };

    const normalizeType = (type) => {
        const normalized = String(type || '').toLowerCase();
        return ['success', 'error', 'warning', 'info'].includes(normalized) ? normalized : 'info';
    };

    const clearTypeClass = () => {
        modalElement.classList.remove(
            'app-modal--success',
            'app-modal--error',
            'app-modal--warning',
            'app-modal--info'
        );
    };

    const hide = () => {
        modalElement.classList.remove('is-open');
        modalElement.setAttribute('aria-hidden', 'true');
    };

    const show = (payload) => {
        const type = normalizeType(payload && payload.type);
        const title = payload && payload.title ? String(payload.title) : titleByType[type];
        const message = payload && payload.message ? String(payload.message) : '';

        clearTypeClass();
        modalElement.classList.add(`app-modal--${type}`);
        titleElement.textContent = title;
        messageElement.textContent = message;
        badgeElement.textContent = iconByType[type] || 'i';

        modalElement.classList.add('is-open');
        modalElement.setAttribute('aria-hidden', 'false');
    };

    modalElement.querySelectorAll('[data-app-modal-close]').forEach((button) => {
        button.addEventListener('click', hide);
    });

    modalElement.addEventListener('click', (event) => {
        if (event.target === modalElement) {
            hide();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modalElement.classList.contains('is-open')) {
            hide();
        }
    });

    window.AppModal = {
        show,
        hide,
    };

    if (window.__APP_FLASH_MODAL) {
        show(window.__APP_FLASH_MODAL);
        delete window.__APP_FLASH_MODAL;
    }
})();
