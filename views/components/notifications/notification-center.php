<?php

declare(strict_types=1);

if (!function_exists('render_notification_center_component')) {
    function render_notification_center_component(array $config = []): void
    {
        $context = (string) ($config['context'] ?? 'views');
        $context = in_array($context, ['root', 'views', 'nested'], true) ? $context : 'views';
        $assetPrefix = $context === 'root' ? '' : ($context === 'nested' ? '../../' : '../');

        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        $endpoint = (string) ($config['endpoint'] ?? app_action_url('action.notifikasi'));
        $roleLabel = trim((string) ($config['roleLabel'] ?? 'Pengguna'));
        $kicker = (string) ($config['kicker'] ?? 'DiscipLink Notification Center');
        $title = (string) ($config['title'] ?? 'Notifikasi Aktivitas Akademik');
        $description = (string) ($config['description'] ?? 'Pusat kendali notifikasi untuk memantau laporan baru, progres penanganan, dan pembaruan status secara cepat.');
        $priorityTitle = (string) ($config['priorityTitle'] ?? 'Prioritas Hari Ini');
        $priorityDescription = (string) ($config['priorityDescription'] ?? 'Masih ada notifikasi yang perlu tindakan lanjutan.');
        $tipsTitle = (string) ($config['tipsTitle'] ?? 'Tips Cepat');
        $feedTitle = (string) ($config['feedTitle'] ?? 'Feed Notifikasi');
        $feedHint = (string) ($config['feedHint'] ?? 'Klik kartu untuk menandai sebagai dibaca.');
        $searchPlaceholder = (string) ($config['searchPlaceholder'] ?? 'Cari isi notifikasi...');
        $priorityUnreadSuffix = (string) ($config['priorityUnreadSuffix'] ?? 'notifikasi belum dibaca');

        $tips = $config['tips'] ?? [
            'Gunakan filter status untuk fokus pada notifikasi penting.',
            'Cari berdasarkan NIM atau kata kunci isi notifikasi.',
            'Tandai semua dibaca setelah memastikan seluruh update diproses.',
        ];
        if (!is_array($tips) || $tips === []) {
            $tips = ['Gunakan filter untuk meninjau notifikasi dengan cepat.'];
        }
        ?>
        <link rel="stylesheet" href="<?= $escape($assetPrefix) ?>css/notifikasi.css">

        <section class="notif-page" data-notif-root data-endpoint="<?= $escape($endpoint) ?>">
            <section class="notif-hero" aria-label="Ringkasan notifikasi">
                <div class="notif-hero-copy">
                    <span class="notif-kicker"><?= $escape($kicker) ?></span>
                    <h2><?= $escape($title) ?></h2>
                    <p><?= $escape($description) ?></p>
                    <div class="notif-hero-pills">
                        <span class="notif-role-pill">
                            <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                            <?= $escape($roleLabel) ?>
                        </span>
                        <span class="notif-live-pill">
                            <span class="notif-live-dot" aria-hidden="true"></span>
                            Sinkron real-time
                        </span>
                    </div>
                </div>
                <div class="notif-stats" aria-live="polite">
                    <article class="notif-stat">
                        <span>Total Notifikasi</span>
                        <strong data-counter="total">0</strong>
                    </article>
                    <article class="notif-stat notif-stat--unread">
                        <span>Perlu Ditinjau</span>
                        <strong data-counter="unread">0</strong>
                    </article>
                    <article class="notif-stat notif-stat--read">
                        <span>Sudah Dibaca</span>
                        <strong data-counter="read">0</strong>
                    </article>
                </div>
            </section>

            <section class="notif-layout">
                <aside class="notif-side-panel" aria-label="Insight notifikasi">
                    <article class="notif-side-card">
                        <h3><?= $escape($priorityTitle) ?></h3>
                        <p><?= $escape($priorityDescription) ?></p>
                        <strong><span data-unread-highlight>0</span> <?= $escape($priorityUnreadSuffix) ?></strong>
                    </article>
                    <article class="notif-side-card notif-side-card--tips">
                        <h3><?= $escape($tipsTitle) ?></h3>
                        <ul>
                            <?php foreach ($tips as $tip):
                                $tipText = trim((string) $tip);
                                if ($tipText === '') {
                                    continue;
                                }
                                ?>
                                <li><?= $escape($tipText) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                </aside>

                <div class="notif-main-panel">
                    <section class="notif-toolbar" aria-label="Kontrol notifikasi">
                        <label class="notif-search" for="notifSearchInput">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="search" id="notifSearchInput" placeholder="<?= $escape($searchPlaceholder) ?>"
                                autocomplete="off">
                        </label>

                        <div class="notif-toolbar-actions">
                            <div class="notif-filters" role="tablist" aria-label="Filter status notifikasi">
                                <button type="button" class="notif-filter-btn is-active" data-filter="all"
                                    aria-selected="true">
                                    Semua
                                    <span data-filter-count="all">0</span>
                                </button>
                                <button type="button" class="notif-filter-btn" data-filter="unread" aria-selected="false">
                                    Unread
                                    <span data-filter-count="unread">0</span>
                                </button>
                                <button type="button" class="notif-filter-btn" data-filter="read" aria-selected="false">
                                    Read
                                    <span data-filter-count="read">0</span>
                                </button>
                            </div>

                            <button type="button" class="notif-mark-all-btn" data-action="mark-all-read" disabled>
                                <i class="fa-solid fa-check-double" aria-hidden="true"></i>
                                Tandai semua dibaca
                            </button>
                        </div>

                        <p class="notif-result-summary">
                            Menampilkan <strong data-counter-visible>0</strong> notifikasi,
                            mode <span data-active-filter-label>Semua</span>
                        </p>
                    </section>

                    <section class="notifications-panel" aria-live="polite">
                        <header class="notifications-head">
                            <h3><?= $escape($feedTitle) ?></h3>
                            <span class="notifications-head__hint"><?= $escape($feedHint) ?></span>
                        </header>

                        <div class="notifications" id="notifList" role="list"></div>

                        <div class="notifications-empty is-hidden" id="notifEmptyServer">
                            <i class="fa-regular fa-bell-slash" aria-hidden="true"></i>
                            <p>Belum ada notifikasi untuk akun ini.</p>
                        </div>

                        <div class="notifications-empty is-hidden" id="notifEmptyFiltered">
                            <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i>
                            <p>Tidak ada notifikasi yang sesuai dengan pencarian/filter.</p>
                        </div>
                    </section>
                </div>
            </section>
        </section>

        <script defer src="<?= $escape(app_seo_script_src('js/notifikasi.js', $assetPrefix)) ?>"></script>
        <?php
    }
}
