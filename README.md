# Sistem Informasi Tata Tertib

## Deskripsi Proyek

DiscipLink adalah sistem informasi berbasis web yang dirancang untuk mengelola dan mendistribusikan informasi terkait aturan dan tata tertib di lingkungan Politeknik Negeri Malang (POLINEMA), khususnya pada Jurusan Teknologi Informasi (JTI). Tujuan utama dari proyek ini adalah menyediakan platform yang membantu mahasiswa dan staf dalam mengakses informasi penting mengenai peraturan yang berlaku, serta mempermudah pengelolaan pelanggaran dan sanksi secara sistematis.

### Fitur Utama

- **Pengumuman dan Pemberitahuan:** Menyediakan informasi terkini tentang aturan dan tata tertib yang baru atau diperbarui.
- **Dokumentasi:** Menyimpan dan mengelola dokumen terkait tata tertib, seperti brosur, panduan, dan kebijakan.
- **Interaksi Pengguna:** Memfasilitasi feedback dari mahasiswa tentang peraturan yang ada untuk meningkatkan transparansi dan akuntabilitas.

## Aturan Kolaborasi

1. **Branching:**
   - Setiap mahasiswa harus mengerjakan fitur atau tugas mereka di branch masing-masing.
   - Pastikan untuk menarik (pull) pembaruan dari `main branch` secara berkala.

2. **Commit dan Push:**
   - Lakukan commit secara berkala dengan pesan yang jelas dan deskriptif.
   - Hanya melakukan push pada branch masing-masing. **DILARANG** melakukan push ke `main branch` langsung tanpa melalui proses pull request.

3. **Review dan Merge:**
   - Setiap perubahan harus melalui proses review sebelum digabungkan ke `main branch`.

4. **Pengujian:**
   - Pastikan semua fitur diuji dengan baik sebelum melakukan push ke `main branch`.

5. **Dokumentasi:**
   - Selalu perbarui dokumentasi proyek dengan perubahan atau penambahan fitur yang baru.

6. **Komunikasi:**
   - Gunakan platform komunikasi yang telah disepakati untuk mendiskusikan perkembangan proyek.

## Cara Memulai

Untuk memulai dengan proyek ini, silakan ikuti langkah-langkah berikut:

1. Clone repositori ini:

   ```bash
   git clone https://github.com/VarizkyNaldiba/TataTertibMhs.git
2. Figma Link
   <https://www.figma.com/design/yRxgSGu5uvuoKQznRxPCNg/UI%2FUX-Sistem-Tatib?node-id=10-572&node-type=frame&t=FUBlBYXBfDiK1yST-0>

## Database CLI (Artisan-like)

Project ini menyediakan command CLI sederhana untuk migration dan seed:

```bash
php artisan list
php artisan migrate
php artisan db:seed
php artisan serve
```

Opsi penting:

- `php artisan migrate --fresh --force`
- `php artisan migrate:fresh --seed`
- `php artisan db:seed --file=20260225_000001_data_dummy.sql`
- `php artisan serve --host=127.0.0.1 --port=8000`
- `php artisan serve --hot` (butuh Node.js/npx, akses via port `8001` default)

Dokumentasi lengkap ada di `Database/README.md`.
