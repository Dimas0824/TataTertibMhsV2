<?php

declare(strict_types=1);

if (!function_exists('render_app_feedback_modal_component')) {
    function render_app_feedback_modal_component(array $config = []): void
    {
        $assetPrefix = rtrim((string) ($config['assetPrefix'] ?? ''), '/');
        $assetPrefix = $assetPrefix === '' ? '' : ($assetPrefix . '/');

        $flashModal = $config['flashModal'] ?? null;
        if (!is_array($flashModal)) {
            $flashModal = null;
        }
        ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8') ?>css/app-modal.css">
        <div id="appFeedbackModal" class="app-modal" aria-hidden="true">
            <div class="app-modal__backdrop" data-app-modal-close></div>
            <section class="app-modal__dialog" role="alertdialog" aria-modal="true" aria-labelledby="appFeedbackModalTitle"
                aria-describedby="appFeedbackModalMessage" tabindex="-1">
                <header class="app-modal__header">
                    <span class="app-modal__tone" data-app-modal-tone>Informasi</span>
                    <button type="button" class="app-modal__close" data-app-modal-close aria-label="Tutup modal">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </header>

                <div class="app-modal__status">
                    <span class="app-modal__icon-wrap" aria-hidden="true">
                        <span class="app-modal__icon" data-app-modal-icon>i</span>
                    </span>
                    <h2 class="app-modal__title" id="appFeedbackModalTitle" data-app-modal-title>Informasi</h2>
                </div>

                <p class="app-modal__message" id="appFeedbackModalMessage" data-app-modal-message></p>

                <footer class="app-modal__actions">
                    <button type="button" class="app-modal__button" data-app-modal-close data-app-modal-confirm>
                        Tutup
                    </button>
                </footer>
            </section>
        </div>
        <?php if ($flashModal !== null): ?>
            <script>
                window.__APP_FLASH_MODAL = <?= json_encode($flashModal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            </script>
        <?php endif; ?>
        <script defer src="<?= htmlspecialchars(app_seo_script_src('js/app-modal.js', $assetPrefix), ENT_QUOTES, 'UTF-8') ?>"></script>
        <?php
    }
}
