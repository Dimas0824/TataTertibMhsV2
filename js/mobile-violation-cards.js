(function () {
    const cards = Array.from(document.querySelectorAll('[data-mobile-card]'));
    if (cards.length === 0) {
        return;
    }

    let activeCard = null;
    let activeTrigger = null;

    const getCardFromElement = (element) => {
        if (!(element instanceof Element)) {
            return null;
        }
        return element.closest('[data-mobile-card]');
    };

    const getSheetFromCard = (card) => card?.querySelector('[data-mobile-sheet]') ?? null;
    const getTriggerFromCard = (card) => card?.querySelector('[data-mobile-card-open]') ?? null;
    const getPanelFromCard = (card) => card?.querySelector('.mobile-violation-sheet__panel') ?? null;

    const closeCard = (card, options = {}) => {
        if (!card) {
            return;
        }

        const { restoreFocus = true } = options;
        const sheet = getSheetFromCard(card);
        const trigger = getTriggerFromCard(card);

        if (sheet) {
            sheet.classList.remove('is-open');
            sheet.setAttribute('aria-hidden', 'true');
        }

        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        }

        if (restoreFocus && activeTrigger && typeof activeTrigger.focus === 'function') {
            activeTrigger.focus();
        }

        if (activeCard === card) {
            activeCard = null;
            activeTrigger = null;
            document.body.classList.remove('mobile-sheet-open');
        }
    };

    const openCard = (card) => {
        if (!card) {
            return;
        }

        if (activeCard === card) {
            closeCard(card);
            return;
        }

        if (activeCard) {
            closeCard(activeCard, { restoreFocus: false });
        }

        const sheet = getSheetFromCard(card);
        const trigger = getTriggerFromCard(card);
        const panel = getPanelFromCard(card);

        if (!sheet || !trigger) {
            return;
        }

        activeCard = card;
        activeTrigger = trigger;

        sheet.classList.add('is-open');
        sheet.setAttribute('aria-hidden', 'false');
        trigger.setAttribute('aria-expanded', 'true');
        document.body.classList.add('mobile-sheet-open');

        if (panel && typeof panel.focus === 'function') {
            panel.focus();
        }
    };

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const openButton = target.closest('[data-mobile-card-open]');
        if (openButton) {
            const card = getCardFromElement(openButton);
            openCard(card);
            return;
        }

        const closeButton = target.closest('[data-mobile-card-close]');
        if (closeButton) {
            const card = getCardFromElement(closeButton);
            closeCard(card);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !activeCard) {
            return;
        }

        event.preventDefault();
        closeCard(activeCard);
    });
})();
