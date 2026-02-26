document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('[data-notif-root]');
  if (!root) {
    return;
  }

  const endpoint = root.dataset.endpoint || '../../Request/Handler_Notifikasi.php';
  const searchInput = document.getElementById('notifSearchInput');
  const filterButtons = Array.from(root.querySelectorAll('.notif-filter-btn'));
  const markAllButton = root.querySelector('[data-action="mark-all-read"]');
  const list = document.getElementById('notifList');
  const emptyServer = document.getElementById('notifEmptyServer');
  const emptyFiltered = document.getElementById('notifEmptyFiltered');

  if (!list) {
    return;
  }

  const counters = {
    total: root.querySelector('[data-counter="total"]'),
    unread: root.querySelector('[data-counter="unread"]'),
    read: root.querySelector('[data-counter="read"]')
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

  const escapeHtml = (text) => {
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
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
    const query = (searchInput?.value || '').trim().toLowerCase();

    return state.notifications.filter((item) => {
      const status = normalizeStatus(item.status);
      const message = String(item.pesan || '').toLowerCase();
      const passFilter = state.currentFilter === 'all' || status === state.currentFilter;
      const passQuery = query === '' || message.includes(query);

      return passFilter && passQuery;
    });
  };

  const renderCounters = () => {
    const total = state.notifications.length;
    const unread = state.notifications.filter((item) => normalizeStatus(item.status) === 'unread').length;
    const read = total - unread;

    if (counters.total) counters.total.textContent = String(total);
    if (counters.unread) counters.unread.textContent = String(unread);
    if (counters.read) counters.read.textContent = String(read);

    if (markAllButton) {
      markAllButton.disabled = unread === 0 || state.loading;
    }
  };

  const renderList = () => {
    const filtered = getFilteredNotifications();

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
        const id = Number.parseInt(item.id_notifikasi, 10);
        const safeId = Number.isInteger(id) && id > 0 ? id : 0;
        const icon = status === 'unread' ? 'fa-solid fa-bell' : 'fa-solid fa-check';

        return `
          <article
            class="notification-card ${status === 'unread' ? 'is-unread' : 'is-read'}"
            data-id="${safeId}"
            data-status="${status}"
            role="listitem"
            tabindex="0"
            aria-label="Notifikasi ${statusLabel}">
            <div class="notif-icon" aria-hidden="true">
              <i class="${icon}"></i>
            </div>
            <div class="notification-content">
              <p class="notification-message">${message}</p>
              <span class="notif-status-chip notif-status-chip--${status}">${statusLabel}</span>
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
    filterButtons.forEach((button) => {
      button.disabled = isLoading;
    });
    if (searchInput) {
      searchInput.disabled = isLoading;
    }
    if (markAllButton) {
      markAllButton.disabled = isLoading || state.notifications.every((item) => normalizeStatus(item.status) === 'read');
    }
  };

  const loadNotifications = async () => {
    setLoadingState(true);

    try {
      const payload = await sendRequest({ action: 'fetch_list' });
      const notifications = Array.isArray(payload.notifications) ? payload.notifications : [];
      state.notifications = notifications.map((item) => ({
        id_notifikasi: Number.parseInt(item.id_notifikasi, 10) || 0,
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

  const markNotificationAsRead = async (id) => {
    if (!Number.isInteger(id) || id <= 0 || state.loading) {
      return;
    }

    const index = state.notifications.findIndex((item) => item.id_notifikasi === id);
    if (index < 0 || normalizeStatus(state.notifications[index].status) === 'read') {
      return;
    }

    const previousStatus = state.notifications[index].status;
    state.notifications[index].status = 'read';
    render();

    try {
      await sendRequest({ action: 'mark_read', id_notifikasi: id });
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
    const card = event.target.closest('.notification-card');
    if (!card) {
      return;
    }

    const id = Number.parseInt(card.dataset.id || '', 10);
    void markNotificationAsRead(id);
  });

  list.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }

    const card = event.target.closest('.notification-card');
    if (!card) {
      return;
    }

    event.preventDefault();
    const id = Number.parseInt(card.dataset.id || '', 10);
    void markNotificationAsRead(id);
  });

  void loadNotifications();
});
