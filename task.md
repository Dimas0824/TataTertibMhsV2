# ✅ OnPage SEO Optimization Checklist (DiscipLink - Home)

> Target utama halaman: **tata tertib mahasiswa** (+ variasi: aturan kampus, pelanggaran, sanksi, kedisiplinan, DiscipLink, Polinema)

---

## 🚨 Issues to Fix (Prioritas Berdasarkan Audit)

### HIGH

- [ ] **URL Canonicalization**: pilih 1 URL utama (preferred) lalu redirect semua variasi ke URL itu  
  - [ ] Tentukan: `https://dimspersonal.my.id/` **atau** `https://www.dimspersonal.my.id/` (pilih satu)
  - [ ] Terapkan redirect 301 dari versi lain → preferred
  - [ ] Tambahkan `<link rel="canonical" href="PREFERRED_URL" />` di semua halaman

- [ ] **LCP ≤ 2.5s (Google recommendation)**  
  - [ ] Optimalkan elemen LCP (audit: section `.judul` / hero text)
  - [ ] Pastikan font tidak blocking dan hero cepat muncul
  - [ ] Kurangi ukuran image (audit: total page ~6.74MB, 94.5% image)

- [ ] **Eliminate render-blocking resources**  
  - [ ] Defer/async JS non-kritis
  - [ ] Inline critical CSS seperlunya / preload CSS utama
  - [ ] Tunda script pihak ketiga sampai interaksi / setelah load

- [ ] **Loading time < 5s** (audit: ~7.43s, risiko drop pengunjung)  
  - [ ] Turunkan total payload, fokus utama: image
  - [ ] Pastikan caching + kompresi + optimasi request

- [ ] **Keyword distribution**: keyword utama harus muncul di title + meta description + headings  
  - [ ] Pastikan “tata”, “tertib”, “mahasiswa”, “disciplink” muncul natural di `<title>`, meta description, H1/H2

- [ ] **Add Meta Description** (audit: tidak ada)  
  - [ ] Tambahkan `<meta name="description" content="...">` 140–160 karakter

- [ ] **Add sitemap.xml** (audit: tidak ada)  
  - [ ] Generate sitemap dan submit ke Google Search Console

- [ ] **Add robots.txt** (audit: tidak ada)  
  - [ ] Buat robots.txt minimal + referensi sitemap

- [ ] **Modern image formats** (WebP/AVIF)  
  - [ ] Convert PNG/JPG → WebP/AVIF untuk hero + gambar besar
  - [ ] Pastikan fallback bila perlu

### MEDIUM

- [ ] **Minify JavaScript** (audit: JS tidak minified)  
  - [ ] Minify bundle/asset JS produksi

- [ ] **Custom 404 page**  
  - [ ] Buat halaman 404 dengan link populer + search + tombol kembali

- [ ] **Serve properly sized images**  
  - [ ] Gunakan responsive images (`srcset`, `sizes`) atau image optimization framework
  - [ ] Hindari kirim gambar besar untuk viewport kecil

- [ ] **Avoid distorted images**  
  - [ ] Perbaiki aspect ratio (gunakan CSS `object-fit`, ukuran proporsional)

- [ ] **Structured data (JSON-LD)**  
  - [ ] Tambahkan schema minimal: `WebSite`, `Organization`, `BreadcrumbList`
  - [ ] Untuk news: `Article`

- [ ] **Add Google Analytics** (atau alternatif)  
  - [ ] Pasang GA4 untuk monitoring SEO/traffic & debugging funnel

- [ ] **Social media meta tags** (OG/Twitter)  
  - [ ] Tambahkan Open Graph + Twitter Cards untuk preview share yang rapi

### LOW

- [ ] **HSTS header** (`Strict-Transport-Security`)  
  - [ ] Aktifkan HSTS untuk forcing HTTPS

- [ ] **Favicon**  
  - [ ] Tambahkan favicon + referensi yang benar (`<link rel="icon" ...>`)

