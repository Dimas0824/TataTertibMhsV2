use DiscipLink;

-- Menambahkan data dummy ke tabel PRODI
INSERT INTO PRODI (id_prodi, nama_prodi, jurusan)
VALUES
    ('TI01', 'Teknik Informatika', 'Teknologi Informasi'),
    ('TI02', 'Sistem Informasi Bisnis', 'Teknologi Informasi'),
    ('TI03', 'Pengembangan Perangkat Lunak Sistem', 'Teknologi Informasi');

-- Data Dummy untuk Tabel DOSEN
INSERT INTO DOSEN (nidn, nama_lengkap, email, jabatan, id_prodi, password, role)
VALUES
    ('1234567890', 'Dr. Andi Wijaya', 'andi.wijaya@polinema.ac.id', 'DPA', 'TI01', 'password123', 'dosen'),
    ('1234567891', 'Prof. Siti Aminah', 'siti.aminah@polinema.ac.id', 'Kajur', 'TI01', 'password456', 'dosen'),
    ('1234567892', 'Dr. Bambang Sutrisno', 'bambang.sutrisno@polinema.ac.id', 'DPA', 'TI02', 'password789', 'dosen'),
    ('1234567893', 'Dr. Sri Wahyuni', 'sri.wahyuni@polinema.ac.id', 'Dosen Tetap', 'TI02', 'password101', 'dosen');


-- Data Dummy untuk Tabel MAHASISWA (dengan id_dpa)
INSERT INTO MAHASISWA (nim, nama_lengkap, email, angkatan, id_prodi, id_dpa, password, role)
VALUES
    ('2347120088', 'Ahmad Irsyad', 'ahmad.irsyad@student.example.com', 2023, 'TI01', 2, 'password123', 'mahasiswa'),
    ('2347120090', 'Budi Santoso', 'budi.santoso@student.example.com', 2023, 'TI01', 3, 'password456', 'mahasiswa'),
    ('2347120100', 'Citra Dewi', 'citra.dewi@student.example.com', 2023, 'TI01', 4, 'password789', 'mahasiswa'),
    ('2347120110', 'Dian Putri', 'dian.putri@student.example.com', 2023, 'TI02', 4, 'password101', 'mahasiswa'),
    ('2347120120', 'Eko Prasetyo', 'eko.prasetyo@student.example.com', 2023, 'TI02', 1, 'password102', 'mahasiswa');

-- Data Dummy untuk Tabel ADMIN
INSERT INTO ADMIN (NIP, nama_admin, password)
VALUES
    ('ADMIN001', 'Achmad Budiarto', 'admin123'),
    ('ADMIN002', 'Siti Aminah', 'admin456'),
    ('ADMIN003', 'Wahyu Wibowo', 'admin789'),
    ('ADMIN004', 'Dian Putri', 'admin101'),
    ('ADMIN005', 'Eko Prasetyo', 'admin102');

