<?php

declare(strict_types=1);

if (!function_exists('render_pelaporan_cancel_modal_component')) {
    function render_pelaporan_cancel_modal_component(array $config = []): void
    {
        $context = (string) ($config['context'] ?? 'views');
        $context = in_array($context, ['root', 'views', 'nested'], true) ? $context : 'views';
        $assetPrefix = $context === 'root' ? '' : ($context === 'nested' ? '../../' : '../');
        $redirectHref = (string) ($config['redirectHref'] ?? 'pelanggaran_dosen.php');

        ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8') ?>css/pelaporan-cancel-modal.css">
        <div id="cancelPelaporanModal" class="cancel-report-modal" aria-hidden="true"
            data-cancel-report-modal data-redirect-href="<?= htmlspecialchars($redirectHref, ENT_QUOTES, 'UTF-8') ?>">
            <div class="cancel-report-modal__backdrop" data-cancel-report-close></div>
            <section class="cancel-report-modal__dialog" role="dialog" aria-modal="true"
                aria-labelledby="cancelPelaporanTitle" aria-describedby="cancelPelaporanDesc" tabindex="-1">
                <button type="button" class="cancel-report-modal__close" data-cancel-report-close aria-label="Tutup modal">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>

                <div class="cancel-report-modal__icon" aria-hidden="true">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <h3 id="cancelPelaporanTitle" class="cancel-report-modal__title">Batalkan pelaporan?</h3>
                <p id="cancelPelaporanDesc" class="cancel-report-modal__desc">
                    Data yang belum disimpan akan hilang. Yakin ingin keluar dari halaman ini?
                </p>

                <div class="cancel-report-modal__actions">
                    <button type="button" class="cancel-report-modal__btn cancel-report-modal__btn--ghost"
                        data-cancel-report-close>Kembali</button>
                    <button type="button" class="cancel-report-modal__btn cancel-report-modal__btn--danger"
                        data-cancel-report-confirm>Ya, Batalkan</button>
                </div>
            </section>
        </div>
        <script defer src="<?= htmlspecialchars(app_seo_script_src('js/pelaporan-cancel-modal.js', $assetPrefix), ENT_QUOTES, 'UTF-8') ?>"></script>
        <?php
    }
}
