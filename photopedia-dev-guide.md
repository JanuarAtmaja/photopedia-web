# Panduan Pengembangan Photopedia
> Senior Full-Stack Laravel Developer Guide · v1.0

---

## Daftar Isi
1. [Ikhtisar Arsitektur](#1-ikhtisar-arsitektur)
2. [Setup Lingkungan Lokal (Laragon)](#2-setup-lingkungan-lokal-laragon)
3. [Struktur Proyek Laravel](#3-struktur-proyek-laravel)
4. [Setup Database & Supabase Storage](#4-setup-database--supabase-storage)
5. [Integrasi WebRTC (Fitur Kamera)](#5-integrasi-webrtc-fitur-kamera)
6. [Integrasi Google Drive API](#6-integrasi-google-drive-api)
7. [Integrasi SMTP Google (Email)](#7-integrasi-smtp-google-email)
8. [Integrasi GetAnalytics](#8-integrasi-getanalytics)
9. [Deploy ke Vercel](#9-deploy-ke-vercel)
10. [Daftar Bug & Kekurangan Arsitektur](#10-daftar-bug--kekurangan-arsitektur)
11. [Saran Perbaikan & Peningkatan Fitur](#11-saran-perbaikan--peningkatan-fitur)

---

## 1. Ikhtisar Arsitektur

```
┌─────────────────────────────────────────────────────────┐
│                      Browser (Gen-Z User)               │
│   Bootstrap UI · WebRTC Camera · JS Canvas Editor      │
└──────────────────────┬──────────────────────────────────┘
                       │ HTTP / REST
┌──────────────────────▼──────────────────────────────────┐
│              Laravel (MVC · Vercel Serverless)          │
│   Routes → Controllers → Services → Models             │
└──────┬──────────────┬──────────────────┬────────────────┘
       │              │                  │
  ┌────▼────┐   ┌─────▼─────┐   ┌───────▼──────┐
  │Supabase │   │Google APIs│   │  GetAnalytics│
  │DB+Store │   │Drive+SMTP │   │   Tracking   │
  └─────────┘   └───────────┘   └──────────────┘
```

**Stack Ringkas:**

| Layer | Teknologi |
|---|---|
| Backend | Laravel 11 (PHP 8.2+) |
| Frontend | Bootstrap 5.3, Vanilla JS, Canvas API |
| Database | Supabase (PostgreSQL) |
| Storage | Supabase Storage |
| Email | Gmail SMTP |
| Backup | Google Drive API v3 |
| Analytics | GetAnalytics |
| Kamera | WebRTC (`getUserMedia`) |
| Dev Lokal | Laragon |
| Deploy | Vercel (via `vercel-laravel`) |

---

## 2. Setup Lingkungan Lokal (Laragon)

### 2.1 Install Laragon
1. Unduh Laragon Full dari [laragon.org](https://laragon.org/download/).
2. Install, lalu jalankan Laragon. Aktifkan Apache/Nginx + MySQL (untuk dev lokal sementara).
3. Pastikan PHP versi minimal **8.2** terpilih di menu `PHP` > `Switch`.

### 2.2 Buat Proyek Laravel
```bash
# Di terminal Laragon (atau cmd)
cd C:\laragon\www
composer create-project laravel/laravel photopedia
cd photopedia
```

### 2.3 Konfigurasi `.env` Awal
```env
APP_NAME=Photopedia
APP_ENV=local
APP_KEY=    # akan di-generate
APP_DEBUG=true
APP_URL=http://photopedia.test

DB_CONNECTION=pgsql
DB_HOST=db.xxxxxxxx.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=YOUR_SUPABASE_PASSWORD

FILESYSTEM_DISK=supabase

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your@gmail.com
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your@gmail.com
MAIL_FROM_NAME="Photopedia"

GOOGLE_DRIVE_CLIENT_ID=
GOOGLE_DRIVE_CLIENT_SECRET=
GOOGLE_DRIVE_REFRESH_TOKEN=
GOOGLE_DRIVE_FOLDER_ID=

GETANALYTICS_KEY=your_getanalytics_key
```

```bash
php artisan key:generate
```

### 2.4 Tambahkan Domain Lokal
Di Laragon klik kanan tray icon > **Quick App** > tambahkan `photopedia.test` agar dapat diakses di browser.

---

## 3. Struktur Proyek Laravel

```
photopedia/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── HomeController.php         # Landing page
│   │   │   ├── PhotoController.php        # Alur kamera & foto
│   │   │   ├── FrameController.php        # Pilihan bingkai
│   │   │   ├── ExportController.php       # Email & Google Drive
│   │   │   └── AnalyticsController.php    # Proxy GetAnalytics
│   │   └── Middleware/
│   │       └── TrackAnalytics.php
│   ├── Services/
│   │   ├── SupabaseStorageService.php
│   │   ├── GoogleDriveService.php
│   │   └── MailService.php
│   └── Models/
│       ├── PhotoSession.php
│       └── Frame.php
├── resources/
│   ├── views/
│   │   ├── layouts/app.blade.php
│   │   ├── landing.blade.php
│   │   ├── frame-select.blade.php
│   │   ├── camera.blade.php
│   │   ├── preview.blade.php
│   │   └── export.blade.php
│   └── js/
│       ├── camera.js                      # WebRTC logic
│       ├── editor.js                      # Canvas filter/emoji/teks
│       └── upload.js                      # Drag and drop
├── routes/
│   └── web.php
├── database/
│   └── migrations/
│       ├── create_frames_table.php
│       └── create_photo_sessions_table.php
└── vercel.json
```

### 3.1 Definisi Routes
```php
// routes/web.php
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/frames', [FrameController::class, 'index'])->name('frames');
Route::post('/frames/select', [FrameController::class, 'select'])->name('frames.select');
Route::get('/camera', [PhotoController::class, 'camera'])->name('camera');
Route::post('/photo/save', [PhotoController::class, 'save'])->name('photo.save');
Route::get('/preview/{session}', [PhotoController::class, 'preview'])->name('photo.preview');
Route::post('/export/email', [ExportController::class, 'sendEmail'])->name('export.email');
Route::post('/export/drive', [ExportController::class, 'saveToDrive'])->name('export.drive');
Route::post('/export/print', [ExportController::class, 'print'])->name('export.print');
```

---

## 4. Setup Database & Supabase Storage

### 4.1 Buat Akun & Proyek Supabase
1. Daftar di [supabase.com](https://supabase.com).
2. Klik **New Project** → isi nama: `photopedia`, pilih region terdekat (Singapore).
3. Catat **Database Password**, **Project URL**, dan **Anon/Service Key** dari menu **Settings > API**.

### 4.2 Konfigurasi Koneksi Database di Laravel
Install driver PostgreSQL:
```bash
# Pastikan extension php_pgsql aktif di Laragon > PHP > Extensions
composer require "ext-pdo_pgsql"
```

Update `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=db.xxxxxxxxxxxxxxxx.supabase.co   # dari Settings > Database
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your_supabase_db_password
DB_SSLMODE=require
```

### 4.3 Buat Migrasi Tabel
```bash
php artisan make:migration create_frames_table
php artisan make:migration create_photo_sessions_table
```

```php
// database/migrations/create_frames_table.php
Schema::create('frames', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('thumbnail_url');
    $table->string('overlay_url');         // URL frame PNG di Supabase Storage
    $table->integer('slot_count');         // jumlah slot foto (2, 4, dst)
    $table->string('aspect_ratio');        // misal "2:3"
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

// database/migrations/create_photo_sessions_table.php
Schema::create('photo_sessions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('frame_id')->constrained();
    $table->string('raw_file_url')->nullable();       // URL file mentah
    $table->string('edited_file_url')->nullable();    // URL hasil edit
    $table->string('email')->nullable();              // email pengguna (opsional)
    $table->boolean('email_sent')->default(false);
    $table->boolean('drive_backed_up')->default(false);
    $table->timestamp('expires_at')->nullable();      // TTL sesi
    $table->timestamps();
});
```

```bash
php artisan migrate
```

### 4.4 Setup Supabase Storage Bucket
1. Di dashboard Supabase, buka menu **Storage**.
2. Klik **New Bucket** → beri nama `photopedia-photos`.
3. Set **Public bucket**: `true` (agar URL foto bisa diakses langsung).
4. Buat bucket kedua bernama `photopedia-frames` untuk menyimpan aset bingkai.

### 4.5 Integrasi Supabase Storage dengan Laravel
Install package Flysystem untuk Supabase (gunakan S3-compatible adapter):

```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

Supabase Storage kompatibel dengan S3 API. Tambahkan disk config:

```php
// config/filesystems.php
'disks' => [
    // ...
    'supabase' => [
        'driver' => 's3',
        'key'    => env('SUPABASE_KEY'),         // Service Role Key
        'secret' => env('SUPABASE_SECRET'),      // isi dengan string apapun (tidak dipakai)
        'region' => 'ap-southeast-1',
        'bucket' => env('SUPABASE_BUCKET', 'photopedia-photos'),
        'url'    => env('SUPABASE_URL').'/storage/v1/s3',
        'endpoint'=> env('SUPABASE_URL').'/storage/v1/s3',
        'use_path_style_endpoint' => true,
    ],
],
```

Tambahkan di `.env`:
```env
SUPABASE_URL=https://xxxxxxxx.supabase.co
SUPABASE_KEY=your_service_role_key
SUPABASE_SECRET=dummy
SUPABASE_BUCKET=photopedia-photos
```

### 4.6 Service Upload ke Supabase
```php
// app/Services/SupabaseStorageService.php
namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupabaseStorageService
{
    public function uploadPhoto(string $base64Image, string $type = 'raw'): string
    {
        $data = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $base64Image));
        $filename = Str::uuid() . '_' . $type . '.jpg';
        $path = 'sessions/' . date('Y/m/d') . '/' . $filename;

        Storage::disk('supabase')->put($path, $data, 'public');

        return Storage::disk('supabase')->url($path);
    }
}
```

---

## 5. Integrasi WebRTC (Fitur Kamera)

### 5.1 View Kamera (Blade)
```html
{{-- resources/views/camera.blade.php --}}
<div class="camera-wrapper position-relative">
    <video id="videoStream" autoplay playsinline muted
           class="w-100" style="transform: scaleX(-1);"></video>
    <canvas id="frameOverlay" class="position-absolute top-0 start-0 w-100 h-100"></canvas>
    <div id="countdown" class="display-1 text-white position-absolute top-50 start-50 translate-middle d-none"></div>
</div>
<button id="captureBtn" class="btn btn-primary btn-lg mt-3">📸 Ambil Foto</button>
```

### 5.2 JavaScript WebRTC
```javascript
// resources/js/camera.js
const video = document.getElementById('videoStream');
const canvas = document.getElementById('frameOverlay');
const ctx = canvas.getContext('2d');
let stream;

async function startCamera() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
            audio: false
        });
        video.srcObject = stream;
    } catch (err) {
        if (err.name === 'NotAllowedError') {
            alert('Akses kamera ditolak. Izinkan kamera di pengaturan browser.');
        } else {
            alert('Kamera tidak tersedia: ' + err.message);
        }
    }
}

async function capturePhoto() {
    const offscreen = document.createElement('canvas');
    offscreen.width = video.videoWidth;
    offscreen.height = video.videoHeight;
    const offCtx = offscreen.getContext('2d');

    // Mirror flip untuk selfie
    offCtx.translate(offscreen.width, 0);
    offCtx.scale(-1, 1);
    offCtx.drawImage(video, 0, 0);

    // Overlay bingkai
    const frameImg = new Image();
    frameImg.src = window.FRAME_URL; // dari blade: window.FRAME_URL = "{{ $frameUrl }}"
    await frameImg.decode();
    offCtx.setTransform(1, 0, 0, 1, 0, 0); // reset transform
    offCtx.drawImage(frameImg, 0, 0, offscreen.width, offscreen.height);

    return offscreen.toDataURL('image/jpeg', 0.92);
}

document.getElementById('captureBtn').addEventListener('click', async () => {
    // Countdown 3 detik
    const countdown = document.getElementById('countdown');
    countdown.classList.remove('d-none');
    for (let i = 3; i > 0; i--) {
        countdown.textContent = i;
        await new Promise(r => setTimeout(r, 1000));
    }
    countdown.classList.add('d-none');

    const photoData = await capturePhoto();
    // Kirim ke server
    const resp = await fetch('/photo/save', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ image: photoData, frame_id: window.FRAME_ID })
    });
    const { session_id } = await resp.json();
    window.location.href = '/preview/' + session_id;
});

startCamera();
```

### 5.3 Controller Menyimpan Foto
```php
// app/Http/Controllers/PhotoController.php
public function save(Request $request)
{
    $request->validate(['image' => 'required|string', 'frame_id' => 'required|integer']);

    $url = app(SupabaseStorageService::class)->uploadPhoto($request->image, 'raw');

    $session = PhotoSession::create([
        'id'           => Str::uuid(),
        'frame_id'     => $request->frame_id,
        'raw_file_url' => $url,
        'expires_at'   => now()->addHours(24),
    ]);

    return response()->json(['session_id' => $session->id]);
}
```

---

## 6. Integrasi Google Drive API

### 6.1 Buat Credentials di Google Cloud Console
1. Buka [console.cloud.google.com](https://console.cloud.google.com).
2. Buat project baru bernama `Photopedia`.
3. Aktifkan **Google Drive API** di **APIs & Services > Library**.
4. Buka **APIs & Services > Credentials** > **Create Credentials > OAuth 2.0 Client IDs**.
5. Application type: **Web application**.
6. Tambahkan Authorized redirect URI: `http://localhost:8000/oauth/callback` (dev) dan URL Vercel Anda.
7. Download `client_secret.json` dan catat **Client ID** & **Client Secret**.

### 6.2 Dapatkan Refresh Token (Satu Kali)
```bash
composer require google/apiclient
```

Buat route sementara untuk OAuth:
```php
// routes/web.php (sementara, hapus setelah mendapat token)
Route::get('/oauth/redirect', function () {
    $client = new Google\Client();
    $client->setClientId(env('GOOGLE_DRIVE_CLIENT_ID'));
    $client->setClientSecret(env('GOOGLE_DRIVE_CLIENT_SECRET'));
    $client->setRedirectUri(url('/oauth/callback'));
    $client->addScope(Google\Service\Drive::DRIVE_FILE);
    $client->setAccessType('offline');
    $client->setPrompt('consent');
    return redirect($client->createAuthUrl());
});

Route::get('/oauth/callback', function (Request $request) {
    $client = new Google\Client();
    $client->setClientId(env('GOOGLE_DRIVE_CLIENT_ID'));
    $client->setClientSecret(env('GOOGLE_DRIVE_CLIENT_SECRET'));
    $client->setRedirectUri(url('/oauth/callback'));
    $token = $client->fetchAccessTokenWithAuthCode($request->get('code'));
    dd($token['refresh_token']); // Salin nilai ini ke .env
});
```

Jalankan `php artisan serve`, buka `/oauth/redirect`, login Google, salin **refresh_token** ke `.env`.

### 6.3 Service Google Drive
```php
// app/Services/GoogleDriveService.php
namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

class GoogleDriveService
{
    private Drive $drive;

    public function __construct()
    {
        $client = new Client();
        $client->setClientId(env('GOOGLE_DRIVE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_DRIVE_CLIENT_SECRET'));
        $client->refreshToken(env('GOOGLE_DRIVE_REFRESH_TOKEN'));
        $this->drive = new Drive($client);
    }

    public function uploadFile(string $localPath, string $filename): string
    {
        $fileMetadata = new DriveFile([
            'name'    => $filename,
            'parents' => [env('GOOGLE_DRIVE_FOLDER_ID')],
        ]);

        $content = file_get_contents($localPath);

        $file = $this->drive->files->create($fileMetadata, [
            'data'       => $content,
            'mimeType'   => 'image/jpeg',
            'uploadType' => 'multipart',
            'fields'     => 'id, webViewLink',
        ]);

        return $file->webViewLink;
    }
}
```

### 6.4 Backup Otomatis via Controller
```php
// app/Http/Controllers/ExportController.php
public function saveToDrive(Request $request)
{
    $session = PhotoSession::findOrFail($request->session_id);

    // Download foto dari Supabase ke temp
    $tmpPath = tempnam(sys_get_temp_dir(), 'photo_') . '.jpg';
    file_put_contents($tmpPath, file_get_contents($session->edited_file_url ?? $session->raw_file_url));

    $filename = 'photopedia_' . $session->id . '_' . now()->format('Ymd_His') . '.jpg';

    $driveLink = app(GoogleDriveService::class)->uploadFile($tmpPath, $filename);

    unlink($tmpPath);
    $session->update(['drive_backed_up' => true]);

    return response()->json(['drive_link' => $driveLink]);
}
```

---

## 7. Integrasi SMTP Google (Email)

### 7.1 Buat App Password Gmail
1. Login ke Google Account dengan akun pengirim.
2. Buka **Manage Account > Security > 2-Step Verification** (aktifkan jika belum).
3. Di bawah bagian yang sama, buka **App Passwords**.
4. Pilih app: **Mail**, device: **Other** → tulis "Photopedia" → **Generate**.
5. Salin 16-karakter password ke `.env` sebagai `MAIL_PASSWORD`.

### 7.2 Mailable Laravel
```bash
php artisan make:mail PhotoResultMail
```

```php
// app/Mail/PhotoResultMail.php
class PhotoResultMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PhotoSession $session) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Foto Photopedia Anda Siap! 📸');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.photo-result');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromUrl($this->session->raw_file_url)->as('foto-mentah.jpg'),
            Attachment::fromUrl($this->session->edited_file_url)->as('foto-hasil.jpg'),
        ];
    }
}
```

```php
// app/Http/Controllers/ExportController.php
public function sendEmail(Request $request)
{
    $request->validate(['email' => 'required|email', 'session_id' => 'required']);

    $session = PhotoSession::findOrFail($request->session_id);
    $session->update(['email' => $request->email]);

    Mail::to($request->email)->send(new PhotoResultMail($session));
    $session->update(['email_sent' => true]);

    return response()->json(['success' => true]);
}
```

---

## 8. Integrasi GetAnalytics

### 8.1 Pasang Script (Layout Blade)
```html
{{-- resources/views/layouts/app.blade.php (di dalam <head>) --}}
<script>
    window.ga=window.ga||function(){(ga.q=ga.q||[]).push(arguments)};
    ga('create', '{{ env("GETANALYTICS_KEY") }}', 'auto');
    ga('send', 'pageview');
</script>
<script async src="https://cdn.getanalytics.io/analytics.min.js"></script>
```

### 8.2 Track Event Custom
```javascript
// Pada setiap aksi penting di JS frontend:
function trackEvent(category, action, label = '') {
    if (window.ga) {
        ga('send', 'event', category, action, label);
    }
}

// Contoh penggunaan:
trackEvent('Frame', 'select', frameId);
trackEvent('Photo', 'capture');
trackEvent('Export', 'email_sent');
trackEvent('Export', 'drive_backed_up');
trackEvent('Export', 'print');
```

### 8.3 Middleware Server-Side (Opsional)
```php
// app/Http/Middleware/TrackAnalytics.php
public function handle(Request $request, Closure $next)
{
    // Simpan data sesi anonim untuk analitik internal
    \Log::channel('analytics')->info('page_view', [
        'path'       => $request->path(),
        'user_agent' => $request->userAgent(),
        'timestamp'  => now()->toIso8601String(),
    ]);
    return $next($request);
}
```

---

## 9. Deploy ke Vercel

> **Catatan**: Laravel adalah framework PHP berbasis server. Vercel adalah platform serverless yang secara natif mendukung Node.js. Untuk mendeploy Laravel ke Vercel, gunakan runtime PHP via community builder `vercel-php`.

### 9.1 Install Vercel CLI
```bash
npm install -g vercel
```

### 9.2 Konfigurasi `vercel.json`
```json
{
  "version": 2,
  "framework": null,
  "functions": {
    "api/index.php": {
      "runtime": "vercel-php@0.7.2"
    }
  },
  "routes": [
    { "src": "/(.*)", "dest": "/api/index.php" }
  ],
  "env": {
    "APP_ENV": "production",
    "APP_DEBUG": "false",
    "LOG_CHANNEL": "stderr"
  }
}
```

### 9.3 Buat Entry Point PHP
```php
// api/index.php
<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request = Illuminate\Http\Request::capture());
$response->send();
$kernel->terminate($request, $response);
```

### 9.4 Konfigurasi Environment Variables di Vercel
1. Login: `vercel login`
2. Inisialisasi: `vercel` (di root proyek) → ikuti prompt.
3. Di dashboard Vercel, buka proyek Anda > **Settings > Environment Variables**.
4. Tambahkan **semua variabel** dari `.env` production Anda satu per satu:
   - `APP_KEY`, `DB_*`, `MAIL_*`, `GOOGLE_DRIVE_*`, `SUPABASE_*`, `GETANALYTICS_KEY`
5. Set `APP_KEY` dengan nilai dari `php artisan key:generate --show`.

### 9.5 Deploy
```bash
# Deploy ke preview
vercel

# Deploy ke production
vercel --prod
```

### 9.6 Hal Penting Setelah Deploy
- **Storage lokal tidak tersedia** di Vercel (ephemeral filesystem). Semua file harus masuk ke Supabase Storage — tidak boleh ada `Storage::disk('local')`.
- **Artisan commands** tidak bisa dijalankan di Vercel. Jalankan migrasi dari lokal dengan env production:
  ```bash
  php artisan migrate --env=production
  ```
- **Session**: gunakan driver `database` atau `cookie`, bukan `file`.
  ```env
  SESSION_DRIVER=cookie
  CACHE_STORE=database
  ```

---

## 10. Daftar Bug & Kekurangan Arsitektur

### 10.1 Masalah Kritis

| # | Masalah | Dampak |
|---|---------|--------|
| 1 | **Laravel tidak dirancang untuk serverless** (Vercel). Cold start bisa lambat, session file-based akan gagal. | Performa & fungsionalitas |
| 2 | **Foto besar dikirim sebagai Base64** lewat POST body. Foto 4–6 MB dalam Base64 = ~8 MB payload per request. | Timeout & boros bandwidth |
| 3 | **Tidak ada antrian email** (`Queue`). Jika Gmail SMTP lambat, response user tertahan. | UX buruk, timeout |
| 4 | **Refresh token Google Drive hardcoded di `.env`** tanpa rotasi otomatis. Token bisa expired. | Backup gagal diam-diam |
| 5 | **Tidak ada TTL / cleanup foto**. Supabase Storage akan terus terisi tanpa pembersihan. | Biaya storage meningkat |
| 6 | **Tidak ada validasi MIME type sisi server** untuk file upload drag-and-drop. | Risiko upload file berbahaya |
| 7 | **WebRTC `getUserMedia` tidak bekerja di HTTP** (hanya HTTPS). Dev lokal via `http://` akan error. | Kamera tidak bisa diakses |
| 8 | **Canvas `toDataURL` tidak tersedia di Safari iOS lama** untuk JPEG. | Kompatibilitas mobile |
| 9 | **Email attachment langsung dari URL Supabase** dapat gagal jika URL expired atau private. | Email tanpa lampiran |
| 10 | **Tidak ada rate limiting** pada endpoint `/photo/save` dan `/export/email`. | Rentan spam & abuse |

### 10.2 Kekurangan Fitur

| # | Kekurangan |
|---|-----------|
| 1 | Tidak ada fallback jika kamera tidak tersedia (upload foto dari galeri). |
| 2 | Tidak ada indikator progres upload foto ke Supabase. |
| 3 | Tidak ada validasi email sebelum dikirim (cukup format, bukan verifikasi). |
| 4 | Editor (filter, emoji, teks) diimplementasikan di Canvas JS, tapi tidak ada undo/redo. |
| 5 | Print langsung dari browser (`window.print()`) tidak bisa mengontrol ukuran cetak fisik. |
| 6 | Tidak ada mekanisme berbagi foto via link tanpa email. |
| 7 | GetAnalytics tidak melacak funnel completion rate per langkah. |

---

## 11. Saran Perbaikan & Peningkatan Fitur

### 11.1 Solusi Bug Kritis

**Bug #1 – Vercel + Laravel**: Gunakan [Laravel Octane](https://laravel.com/docs/octane) dengan Frankenphp, atau pertimbangkan deploy ke **Railway.app** / **Render.com** yang lebih cocok untuk PHP long-running server. Tetap bisa gratis di tier awal.

**Bug #2 – Upload Base64 besar**: Ganti alur upload. Minta Supabase Storage pre-signed URL dari server, lalu upload langsung dari browser ke Supabase (tidak lewat Laravel):
```javascript
// 1. Minta pre-signed URL dari server Laravel
const { uploadUrl } = await fetch('/photo/presign').then(r => r.json());
// 2. Upload langsung ke Supabase dari browser
await fetch(uploadUrl, { method: 'PUT', body: blob });
```

**Bug #3 – Antrian email**: Aktifkan Laravel Queue dengan driver `database`:
```bash
php artisan queue:table && php artisan migrate
```
```env
QUEUE_CONNECTION=database
```
```php
Mail::to($email)->queue(new PhotoResultMail($session)); // bukan send()
```
Jalankan worker (atau gunakan cron di Railway/Render).

**Bug #7 – HTTP/HTTPS WebRTC**: Gunakan Laragon dengan HTTPS (aktifkan SSL di Laragon > SSL) atau gunakan `localhost` (WebRTC mengizinkan `localhost` meski HTTP).

**Bug #10 – Rate limiting**: Tambahkan di routes:
```php
Route::middleware(['throttle:10,1'])->group(function () {
    Route::post('/photo/save', ...);
    Route::post('/export/email', ...);
});
```

### 11.2 Peningkatan Fitur (Gratis & Open Source)

| Fitur | Tools / API | Keterangan |
|---|---|---|
| **Kompresi foto otomatis** | [Intervention Image](https://image.intervention.io/) (open source) | Kompres sebelum upload, hemat storage |
| **Filter foto real-time** | [CamanJS](https://camanjs.com/) (open source) | Library filter canvas yang ringan |
| **Watermark otomatis** | Intervention Image | Tambahkan logo Photopedia ke semua foto |
| **Berbagi via link** | Supabase Storage public URL | Generate short URL sesi tanpa login |
| **QR Code untuk print** | [Simple QRCode Laravel](https://github.com/SimpleSoftwareIO/simple-qrcode) (open source) | Scan QR untuk unduh softcopy |
| **Deteksi wajah (framing otomatis)** | [face-api.js](https://github.com/justadudewhohacks/face-api.js) (open source) | Panduan posisi wajah di kamera |
| **Progressive Web App (PWA)** | [Laravel PWA](https://github.com/silviolleite/laravel-pwa) | Pengguna bisa install di HP tanpa app store |
| **Background removal** | [background-removal-js](https://github.com/imgly/background-removal-js) (open source) | Hapus background sebelum foto diambil |
| **Antrian email robust** | [Resend](https://resend.com/) (gratis 3.000/bulan) | Lebih andal dari Gmail SMTP untuk production |
| **Logging & error tracking** | [Sentry Laravel](https://sentry.io/) (gratis tier) | Pantau error production secara real-time |

### 11.3 Rekomendasi Alternatif Deploy

Jika Vercel terlalu bermasalah untuk Laravel, gunakan alternatif ini (semua punya free tier):

| Platform | Kelebihan |
|---|---|
| **Railway.app** | Support PHP native, persistent storage, mudah setup |
| **Render.com** | Free tier, support PHP, cron job built-in untuk queue worker |
| **Fly.io** | Dockerized, bisa Laravel Octane, edge deployment |

---

*Dokumen ini dibuat berdasarkan PRD dan SRD Photopedia. Perbarui sesuai kebutuhan proyek.*
