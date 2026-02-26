document.addEventListener('DOMContentLoaded', () => {
  const root = document.body;
  const toggle = document.querySelector('[data-nav-toggle]');
  const storageKey = 'disciplink.sidebar.pinned';
  const storage = {
    get() {
      try {
        return localStorage.getItem(storageKey);
      } catch (error) {
        return null;
      }
    },
    set(value) {
      try {
        localStorage.setItem(storageKey, value);
      } catch (error) {
        // Ignore storage errors in private browsing contexts.
      }
    }
  };

  if (!toggle) {
    return;
  }

  const toggleIcon = toggle.querySelector('i');

  const applyPinnedState = (isPinned) => {
    root.classList.toggle('app-shell--rail-pinned', isPinned);
    toggle.setAttribute('aria-pressed', isPinned ? 'true' : 'false');
    toggle.setAttribute('aria-label', isPinned ? 'Unpin sidebar' : 'Pin sidebar');
    if (toggleIcon) {
      toggleIcon.className = isPinned
        ? 'fa-solid fa-chevron-left'
        : 'fa-solid fa-chevron-right';
    }
  };

  const isDesktop = () => window.matchMedia('(min-width: 901px)').matches;

  const syncDesktopState = () => {
    if (isDesktop()) {
      const pinned = storage.get() === 'true';
      applyPinnedState(pinned);
      toggle.disabled = false;
    } else {
      applyPinnedState(false);
      toggle.disabled = true;
    }
  };

  toggle.addEventListener('click', () => {
    if (!isDesktop()) {
      return;
    }

    const nextState = !root.classList.contains('app-shell--rail-pinned');
    storage.set(nextState ? 'true' : 'false');
    applyPinnedState(nextState);
  });

  window.addEventListener('resize', syncDesktopState);
  syncDesktopState();
});