---

## 1) Title Tag (Prioritas Tinggi)

- [ ] Panjang rekomendasi 20–60 karakter (aman untuk SERP)
- [ ] Minimal 6 kata (audit: cuma 2 kata)
- [ ] Masukkan keyword utama + brand

Contoh:

- [ ] `Tata Tertib Mahasiswa Polinema - Aturan & Sanksi | DiscipLink`
- [ ] `DiscipLink Polinema: Tata Tertib Mahasiswa, Pelanggaran & Sanksi`

---

## 2) Meta Description (Prioritas Tinggi)

- [ ] Tambahkan meta description (audit: kosong)
- [ ] 140–160 karakter, informatif, ada CTA ringan

Contoh:

- [ ] `Satu pusat informasi tata tertib mahasiswa Polinema: aturan, pelanggaran, sanksi, dan berita kedisiplinan. Cek ketentuan terbaru di DiscipLink.`

---

## 3) Headings & Keyword Placement

- [ ] H1 jangan “Home” → harus topik utama
  - [ ] H1: `Tata Tertib Mahasiswa Polinema (DiscipLink)`
- [ ] Pastikan tidak ada lompat heading (missing H4)  
- [ ] Masukkan keyword utama di H2/H3 secara natural

---

## 4) Konten Body (≥ 400 kata)

- [ ] Tambah konten jadi ≥ 400–800 kata (audit: 197 kata)
- [ ] Tambah section: Apa itu DiscipLink, Cara pakai, FAQ, ringkasan aturan/sanksi, highlight news
- [ ] Perbaiki readability (Flesch 28 terlalu sulit): kalimat pendek + bullet list

---

## 5) Internal & Outbound Links

- [ ] Internal link ≥ 10 (audit: 8)
- [ ] Outbound link ≥ 3–5 ke sumber resmi & relevan (audit: 2)
- [ ] Perbaiki link tanpa `title` attribute (audit: banyak)
- [ ] Perbaiki link tanpa link text (audit: ada)
- [ ] Untuk external `target="_blank"` wajib `rel="noopener noreferrer"`

---

## 6) Images (Speed + SEO)

- [ ] Gunakan WebP/AVIF (audit: belum modern format)
- [ ] Properly sized (audit: oversized)
- [ ] Fix aspect ratio (audit: distorted)
- [ ] Tambahkan `width` dan `height` pada `<img>` (PSI warning)
- [ ] Lazyload gambar non-hero
- [ ] Kompresi agresif (target page weight < 2MB untuk mobile bila memungkinkan)

---

## 7) Technical SEO Essentials

- [ ] Canonical tag di semua halaman
- [ ] robots.txt + sitemap.xml
- [ ] Pastikan www/non-www konsisten (redirect 301)
- [ ] Minimalkan variasi URL yang respons (hindari duplicate)

---

## 8) Social Preview

- [ ] Open Graph: `og:title`, `og:description`, `og:image`, `og:url`, `og:type`
- [ ] Twitter: `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`

---

## 9) Performance Targets (Mobile)

- [ ] LCP ≤ 2.5s (audit: 6.9s)
- [ ] FCP ≤ 1.8s (audit: 2.632s)
- [ ] Total load ≤ 5s (audit: ~7.43s)
- [ ] Kurangi render blocking
- [ ] Minify JS
- [ ] Turunkan image payload (audit: 6.37MB image)

---

## ✅ Done Criteria (Ringkas)

- [ ] Title 20–60 chars + keyword utama
- [ ] Meta description ada (140–160 chars)
- [ ] H1 relevan + heading tidak lompat
- [ ] Konten ≥ 400 kata + readability lebih ringan
- [ ] Internal links ≥ 10, outbound ≥ 3
- [ ] Canonical + redirect www/non-www beres
- [ ] robots.txt + sitemap.xml ada
- [ ] OG/Twitter meta ada
- [ ] Mobile LCP turun mendekati ≤ 2.5s
