<?php

declare(strict_types=1);

if (!function_exists('render_admin_confirm_modal_component')) {
    function render_admin_confirm_modal_component(array $config = []): void
    {
        $context = (string) ($config['context'] ?? 'views');
        $context = in_array($context, ['root', 'views', 'nested'], true) ? $context : 'views';
        $assetPrefix = $context === 'root' ? '' : ($context === 'nested' ? '../../' : '../');

        ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8') ?>css/admin-confirm-modal.css">
        <div id="adminConfirmModal" class="admin-confirm-modal" aria-hidden="true" data-admin-confirm-modal>
            <div class="admin-confirm-modal__backdrop" data-admin-confirm-close></div>
            <section class="admin-confirm-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="adminConfirmTitle"
                aria-describedby="adminConfirmDesc" tabindex="-1">
                <button type="button" class="admin-confirm-modal__close" data-admin-confirm-close aria-label="Tutup modal">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>

                <div class="admin-confirm-modal__icon" aria-hidden="true">
                    <i class="fa-solid fa-circle-exclamation"></i>
                </div>
                <h3 id="adminConfirmTitle" class="admin-confirm-modal__title">Konfirmasi Aksi</h3>
                <p id="adminConfirmDesc" class="admin-confirm-modal__desc">Apakah Anda yakin ingin melanjutkan aksi ini?</p>

                <div class="admin-confirm-modal__actions">
                    <button type="button" class="admin-confirm-modal__btn admin-confirm-modal__btn--ghost"
                        data-admin-confirm-close>Batal</button>
                    <button type="button" class="admin-confirm-modal__btn admin-confirm-modal__btn--danger"
                        data-admin-confirm-confirm>Ya, Lanjutkan</button>
                </div>
            </section>
        </div>
        <script defer
            src="<?= htmlspecialchars(app_seo_script_src('js/admin-confirm-modal.js', $assetPrefix), ENT_QUOTES, 'UTF-8') ?>"></script>
        <?php
    }
}
