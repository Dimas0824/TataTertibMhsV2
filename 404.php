<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers/seo_helper.php';

app_seo_enforce_canonical_host();
app_seo_apply_security_headers();
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halaman Tidak Ditemukan | DiscipLink</title>
    <?php
    app_seo_meta_tags([
        'title' => '404 - Halaman Tidak Ditemukan | DiscipLink',
        'description' => 'Halaman yang Anda cari tidak tersedia. Kembali ke DiscipLink untuk melihat tata tertib, pelanggaran, dan sanksi mahasiswa Polinema.',
        'canonical_path' => '/404.php',
        'robots' => 'noindex, nofollow',
        'image' => 'img/GRAHA-POLINEMA1-slider-01.webp',
    ]);
    app_seo_favicon_tags();
    ?>
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/not-found.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
</head>

<body>
    <main class="not-found" aria-labelledby="notFoundTitle">
        <div class="not-found-card">
            <p class="not-found-code">404</p>
            <h1 id="notFoundTitle">Halaman tidak ditemukan</h1>
            <p>Maaf, halaman yang Anda cari mungkin dipindahkan atau URL tidak valid. Gunakan tautan cepat di bawah
                untuk kembali ke halaman utama DiscipLink.</p>

            <form action="index.php" method="get" class="not-found-search" role="search" aria-label="Cari halaman">
                <label for="searchTerm">Cari halaman</label>
                <input id="searchTerm" type="search" name="q" placeholder="Cari tata tertib, pelanggaran, sanksi..."
                    autocomplete="off">
                <button type="submit">Cari</button>
            </form>

            <nav class="not-found-links" aria-label="Tautan populer">
                <a href="index.php" title="Kembali ke halaman utama DiscipLink">Beranda</a>
                <a href="views/tatib/listTatib.php" title="Lihat daftar tata tertib mahasiswa">Tata Tertib</a>
                <a href="views/auth/login.php" title="Masuk ke akun DiscipLink">Login</a>
                <a href="https://www.polinema.ac.id" target="_blank" rel="noopener noreferrer"
                    title="Website resmi Politeknik Negeri Malang">Website Resmi Polinema</a>
            </nav>

            <button type="button" class="not-found-back" id="backButton">Kembali</button>
        </div>
    </main>

    <script>
        (function () {
            var backButton = document.getElementById('backButton');
            if (!backButton) {
                return;
            }

            backButton.addEventListener('click', function () {
                if (window.history.length > 1) {
                    window.history.back();
                    return;
                }

                window.location.href = 'index.php';
            });
        })();
    </script>
</body>

</html>