(function () {
    const normalize = (value) => String(value || '').trim().replace(/\s+/g, ' ').toLowerCase();
    const sections = Array.from(document.querySelectorAll('[data-mobile-violation-section]'));
    const cards = Array.from(document.querySelectorAll('[data-mobile-card]'));
    if (cards.length === 0 && sections.length === 0) {
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

    const setupMobileStatusFilter = (section) => {
        if (!(section instanceof HTMLElement)) {
            return;
        }

        const tools = section.querySelector('[data-mobile-violation-tools]');
        const list = section.querySelector('[data-mobile-violation-list]');
        if (!(tools instanceof HTMLElement) || !(list instanceof HTMLElement)) {
            return;
        }

        const sectionCards = Array.from(list.querySelectorAll('[data-mobile-card]'));
        const buttons = Array.from(tools.querySelectorAll('[data-mobile-status-value]'));
        const searchInput = tools.querySelector('[data-mobile-search]');
        const selectFilters = Array.from(tools.querySelectorAll('select[data-mobile-filter-key]'));
        const visibleCountElement = tools.querySelector('[data-mobile-visible-count]');
        const totalCountElement = tools.querySelector('[data-mobile-total-count]');
        const emptyFilteredElement = list.querySelector('[data-mobile-empty-filtered]');

        if (totalCountElement instanceof HTMLElement) {
            totalCountElement.textContent = String(sectionCards.length);
        }

        if (sectionCards.length === 0) {
            if (visibleCountElement instanceof HTMLElement) {
                visibleCountElement.textContent = '0';
            }
            return;
        }

        if (buttons.length === 0) {
            if (visibleCountElement instanceof HTMLElement) {
                visibleCountElement.textContent = String(sectionCards.length);
            }
            return;
        }

        const initialActiveButton = buttons.find((button) => button.getAttribute('aria-pressed') === 'true') ?? buttons[0];
        let activeStatus = normalize(initialActiveButton?.getAttribute('data-mobile-status-value'));

        const applyStatusFilter = () => {
            const searchTerm = searchInput instanceof HTMLInputElement ? normalize(searchInput.value) : '';
            let visibleCount = 0;

            sectionCards.forEach((card) => {
                if (!(card instanceof HTMLElement)) {
                    return;
                }

                const cardStatus = normalize(card.getAttribute('data-mobile-card-status'));
                const statusMatched = activeStatus === '' || cardStatus === activeStatus;

                const cardSearchText = normalize(card.getAttribute('data-mobile-search'));
                const searchMatched = searchTerm === '' || cardSearchText.includes(searchTerm);

                const selectMatched = selectFilters.every((filterInput) => {
                    if (!(filterInput instanceof HTMLSelectElement)) {
                        return true;
                    }

                    const filterKey = normalize(filterInput.getAttribute('data-mobile-filter-key'));
                    if (filterKey === '') {
                        return true;
                    }

                    const expectedValue = normalize(filterInput.value);
                    if (expectedValue === '') {
                        return true;
                    }

                    const rowValue = normalize(card.getAttribute(`data-mobile-filter-${filterKey}`));
                    return rowValue === expectedValue;
                });

                const isVisible = statusMatched && searchMatched && selectMatched;
                card.hidden = !isVisible;

                if (!isVisible && activeCard === card) {
                    closeCard(card, { restoreFocus: false });
                }

                if (isVisible) {
                    visibleCount += 1;
                }
            });

            if (visibleCountElement instanceof HTMLElement) {
                visibleCountElement.textContent = String(visibleCount);
            }

            if (emptyFilteredElement instanceof HTMLElement) {
                emptyFilteredElement.hidden = visibleCount !== 0;
            }
        };

        buttons.forEach((button) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            const isInitiallyActive = button === initialActiveButton;
            button.classList.toggle('is-active', isInitiallyActive);
            button.setAttribute('aria-pressed', isInitiallyActive ? 'true' : 'false');

            button.addEventListener('click', () => {
                activeStatus = normalize(button.getAttribute('data-mobile-status-value'));

                buttons.forEach((candidate) => {
                    if (!(candidate instanceof HTMLButtonElement)) {
                        return;
                    }

                    const isActive = candidate === button;
                    candidate.classList.toggle('is-active', isActive);
                    candidate.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });

                applyStatusFilter();
            });
        });

        if (searchInput instanceof HTMLInputElement) {
            searchInput.addEventListener('input', applyStatusFilter);
        }

        selectFilters.forEach((filterInput) => {
            filterInput.addEventListener('change', applyStatusFilter);
        });

        applyStatusFilter();
    };

    sections.forEach(setupMobileStatusFilter);
})();