-- Data Dummy untuk Tabel TATA_TERTIB
INSERT INTO TATA_TERTIB (id_adminTatib, deskripsi, tingkat, poin)
VALUES
    -- Tingkat V (Poin 2)
    (1, 'Berkomunikasi dengan tidak sopan, baik tertulis atau tidak tertulis kepada mahasiswa, dosen, karyawan, atau orang lain', 'V', 2),

    -- Tingkat IV (Poin 4)
    (1, 'Berbusana tidak sopan dan tidak rapi. Yaitu antara lain adalah: berpakaian ketat, transparan, memakai t-shirt (baju kaos tidak berkerah), tank top, hipster, you can see, rok mini, backless, celana pendek, celana tiga per empat, legging, model celana atau baju koyak, sandal, sepatu sandal di lingkungan kampus', 'IV', 4),
    (1, 'Mahasiswa laki-laki berambut tidak rapi, gondrong yaitu panjang rambutnya melewati batas alis mata di bagian depan, telinga di bagian samping atau menyentuh kerah baju di bagian leher', 'IV', 4),
    (1, 'Mahasiswa berambut dengan model punk, dicat selain hitam dan/atau skinned.', 'IV', 4),
    (1, 'Makan, atau minum di dalam ruang kuliah/laboratorium/bengkel.', 'IV', 4),

    -- Tingkat III (Poin 8)
    (1, 'Melanggar peraturan/ketentuan yang berlaku di Polinema baik di Jurusan/Program Studi', 'III', 8),
    (1, 'Tidak menjaga kebersihan di seluruh area Polinema', 'III', 8),
    (1, 'Membuat kegaduhan yang mengganggu pelaksanaan perkuliahan atau praktikum yang sedang berlangsung', 'III', 8),
    (1, 'Merokok di luar area kawasan merokok', 'III', 8),
    (1, 'Bermain kartu, game online di area kampus', 'III', 8),
    (1, 'Mengotori atau mencoret-coret meja, kursi, tembok, dan lain-lain di lingkungan Polinema', 'III', 8),
    (1, 'Bertingkah laku kasar atau tidak sopan kepada mahasiswa, dosen, dan/atau karyawan.', 'III', 8),

    -- Tingkat II (Poin 16)
    (1, 'Merusak sarana dan prasarana yang ada di area Polinema', 'II', 16),
    (1, 'Tidak menjaga ketertiban dan keamanan di seluruh area Polinema (misalnya: parkir tidak pada tempatnya, konvoi selebrasi wisuda dll)', 'II', 16),
    (1, 'Melakukan pengotoran/pengrusakan barang milik orang lain termasuk milik Politeknik Negeri Malang', 'II', 16),
    (1, 'Mengakses materi pornografi di kelas atau area kampus', 'II', 16),
    (1, 'Membawa dan/atau menggunakan senjata tajam dan/atau senjata api untuk hal kriminal', 'II', 16),
    (1, 'Melakukan perkelahian, serta membentuk geng/kelompok yang bertujuan negatif.', 'II', 16),
    (1, 'Melakukan kegiatan politik praktis di dalam kampus', 'II', 16),
    (1, 'Melakukan tindakan kekerasan atau perkelahian di dalam kampus.', 'II', 16),
    (1, 'Melakukan penyalahgunaan identitas untuk perbuatan negatif', 'II', 16),
    (1, 'Mengancam, baik tertulis atau tidak tertulis kepada mahasiswa, dosen, dan/atau karyawan.', 'II', 16),

    -- Tingkat I (Poin 32)
    (1, 'Mencuri dalam bentuk apapun', 'I', 32),
    (1, 'Melakukan kecurangan dalam bidang akademik, administratif, dan keuangan.', 'I', 32),
    (1, 'Melakukan pemerasan dan/atau penipuan', 'I', 32),
    (1, 'Melakukan pelecehan dan/atau tindakan asusila dalam segala bentuk di dalam dan di luar kampus', 'I', 32),
    (1, 'Berjudi, mengkonsumsi minum-minuman keras, dan/atau bermabuk-mabukan di lingkungan dan di luar lingkungan Kampus Polinema', 'I', 32),
    (1, 'Mengikuti organisasi dan atau menyebarkan faham-faham yang dilarang oleh Pemerintah.', 'I', 32),
    (1, 'Melakukan pemalsuan data / dokumen / tanda tangan.', 'I', 32),
    (1, 'Melakukan plagiasi (copy paste) dalam tugas-tugas atau karya ilmiah', 'I', 32),
    (1, 'Tidak menjaga nama baik Polinema di masyarakat dan/atau mencemarkan nama baik Polinema melalui media apapun', 'I', 32),
    (1, 'Melakukan kegiatan atau sejenisnya yang dapat menurunkan kehormatan atau martabat Negara, Bangsa dan Polinema.', 'I', 32),
    (1, 'Menggunakan barang-barang psikotropika dan/atau zat-zat Adiktif lainnya', 'I', 32),
    (1, 'Mengedarkan serta menjual barang-barang psikotropika dan/atau zat-zat Adiktif lainnya', 'I', 32),
    (1, 'Terlibat dalam tindakan kriminal dan dinyatakan bersalah oleh Pengadilan', 'I', 32);


-- Data Dummy untuk Tabel SANKSI (dipisah sub-bab nya)
INSERT INTO SANKSI (tingkat, deskripsi)
VALUES
    ('V', 'Teguran lisan disertai dengan surat pernyataan tidak mengulangi perbuatan tersebut, dibubuhi materai, ditandatangani mahasiswa yang bersangkutan dan DPA'),
    ('IV', 'Teguran tertulis disertai dengan pemanggilan orang tua/wali dan membuat surat pernyataan tidak mengulangi perbuatan tersebut, dibubuhi materai, ditandatangani mahasiswa, orang tua/wali, dan DPA'),
    ('III', 'Membuat surat pernyataan tidak mengulangi perbuatan tersebut, dibubuhi materai, ditandatangani mahasiswa, orang tua/wali, dan DPA'),
    ('III', 'Melakukan tugas khusus, misalnya bertanggung jawab untuk memperbaiki atau membersihkan kembali, dan tugas-tugas lainnya'),
    ('II', 'Dikenakan penggantian kerugian atau penggantian benda/barang semacamnya'),
    ('II', 'Melakukan tugas layanan sosial dalam jangka waktu tertentu'),
    ('II', 'Diberikan nilai D pada mata kuliah terkait saat melakukan pelanggaran'),
    ('I', 'Dinonaktifkan (Cuti Akademik/Terminal) selama dua semester'),
    ('I', 'Diberhentikan sebagai mahasiswa');

-- Data Dummy untuk Tabel NEWS
INSERT INTO NEWS (judul, konten, penulis_id)
VALUES
    ('Perubahan Tata Tertib Kampus', 'Mulai semester ini, aturan terkait kehadiran diperketat.', 1),
    ('Pelatihan Anti Kekerasan', 'Mahasiswa yang melanggar akan diwajibkan mengikuti pelatihan.', 2),
    ('Peningkatan Disiplin', 'Kami mengajak seluruh mahasiswa meningkatkan kedisiplinan.', 3),
    ('Larangan Merokok', 'Merokok di area kampus akan dikenakan sanksi tegas.', 4),
    ('Penggunaan Seragam Baru', 'Setiap mahasiswa wajib menggunakan seragam sesuai program studi.', 5);
