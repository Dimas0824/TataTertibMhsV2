# ✅ OnPage SEO Optimization Checklist (DiscipLink - Home)

> Target utama halaman: **tata tertib mahasiswa** (+ variasi: aturan kampus, pelanggaran, sanksi, kedisiplinan, DiscipLink, Polinema)

---

## 🚨 Issues to Fix (Prioritas Berdasarkan Audit)

### HIGH

- [x] **URL Canonicalization**: pilih 1 URL utama (preferred) lalu redirect semua variasi ke URL itu  
  - [x] Tentukan: `https://dimspersonal.my.id/` **atau** `https://www.dimspersonal.my.id/` (pilih satu)
  - [x] Terapkan redirect 301 dari versi lain → preferred
  - [x] Tambahkan `<link rel="canonical" href="PREFERRED_URL" />` di semua halaman

- [ ] **LCP ≤ 2.5s (Google recommendation)**  
  - [x] Optimalkan elemen LCP (audit: section `.judul` / hero text)
  - [x] Pastikan font tidak blocking dan hero cepat muncul
  - [ ] Kurangi ukuran image (audit: total page ~6.74MB, 94.5% image)

- [ ] **Eliminate render-blocking resources**  
  - [x] Defer/async JS non-kritis
  - [x] Inline critical CSS seperlunya / preload CSS utama
  - [x] Tunda script pihak ketiga sampai interaksi / setelah load

- [ ] **Loading time < 5s** (audit: ~7.43s, risiko drop pengunjung)  
  - [x] Turunkan total payload, fokus utama: image
  - [x] Pastikan caching + kompresi + optimasi request

- [x] **Keyword distribution**: keyword utama harus muncul di title + meta description + headings  
  - [x] Pastikan “tata”, “tertib”, “mahasiswa”, “disciplink” muncul natural di `<title>`, meta description, H1/H2

- [x] **Add Meta Description** (audit: tidak ada)  
  - [x] Tambahkan `<meta name="description" content="...">` 140–160 karakter

- [x] **Add sitemap.xml** (audit: tidak ada)  
  - [x] Generate sitemap dan submit ke Google Search Console

- [x] **Add robots.txt** (audit: tidak ada)  
  - [x] Buat robots.txt minimal + referensi sitemap

- [ ] **Modern image formats** (WebP/AVIF)  
  - [ ] Convert PNG/JPG → WebP/AVIF untuk hero + gambar besar
  - [x] Pastikan fallback bila perlu

### MEDIUM

- [x] **Minify JavaScript** (audit: JS tidak minified)  
  - [x] Minify bundle/asset JS produksi

- [x] **Custom 404 page**  
  - [x] Buat halaman 404 dengan link populer + search + tombol kembali

- [x] **Serve properly sized images**  
  - [x] Gunakan responsive images (`srcset`, `sizes`) atau image optimization framework
  - [x] Hindari kirim gambar besar untuk viewport kecil

- [x] **Avoid distorted images**  
  - [x] Perbaiki aspect ratio (gunakan CSS `object-fit`, ukuran proporsional)

- [x] **Structured data (JSON-LD)**  
  - [x] Tambahkan schema minimal: `WebSite`, `Organization`, `BreadcrumbList`
  - [x] Untuk news: `Article`

- [x] **Add Google Analytics** (atau alternatif)  
  - [x] Pasang GA4 untuk monitoring SEO/traffic & debugging funnel

- [x] **Social media meta tags** (OG/Twitter)  
  - [x] Tambahkan Open Graph + Twitter Cards untuk preview share yang rapi

### LOW

- [x] **HSTS header** (`Strict-Transport-Security`)  
  - [x] Aktifkan HSTS untuk forcing HTTPS

- [x] **Favicon**  
  - [x] Tambahkan favicon + referensi yang benar (`<link rel="icon" ...>`)

---

## 1) Title Tag (Prioritas Tinggi)

- [x] Panjang rekomendasi 20–60 karakter (aman untuk SERP)
- [x] Minimal 6 kata (audit: cuma 2 kata)
- [x] Masukkan keyword utama + brand

Contoh:

- [ ] `Tata Tertib Mahasiswa Polinema - Aturan & Sanksi | DiscipLink`
- [ ] `DiscipLink Polinema: Tata Tertib Mahasiswa, Pelanggaran & Sanksi`

---

## 2) Meta Description (Prioritas Tinggi)

- [x] Tambahkan meta description (audit: kosong)
- [x] 140–160 karakter, informatif, ada CTA ringan

Contoh:

- [ ] `Satu pusat informasi tata tertib mahasiswa Polinema: aturan, pelanggaran, sanksi, dan berita kedisiplinan. Cek ketentuan terbaru di DiscipLink.`

---

## 3) Headings & Keyword Placement

- [x] H1 jangan “Home” → harus topik utama
  - [x] H1: `Tata Tertib Mahasiswa Polinema (DiscipLink)`
- [x] Pastikan tidak ada lompat heading (missing H4)  
- [x] Masukkan keyword utama di H2/H3 secara natural

---

## 4) Konten Body (≥ 400 kata)

- [x] Tambah konten jadi ≥ 400–800 kata (audit: 197 kata)
- [x] Tambah section: Apa itu DiscipLink, Cara pakai, FAQ, ringkasan aturan/sanksi, highlight news
- [x] Perbaiki readability (Flesch 28 terlalu sulit): kalimat pendek + bullet list

---

## 5) Internal & Outbound Links

- [x] Internal link ≥ 10 (audit: 8)
- [x] Outbound link ≥ 3–5 ke sumber resmi & relevan (audit: 2)
- [x] Perbaiki link tanpa `title` attribute (audit: banyak)
- [x] Perbaiki link tanpa link text (audit: ada)
- [x] Untuk external `target="_blank"` wajib `rel="noopener noreferrer"`

---

## 6) Images (Speed + SEO)

- [ ] Gunakan WebP/AVIF (audit: belum modern format)
- [x] Properly sized (audit: oversized)
- [x] Fix aspect ratio (audit: distorted)
- [x] Tambahkan `width` dan `height` pada `<img>` (PSI warning)
- [x] Lazyload gambar non-hero
- [ ] Kompresi agresif (target page weight < 2MB untuk mobile bila memungkinkan)

---

## 7) Technical SEO Essentials

- [x] Canonical tag di semua halaman
- [x] robots.txt + sitemap.xml
- [x] Pastikan www/non-www konsisten (redirect 301)
- [x] Minimalkan variasi URL yang respons (hindari duplicate)

---

## 8) Social Preview

- [x] Open Graph: `og:title`, `og:description`, `og:image`, `og:url`, `og:type`
- [x] Twitter: `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`

---

## 9) Performance Targets (Mobile)

- [ ] LCP ≤ 2.5s (audit: 6.9s)
- [ ] FCP ≤ 1.8s (audit: 2.632s)
- [ ] Total load ≤ 5s (audit: ~7.43s)
- [x] Kurangi render blocking
- [x] Minify JS
- [ ] Turunkan image payload (audit: 6.37MB image)

---

## ✅ Done Criteria (Ringkas)

- [x] Title 20–60 chars + keyword utama
- [x] Meta description ada (140–160 chars)
- [x] H1 relevan + heading tidak lompat
- [x] Konten ≥ 400 kata + readability lebih ringan
- [x] Internal links ≥ 10, outbound ≥ 3
- [x] Canonical + redirect www/non-www beres
- [x] robots.txt + sitemap.xml ada
- [x] OG/Twitter meta ada
- [ ] Mobile LCP turun mendekati ≤ 2.5s
