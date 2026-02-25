<div class="sidebar">
        <img class="logo" src="img/logo aja.png" alt="logo">
        <div class="logo-separator"></div>
        <ul>
        <li class="active"><a href=""><i class="fa-solid fa-house"></i></a></li>
            <li><a href="views/listTatib.php"><i class="fa-solid fa-book"></i></a></li>
            <li><a href="views/pelanggaranpage.php"><i class="fa-solid fa-hand"></i></i></a></li>
            <?php if (isset($_SESSION['username'])): ?>
                <li><a href="views/notifikasi.php"><i class="fa-solid fa-bell"></i></a></li>
                <li class="logout"><a href="?logout=true"><i class="fa-solid fa-right-from-bracket"></i></a></li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="content">
    <div class="header">
    <h1>Home</h1>
    <?php if (!isset($_SESSION['username'])) : ?>
        <button class="login-btn" onclick="window.location.href='views/login.php'">Login</button>
    <?php endif; ?>
</div>

            <div class="judul">
                <h2>TATA TERTIB <br>MAHASISWA </h2>
                <p>Sebuah sistem yang dirancang untuk mengelola aturan, <br>pelanggaran, dan sanksi di Politeknik Negeri Malang</p>
            </div>
            <div class="dashboard-container">
                <img class="logo-disciplink" src="img/ga logo aja.png">
                <p>Disciplink adalah platform digital inovatif yang dirancang untuk menghubungkan mahasiswa dengan
                    sistem kedisiplinan kampus. Sebagai gabungan dari kata "Discipline" dan "Link", Disciplink berfokus
                    pada penyederhanaan proses pengelolaan tata tertib di lingkungan akademik, memudahkan mahasiswa dan
                    pihak kampus untuk memahami, memantau, dan menegakkan aturan secara efisien.</p>
            </div>
            <div class="news">
                <h2>News</h2>
                <div class="row">
                    <!-- nanti pakai while aja isi nya biar sesuai dengan inputan-->
                    <?php foreach($newsData as $news): ?>
                    <div class="news-content">
                        <?php if (!empty($news['gambar'])): ?>
                            <img src="<?= htmlspecialchars($news['gambar']) ?>" alt="Gambar News">
                        <?php else : ?>
                            <img src="img/news.jpg" alt="gambar">
                        <?php endif; ?>
                        <h3><?= htmlspecialchars($news['judul']) ?></h3>
                        <!-- ini nanti di ganti nama -->
                        <h5><?= htmlspecialchars($news['penulis_nama']) ?></h5>
                        <!-- ini -->
                        <p><?= nl2br(htmlspecialchars($news['konten'])) ?></p>
                    </div>
                    <?php endforeach; ?>
                    <!-- sampai sini while nya nanti yang bawah di hapus -->
                </div>
            </div>

            
            <div class="footer">
    <div class="footer-left">
        <img class="footer-logo" src="img/logo aja.png" alt="Logo">
        <img class ="footer-logo" src="img/logo.png" alt="logo polinema">
    </div>
    <div class="footer-center">
        <p>Jl. Soekarno Hatta No.9, Jatimulyo, Kec. Lowokwaru, <br>Kota Malang, Jawa Timur 65141</p>
        <p><a href="tel:(0341)404424" class="footer-link">(0341) 404424</a></p>
    </div>
    <div class="footer-right">
        <a href="https://instagram.com" class="social-link"><i class="fa-brands fa-instagram" alt="Instagram" class="social-icon"></i></a>
        <a href="https://wa.me/1234567890" class="social-link"><i class="fa-brands fa-whatsapp" alt="WhatsApp" class="social-icon"></i></a>
        <a href="https://wa.me/1234567890" class="social-link"><i class="fa-solid fa-envelope" alt="Email" class="social-icon"></i></a>
    </div>
    <div class="footer-bottom">
        <p>© Copyright 2024 web Tatib. All Rights Reserved.</p>
    </div>
</div>

</div>
