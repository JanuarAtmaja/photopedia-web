# 📸 Photopedia — Setup & Deployment Guide

> Photobooth digital untuk Gen-Z · PHP Native · Vercel

---

## Struktur File

```
photopedia/
├── public/
│   └── index.php              ← Entry point utama (semua halaman)
├── config/
│   ├── env.php                ← Loader environment variables
│   └── helpers.php            ← Helper functions + frame discovery
├── api/
│   ├── send-email.php         ← POST endpoint kirim email (Resend API)
│   └── frames.php             ← GET endpoint daftar frame (JSON)
├── assets/
│   ├── css/style.css          ← Stylesheet global (lavender/purple palette)
│   ├── js/
│   │   ├── app.js             ← SPA router + Supabase upload + QR + email
│   │   ├── camera.js          ← WebRTC kamera + countdown + capture
│   │   └── editor.js          ← Canvas editor: filter, stiker, teks
│   └── frames/                ← ← Frame PNG kamu taruh di sini!
│       ├── frame-film-strip.png
│       ├── frame-music-player.png
│       └── frame-y2k-grid.png
├── vercel.json                ← Konfigurasi deployment Vercel
├── .env.example               ← Template environment variables
└── README.md                  ← File ini
```

---

## Cara Tambah Frame Baru

> **Semudah copy-paste!** Sistem otomatis mendeteksi frame baru.

1. Buat atau dapatkan file PNG dengan **background transparan** (PNG-24)
2. Beri nama file dengan format: **`frame-[nama-slug].png`**
   - ✅ `frame-birthday-party.png`
   - ✅ `frame-halloween.png`
   - ❌ `birthday party frame.png` ← spasi tidak boleh
3. Copy file ke folder **`assets/frames/`**
4. Deploy ulang → frame langsung muncul di picker!

**Rekomendasi ukuran frame:** `1280 × 960 px` (rasio 4:3) agar sesuai output kamera

---

## Setup Lokal (Development)

### 1. Requirement

- PHP 8.1+ (cek: `php -v`)
- Laragon / XAMPP / MAMP, atau cukup PHP built-in server

### 2. Clone / Extract project

```bash
# Jika pakai git
git clone https://github.com/kamu/photopedia.git
cd photopedia

# Atau extract zip langsung ke folder kerja
```

### 3. Buat file `.env`

```bash
# Windows
copy .env.example .env

# Mac/Linux
cp .env.example .env
```

Buka `.env` dengan teks editor dan isi nilainya (lihat bagian **Setup Services** di bawah).

### 4. Jalankan server lokal

```bash
# Opsi A: PHP built-in server (dari root folder project)
php -S localhost:8000 -t public

# Opsi B: Laragon — taruh folder project di C:\laragon\www\photopedia
# lalu akses http://photopedia.test
```

Buka browser: **http://localhost:8000**

> ⚠️ **WebRTC (kamera) butuh HTTPS atau localhost.**
> PHP built-in server di `localhost` sudah aman untuk kamera.
> Jika pakai domain custom di Laragon, aktifkan SSL dulu:
> klik kanan Laragon tray → **SSL** → centang domain.

---

## Setup Services

### 🟣 Supabase (Storage foto)

