ALTER TABLE DETAIL_PELANGGARAN
    ADD COLUMN delegasi_tugas_ke_dpa TINYINT(1) NOT NULL DEFAULT 0 AFTER status_tugas,
    ADD COLUMN id_dosen_penanggung_jawab INT NULL AFTER delegasi_tugas_ke_dpa;

UPDATE DETAIL_PELANGGARAN
SET id_dosen_penanggung_jawab = id_dosen
WHERE id_dosen_penanggung_jawab IS NULL;

ALTER TABLE DETAIL_PELANGGARAN
    MODIFY COLUMN id_dosen_penanggung_jawab INT NOT NULL;

ALTER TABLE DETAIL_PELANGGARAN
    ADD CONSTRAINT FK_DosenPenanggungJawab
        FOREIGN KEY (id_dosen_penanggung_jawab) REFERENCES DOSEN(id_dosen);

CREATE OR REPLACE VIEW v_PelanggaranMahasiswa AS
SELECT
    dp.id_detail,
    m.nim,
    t.deskripsi AS pelanggaran,
    t.tingkat,
    s.deskripsi AS sanksi,
    d.nama_lengkap,
    dp.tugas_khusus,
    dp.surat,
    t.poin,
    dp.status,
    dp.status_tugas,
    dp.delegasi_tugas_ke_dpa,
    penanggung.nama_lengkap AS dosen_penanggung_jawab
FROM DETAIL_PELANGGARAN dp
JOIN MAHASISWA m ON dp.id_mahasiswa = m.id_mhs
JOIN TATA_TERTIB t ON dp.id_tata_tertib = t.id_tata_tertib
JOIN SANKSI s ON dp.id_sanksi = s.id_sanksi
JOIN DOSEN d ON dp.id_dosen = d.id_dosen
LEFT JOIN DOSEN penanggung ON dp.id_dosen_penanggung_jawab = penanggung.id_dosen;

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
    dp.status_tugas,
    dp.delegasi_tugas_ke_dpa,
    dp.id_dosen_penanggung_jawab,
    penanggung.nidn AS nidn_penanggung_jawab,
    penanggung.nama_lengkap AS dosen_penanggung_jawab
FROM DETAIL_PELANGGARAN dp
JOIN DOSEN d ON dp.id_dosen = d.id_dosen
JOIN MAHASISWA m ON dp.id_mahasiswa = m.id_mhs
JOIN TATA_TERTIB t ON dp.id_tata_tertib = t.id_tata_tertib
JOIN PRODI p ON m.id_prodi = p.id_prodi
LEFT JOIN DOSEN penanggung ON dp.id_dosen_penanggung_jawab = penanggung.id_dosen;
