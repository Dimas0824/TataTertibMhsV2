document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('[data-notif-root]');
  if (!root) {
    return;
  }

  const endpoint = root.dataset.endpoint || '';
  const searchInput = document.getElementById('notifSearchInput');
  const filterButtons = Array.from(root.querySelectorAll('.notif-filter-btn'));
  const markAllButton = root.querySelector('[data-action="mark-all-read"]');
  const list = document.getElementById('notifList');
  const emptyServer = document.getElementById('notifEmptyServer');
  const emptyFiltered = document.getElementById('notifEmptyFiltered');
  const visibleCounter = root.querySelector('[data-counter-visible]');
  const activeFilterLabel = root.querySelector('[data-active-filter-label]');
  const unreadHighlight = root.querySelector('[data-unread-highlight]');
  const filterCountBadges = Array.from(root.querySelectorAll('[data-filter-count]'));

  if (!list) {
    return;
  }

  const counters = {
    total: root.querySelector('[data-counter="total"]'),
    unread: root.querySelector('[data-counter="unread"]'),
    read: root.querySelector('[data-counter="read"]')
  };

  const filterLabels = {
    all: 'Semua',
    unread: 'Unread',
    read: 'Read'
  };

  const state = {
    notifications: [],
    currentFilter: 'all',
    loading: false
  };

  const showFeedback = (type, message) => {
    if (window.AppModal && typeof window.AppModal.show === 'function') {
      window.AppModal.show({ type, message });
      return;
    }

    if (type === 'error' && message) {
      alert(message);
    }
  };

  const normalizeStatus = (value) => (String(value || '').toLowerCase() === 'read' ? 'read' : 'unread');
  const normalizeText = (value) => String(value || '').trim().replace(/\s+/g, ' ').toLowerCase();

  const escapeHtml = (text) => {
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  };

  const getNotifCategory = (message) => {
    const normalized = normalizeText(message);

    if (normalized.includes('selesai') || normalized.includes('dikonfirmasi')) {
      return {
        key: 'complete',
        label: 'Status Selesai',
        icon: 'fa-solid fa-circle-check'
      };
    }

    if (normalized.includes('upload') || normalized.includes('unggah') || normalized.includes('surat') || normalized.includes('tugas')) {
      return {
        key: 'document',
        label: 'Dokumen',
        icon: 'fa-solid fa-file-arrow-up'
      };
    }

    if (normalized.includes('lapor') || normalized.includes('pelanggaran')) {
      return {
        key: 'report',
        label: 'Laporan Baru',
        icon: 'fa-solid fa-triangle-exclamation'
      };
    }

    return {
      key: 'info',
      label: 'Informasi',
      icon: 'fa-solid fa-circle-info'
    };
  };

  const sendRequest = async (payload) => {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    });

    let data = null;
    try {
      data = await response.json();
    } catch (error) {
      throw new Error('Respons server tidak valid.');
    }

    if (!response.ok || !data || data.success !== true) {
      throw new Error((data && data.message) || 'Gagal memproses notifikasi.');
    }

    return data;
  };

  const getFilteredNotifications = () => {
    const query = normalizeText(searchInput?.value || '');

    return state.notifications.filter((item) => {
      const status = normalizeStatus(item.status);
      const message = normalizeText(item.pesan || '');
      const passFilter = state.currentFilter === 'all' || status === state.currentFilter;
      const passQuery = query === '' || message.includes(query);

      return passFilter && passQuery;
    });
  };

  const setMarkAllButtonLabel = (unread) => {
    if (!markAllButton) {
      return;
    }

    const label = unread > 0 ? `Tandai semua dibaca (${unread})` : 'Tandai semua dibaca';
    markAllButton.innerHTML = `<i class="fa-solid fa-check-double" aria-hidden="true"></i><span>${escapeHtml(label)}</span>`;
  };

  const renderFilterCountBadges = () => {
    const total = state.notifications.length;
    const unread = state.notifications.filter((item) => normalizeStatus(item.status) === 'unread').length;
    const read = total - unread;

    filterCountBadges.forEach((badge) => {
      const key = String(badge.getAttribute('data-filter-count') || '');
      if (key === 'all') {
        badge.textContent = String(total);
      } else if (key === 'unread') {
        badge.textContent = String(unread);
      } else if (key === 'read') {
        badge.textContent = String(read);
      }
    });
  };

  const renderCounters = () => {
    const total = state.notifications.length;
    const unread = state.notifications.filter((item) => normalizeStatus(item.status) === 'unread').length;
    const read = total - unread;

    if (counters.total) counters.total.textContent = String(total);
    if (counters.unread) counters.unread.textContent = String(unread);
    if (counters.read) counters.read.textContent = String(read);
    if (unreadHighlight) unreadHighlight.textContent = String(unread);

    renderFilterCountBadges();
    setMarkAllButtonLabel(unread);

    if (markAllButton) {
      markAllButton.disabled = unread === 0 || state.loading;
    }
  };

  const renderList = () => {
    const filtered = getFilteredNotifications();

    if (visibleCounter) {
      visibleCounter.textContent = String(filtered.length);
    }

    if (activeFilterLabel) {
      activeFilterLabel.textContent = filterLabels[state.currentFilter] || 'Semua';
    }

    if (state.notifications.length === 0) {
      list.innerHTML = '';
      emptyServer?.classList.remove('is-hidden');
      emptyFiltered?.classList.add('is-hidden');
      return;
    }

    emptyServer?.classList.add('is-hidden');

    if (filtered.length === 0) {
      list.innerHTML = '';
      emptyFiltered?.classList.remove('is-hidden');
      return;
    }

    emptyFiltered?.classList.add('is-hidden');

    const html = filtered
      .map((item) => {
        const status = normalizeStatus(item.status);
        const statusLabel = status === 'read' ? 'Read' : 'Unread';
        const message = escapeHtml(item.pesan || 'Notifikasi baru');
        const id = escapeHtml(String(item.id_notifikasi || ''));
        const category = getNotifCategory(item.pesan || '');

        const actionHtml = status === 'unread'
          ? `<button type="button" class="notif-inline-btn" data-action="mark-read" data-id="${id}">Tandai dibaca</button>`
          : '<span class="notif-read-note">Sudah dibaca</span>';

        return `
          <article
            class="notification-card ${status === 'unread' ? 'is-unread' : 'is-read'} type-${category.key}"
            data-id="${id}"
            data-status="${status}"
            role="listitem"
            tabindex="0"
            aria-label="Notifikasi ${statusLabel}">
            <div class="notif-icon" aria-hidden="true">
              <i class="${category.icon}"></i>
            </div>
            <div class="notification-content">
              <div class="notification-meta">
                <span class="notif-topic">${category.label}</span>
                <span class="notif-status-chip notif-status-chip--${status}">${statusLabel}</span>
              </div>
              <p class="notification-message">${message}</p>
              <div class="notification-footer">
                ${actionHtml}
              </div>
            </div>
          </article>
        `;
      })
      .join('');

    list.innerHTML = html;
  };

  const render = () => {
    renderCounters();
    renderList();
  };

  const setLoadingState = (isLoading) => {
    state.loading = isLoading;
    root.classList.toggle('is-loading', isLoading);

    filterButtons.forEach((button) => {
      button.disabled = isLoading;
    });

    if (searchInput) {
      searchInput.disabled = isLoading;
    }

    if (markAllButton) {
      const unread = state.notifications.some((item) => normalizeStatus(item.status) === 'unread');
      markAllButton.disabled = isLoading || !unread;
    }
  };

  const loadNotifications = async () => {
    setLoadingState(true);

    try {
      const payload = await sendRequest({ action: 'fetch_list' });
      const notifications = Array.isArray(payload.notifications) ? payload.notifications : [];
      state.notifications = notifications.map((item) => ({
        id_notifikasi: String(item.id_notifikasi || ''),
        pesan: String(item.pesan || ''),
        status: normalizeStatus(item.status)
      }));
      render();
    } catch (error) {
      state.notifications = [];
      render();
      showFeedback('error', error.message || 'Gagal memuat notifikasi.');
    } finally {
      setLoadingState(false);
      render();
    }
  };

  const markNotificationAsRead = async (idToken) => {
    if (!idToken || state.loading) {
      return;
    }

    const index = state.notifications.findIndex((item) => item.id_notifikasi === idToken);
    if (index < 0 || normalizeStatus(state.notifications[index].status) === 'read') {
      return;
    }

    const previousStatus = state.notifications[index].status;
    state.notifications[index].status = 'read';
    render();

    try {
      await sendRequest({ action: 'mark_read', id_notifikasi: idToken });
    } catch (error) {
      state.notifications[index].status = previousStatus;
      render();
      showFeedback('error', error.message || 'Gagal menandai notifikasi sebagai dibaca.');
    }
  };

  const markAllAsRead = async () => {
    if (state.loading) {
      return;
    }

    const unreadIndexes = [];
    state.notifications.forEach((item, index) => {
      if (normalizeStatus(item.status) === 'unread') {
        unreadIndexes.push(index);
      }
    });

    if (unreadIndexes.length === 0) {
      return;
    }

    unreadIndexes.forEach((index) => {
      state.notifications[index].status = 'read';
    });
    render();

    setLoadingState(true);
    try {
      await sendRequest({ action: 'mark_all_read' });
    } catch (error) {
      unreadIndexes.forEach((index) => {
        state.notifications[index].status = 'unread';
      });
      showFeedback('error', error.message || 'Gagal menandai semua notifikasi sebagai dibaca.');
    } finally {
      setLoadingState(false);
      render();
    }
  };

  filterButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const nextFilter = button.dataset.filter || 'all';
      state.currentFilter = ['all', 'unread', 'read'].includes(nextFilter) ? nextFilter : 'all';

      filterButtons.forEach((item) => {
        const active = item === button;
        item.classList.toggle('is-active', active);
        item.setAttribute('aria-selected', active ? 'true' : 'false');
      });

      renderList();
    });
  });

  if (searchInput) {
    searchInput.addEventListener('input', () => {
      renderList();
    });
  }

  if (markAllButton) {
    markAllButton.addEventListener('click', () => {
      void markAllAsRead();
    });
  }

  list.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) {
      return;
    }

    const directActionButton = target.closest('[data-action="mark-read"]');
    if (directActionButton instanceof HTMLButtonElement) {
      event.preventDefault();
      const idToken = String(directActionButton.getAttribute('data-id') || '');
      void markNotificationAsRead(idToken);
      return;
    }

    const card = target.closest('.notification-card');
    if (!card) {
      return;
    }

    const idToken = String(card.getAttribute('data-id') || '');
    void markNotificationAsRead(idToken);
  });

  list.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }

    const target = event.target;
    if (!(target instanceof Element) || target.closest('button')) {
      return;
    }

    const card = target.closest('.notification-card');
    if (!card) {
      return;
    }

    event.preventDefault();
    const idToken = String(card.getAttribute('data-id') || '');
    void markNotificationAsRead(idToken);
  });

  void loadNotifications();
});
