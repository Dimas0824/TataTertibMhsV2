CREATE TABLE PRODI (
    id_prodi VARCHAR(20) PRIMARY KEY,
    nama_prodi VARCHAR(100) NOT NULL,
    jurusan VARCHAR(100)
);

CREATE TABLE ADMIN (
    id_admin INT AUTO_INCREMENT PRIMARY KEY,
    NIP VARCHAR(50) UNIQUE NOT NULL,
    nama_admin VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE DOSEN (
    id_dosen INT AUTO_INCREMENT PRIMARY KEY,
    nidn VARCHAR(20) UNIQUE NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    jabatan VARCHAR(50) NOT NULL,
    id_prodi VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    CONSTRAINT FK_DosenProdi FOREIGN KEY (id_prodi) REFERENCES PRODI(id_prodi)
);

CREATE TABLE MAHASISWA (
    id_mhs INT AUTO_INCREMENT PRIMARY KEY,
    nim VARCHAR(20) UNIQUE NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    angkatan INT NOT NULL,
    id_prodi VARCHAR(20) NOT NULL,
    id_dpa INT NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    CONSTRAINT FK_MahasiswaProdi FOREIGN KEY (id_prodi) REFERENCES PRODI(id_prodi),
    CONSTRAINT FK_MahasiswaDPA FOREIGN KEY (id_dpa) REFERENCES DOSEN(id_dosen)
);

CREATE TABLE TATA_TERTIB (
    id_tata_tertib INT AUTO_INCREMENT PRIMARY KEY,
    id_adminTatib INT,
    deskripsi TEXT NOT NULL,
    tingkat VARCHAR(5) NOT NULL,
    poin INT NOT NULL,
    CONSTRAINT FK_AdminTaTib FOREIGN KEY (id_adminTatib) REFERENCES ADMIN(id_admin)
);

CREATE TABLE SANKSI (
    id_sanksi INT AUTO_INCREMENT PRIMARY KEY,
    tingkat VARCHAR(5) NOT NULL,
    deskripsi VARCHAR(255) NOT NULL
);

CREATE TABLE DETAIL_PELANGGARAN (
    id_detail INT AUTO_INCREMENT PRIMARY KEY,
    id_dosen INT NOT NULL,
    id_tata_tertib INT NOT NULL,
    id_mahasiswa INT NOT NULL,
    id_sanksi INT NOT NULL,
    tugas_khusus VARCHAR(255),
    detail_pelanggaran LONGTEXT,
    pengumpulan_tgsKhusus VARCHAR(255),
    surat VARCHAR(255),
    pengumpulan_surat VARCHAR(255),
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    status_tugas VARCHAR(50) DEFAULT 'Belum Diberikan',
    CONSTRAINT FK_Dosen FOREIGN KEY (id_dosen) REFERENCES DOSEN(id_dosen),
    CONSTRAINT FK_TataTertib FOREIGN KEY (id_tata_tertib) REFERENCES TATA_TERTIB(id_tata_tertib),
    CONSTRAINT FK_Mahasiswa FOREIGN KEY (id_mahasiswa) REFERENCES MAHASISWA(id_mhs),
    CONSTRAINT FK_Sanksi FOREIGN KEY (id_sanksi) REFERENCES SANKSI(id_sanksi)
);

CREATE TABLE NOTIFIKASI (
    id_notifikasi INT AUTO_INCREMENT PRIMARY KEY,
    id_mhs INT NULL,
    id_dosen INT NULL,
    id_detail_pelanggaran INT,
    pesan TEXT NOT NULL,
    status VARCHAR(50) NOT NULL,
    role_penerima VARCHAR(50) NOT NULL,
    CONSTRAINT FK_NotifMahasiswa FOREIGN KEY (id_mhs) REFERENCES MAHASISWA(id_mhs),
    CONSTRAINT FK_NotifDosen FOREIGN KEY (id_dosen) REFERENCES DOSEN(id_dosen),
    CONSTRAINT FK_NotifPelanggaran FOREIGN KEY (id_detail_pelanggaran) REFERENCES DETAIL_PELANGGARAN(id_detail)
);

CREATE TABLE NEWS (
    id_news INT AUTO_INCREMENT PRIMARY KEY,
    gambar VARCHAR(255),
    judul VARCHAR(100) NOT NULL,
    konten TEXT NOT NULL,
    penulis_id INT NOT NULL,
    CONSTRAINT FK_AdminNews FOREIGN KEY (penulis_id) REFERENCES ADMIN(id_admin)
);

CREATE OR REPLACE VIEW v_PelanggaranMahasiswa AS
SELECT
    DP.id_detail,
    M.nim,
    T.deskripsi AS pelanggaran,
    T.tingkat,
    S.deskripsi AS sanksi,
    D.nama_lengkap,
    DP.tugas_khusus,
    DP.surat,
    T.poin,
    DP.status
FROM DETAIL_PELANGGARAN DP
JOIN MAHASISWA M ON DP.id_mahasiswa = M.id_mhs
JOIN TATA_TERTIB T ON DP.id_tata_tertib = T.id_tata_tertib
JOIN SANKSI S ON DP.id_sanksi = S.id_sanksi
JOIN DOSEN D ON DP.id_dosen = D.id_dosen;

CREATE OR REPLACE VIEW v_DosenMelaporkan AS
SELECT
    dp.id_detail,
    d.nidn,
    m.nama_lengkap AS nama_mahasiswa,
    m.nim,
    p.nama_prodi,
    t.deskripsi AS pelanggaran,
    dp.detail_pelanggaran,
    t.tingkat,
    d.nama_lengkap AS dosen_pelapor,
    dp.tugas_khusus,
    dp.surat,
    dp.pengumpulan_tgsKhusus,
    t.poin,
    dp.status AS status_pelanggaran,
    dp.status_tugas
FROM DETAIL_PELANGGARAN dp
JOIN DOSEN d ON dp.id_dosen = d.id_dosen
JOIN MAHASISWA m ON dp.id_mahasiswa = m.id_mhs
JOIN TATA_TERTIB t ON dp.id_tata_tertib = t.id_tata_tertib
JOIN PRODI p ON m.id_prodi = p.id_prodi;

CREATE OR REPLACE VIEW v_NotifikasiDosen AS
SELECT
    N.id_notifikasi,
    D.nidn,
    D.nama_lengkap AS nama_dosen,
    M.nama_lengkap AS nama_mahasiswa,
    N.pesan,
    N.status
FROM NOTIFIKASI N
LEFT JOIN DOSEN D ON N.id_dosen = D.id_dosen
LEFT JOIN MAHASISWA M ON N.id_mhs = M.id_mhs
WHERE N.role_penerima = 'dosen';

CREATE OR REPLACE VIEW v_NotifikasiMahasiswa AS
SELECT
    N.id_notifikasi,
    M.nim,
    M.nama_lengkap AS nama_mahasiswa,
    N.pesan,
    N.status
FROM NOTIFIKASI N
LEFT JOIN MAHASISWA M ON N.id_mhs = M.id_mhs
WHERE N.role_penerima = 'mahasiswa';