1. Daftar/login di [supabase.com](https://supabase.com)
2. **New Project** → isi nama: `photopedia`, pilih region: **Singapore**
3. Tunggu project selesai dibuat (~2 menit)
4. Buka **Settings → API**, catat:
   - **Project URL** → isi ke `SUPABASE_URL`
   - **anon / public key** → isi ke `SUPABASE_ANON_KEY`
   - **service_role key** → isi ke `SUPABASE_SERVICE_ROLE_KEY` *(jangan expose ke client!)*
5. Buka **Storage → New Bucket**:
   - Nama: `photopedia-photos`
   - **Public bucket: ON** ✅
   - Klik **Create Bucket**
6. Isi `SUPABASE_BUCKET=photopedia-photos` di `.env`

**Setup RLS Policy (agar upload tanpa login bisa):**

Di Supabase Dashboard → Storage → `photopedia-photos` → **Policies → New Policy**:

```sql
-- Policy: Allow public INSERT (upload)
CREATE POLICY "Allow public uploads"
ON storage.objects FOR INSERT
TO anon
WITH CHECK (bucket_id = 'photopedia-photos');

-- Policy: Allow public SELECT (baca URL)
CREATE POLICY "Allow public reads"
ON storage.objects FOR SELECT
TO anon
USING (bucket_id = 'photopedia-photos');
```

---

### 📧 Resend API (Email)

1. Daftar di [resend.com](https://resend.com) (gratis 3.000 email/bulan)
2. Buka **API Keys → Create API Key**
3. Salin key → isi `RESEND_API_KEY` di `.env`
4. **Untuk production:** verifikasi domain kamu di **Domains → Add Domain**
   - Ikuti instruksi tambah DNS record (5–10 menit propagasi)
   - Isi `RESEND_FROM=Photopedia <noreply@domainmu.com>`
5. **Untuk testing lokal:** gunakan Resend sandbox, email hanya terkirim ke alamat yang diverifikasi

---

## Deploy ke Vercel

### Prerequisite

```bash
# Install Vercel CLI (sekali saja)
npm install -g vercel

# Login ke akun Vercel
vercel login
```

### Deploy Pertama Kali

```bash
# Di root folder project (d:\Photopedia)
vercel

# Ikuti prompt:
# ? Set up and deploy "photopedia"? → Y
# ? Which scope? → pilih akun kamu
# ? Link to existing project? → N
# ? What's your project's name? → photopedia
# ? In which directory is your code located? → ./
# → Vercel akan detect settings dari vercel.json
```

Setelah deploy preview berhasil, catat URL preview-nya.

### Setup Environment Variables di Vercel

1. Buka [vercel.com/dashboard](https://vercel.com/dashboard)
2. Pilih project **photopedia**
3. Buka **Settings → Environment Variables**
4. Tambahkan satu per satu:

| Variable | Value | Environment |
|---|---|---|
| `RESEND_API_KEY` | `re_xxxx...` | Production, Preview |
| `RESEND_FROM` | `Photopedia <noreply@domain.com>` | Production, Preview |
| `SUPABASE_URL` | `https://xxxx.supabase.co` | Production, Preview |
| `SUPABASE_ANON_KEY` | `eyJhbGci...` | Production, Preview |
| `SUPABASE_SERVICE_ROLE_KEY` | `eyJhbGci...` | Production *(jangan di Preview!)* |
| `SUPABASE_BUCKET` | `photopedia-photos` | Production, Preview |
| `APP_URL` | `https://photopedia.vercel.app` | Production |
| `APP_ENV` | `production` | Production, Preview |

### Deploy ke Production

```bash
vercel --prod
```

Aplikasi live di: **https://photopedia.vercel.app** (atau custom domain kamu)

### Custom Domain (Opsional)

1. Vercel Dashboard → project → **Settings → Domains**
2. Tambahkan domain: `photopedia.namakamu.com`
3. Ikuti instruksi tambah CNAME record di DNS provider domain kamu
4. Update `APP_URL` di env vars ke domain baru

---

## Troubleshooting

### ❌ Kamera tidak muncul / error NotAllowedError

- Pastikan buka via `localhost` atau `https://`
- HTTP biasa (bukan localhost) diblokir browser untuk WebRTC
- Chrome: klik ikon 🔒 di address bar → **Site settings → Camera → Allow**

### ❌ Frame tidak muncul di picker

- Cek nama file: harus format `frame-[slug].png`
- Cek folder: file ada di `assets/frames/`
- Cek permission file (Linux/Mac): `chmod 644 assets/frames/*.png`

### ❌ Upload foto gagal / error CORS Supabase

- Cek Supabase RLS policy sudah ditambahkan
- Cek `SUPABASE_URL` dan `SUPABASE_ANON_KEY` benar
- Buka browser DevTools → Network → lihat error detail response Supabase

### ❌ Email tidak terkirim

- Cek `RESEND_API_KEY` benar
- Cek domain sudah diverifikasi di Resend dashboard
- Lihat log di Vercel Dashboard → project → **Logs**
- Test endpoint langsung:
  ```bash
  curl -X POST https://photopedia.vercel.app/api/send-email \
    -H "Content-Type: application/json" \
    -d '{"to":"test@email.com","photo_url":"https://example.com/foto.jpg","session_id":"test-123"}'
  ```

### ❌ 500 Error di Vercel

- Buka Vercel Dashboard → **Logs** → cari error message
- Pastikan semua env vars sudah diisi di Vercel Settings
- Pastikan `vercel.json` tidak berubah dari yang disediakan

---

## Tech Stack

| Layer | Teknologi |
|---|---|
| Frontend | Vanilla HTML/CSS/JS, Canvas API, WebRTC |
| Backend | PHP 8.1+ Native (tanpa framework) |
| Filters | CamanJS 4.1.2 |
| QR Code | QRCode.js |
| Email | Resend API |
| Storage | Supabase Storage (S3-compatible) |
| Hosting | Vercel + vercel-php@0.7.2 |
| Font | Plus Jakarta Sans (Google Fonts) |

---

## Alur Upload Foto (Penjelasan Teknis)

```
Browser User
    │
    ├── Ambil foto → Canvas.toBlob() → File JPEG di memori browser
    │
    ├── Fetch POST langsung ke Supabase Storage REST API
    │   (pakai SUPABASE_ANON_KEY — aman untuk client-side)
    │   URL: https://[project].supabase.co/storage/v1/object/[bucket]/[path]
    │
    ├── Supabase kembalikan → Public URL foto
    │
    └── Public URL dipakai untuk:
        ├── Tampilan di halaman export
        ├── QR Code generation
        ├── Link berbagi
        └── Lampiran email via /api/send-email.php → Resend API
```

> **Kenapa upload langsung dari browser?**
> Vercel Functions punya limit payload **4.5 MB**. Foto JPEG bisa 3–8 MB.
> Upload lewat PHP backend = risiko timeout & gagal.
> Upload langsung browser → Supabase = tidak ada batas, lebih cepat.

---

*Photopedia © 2025 · Dibuat dengan 💜 untuk Gen-Z Indonesia*
