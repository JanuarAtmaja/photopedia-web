<?php
// public/index.php — Entry point Photopedia (PHP Native SPA Shell)
require_once dirname(__DIR__) . '/config/helpers.php';

// ── Routing untuk API calls ─────────────────────────────────
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Redirect API calls ke handler-nya
if ($uri === '/api/send-email.php' || $uri === '/api/send-email') {
    require_once dirname(__DIR__) . '/api/send-email.php';
    exit;
}
if ($uri === '/api/frames.php' || $uri === '/api/frames') {
    require_once dirname(__DIR__) . '/api/frames.php';
    exit;
}

// Handle static assets routing in Vercel environment
$isAsset = strpos($uri, '/assets/') === 0;
$isRootAsset = in_array($uri, [
    '/favicon.ico',
    '/favicon-16x16.png',
    '/favicon-32x32.png',
    '/apple-touch-icon.png',
    '/android-chrome-192x192.png',
    '/android-chrome-512x512.png',
    '/site.webmanifest'
]);

if ($isAsset || $isRootAsset) {
    $filePath = dirname(__DIR__) . $uri;
    if (file_exists($filePath) && is_file($filePath)) {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'webmanifest' => 'application/manifest+json'
        ];
        
        $mimeType = $mimeTypes[$ext] ?? mime_content_type($filePath);
        
        header("Content-Type: $mimeType");
        header("Cache-Control: public, max-age=31536000"); // Cache static assets
        readfile($filePath);
        exit;
    } else {
        http_response_code(404);
        echo "File not found";
        exit;
    }
}

// ── Config untuk JS (dikirim aman, bukan expose secret) ─────
$jsConfig = json_encode([
    'supabaseUrl'    => env('SUPABASE_URL', ''),
    'supabaseAnon'   => env('SUPABASE_ANON_KEY', ''),
    'supabaseBucket' => env('SUPABASE_BUCKET', 'photopedia-photos'),
    'appEnv'         => env('APP_ENV', 'production'),
], JSON_HEX_TAG | JSON_HEX_QUOT);

?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- SEO -->
  <title>Photopedia — Photobooth Digital Gen-Z</title>
  <meta name="description" content="Photobooth digital gratis! Ambil foto dengan bingkai lucu, tambahkan filter & stiker, lalu bagikan kenangan indahmu bersama teman.">
  <meta name="keywords" content="photobooth, digital, gen-z, foto, filter, frame, bingkai, selfie">
  <meta name="theme-color" content="#4B3FA0">

  <!-- Open Graph -->
  <meta property="og:title" content="Photopedia — Photobooth Digital Gen-Z">
  <meta property="og:description" content="Photobooth digital gratis dengan frame & filter keren!">
  <meta property="og:type" content="website">
  <meta property="og:image" content="<?= htmlspecialchars(env('APP_URL', '')) ?>/assets/frames/frame-film-strip.png">

  <!-- Favicon -->
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
  <link rel="manifest" href="/site.webmanifest">

  <!-- App Styles -->
  <link rel="stylesheet" href="/assets/css/style.css?v=20">

  <!-- Config untuk JS (hanya anon key, AMAN untuk expose ke client) -->
  <script>
    window.PHOTOPEDIA_CONFIG = <?= $jsConfig ?>;
  </script>
</head>
<body>

<!-- ═══════════════════════════════════════════════════════════
     TICKER
  ══════════════════════════════════════════════════════════ -->
<div class="ticker-wrap" aria-hidden="true">
  <div class="ticker-track">
    <?php
    $tickerItems = [
        'Photopedia', 'Photobooth Digital', 'Frame Keren',
        'Filter Aesthetic', 'Cetak Kenangan', 'Gen-Z Vibes',
        'Bagikan Momenmu', 'Foto Sekarang', 'Gratis & Mudah',
        'Photopedia', 'Photobooth Digital', 'Frame Keren',
        'Filter Aesthetic', 'Cetak Kenangan', 'Gen-Z Vibes',
        'Bagikan Momenmu', 'Foto Sekarang', 'Gratis & Mudah',
    ];
    foreach ($tickerItems as $item) {
        echo '<span class="ticker-item">' . htmlspecialchars($item) . '</span>';
    }
    ?>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     HEADER
  ══════════════════════════════════════════════════════════ -->
<header class="site-header">
  <div class="container header-inner">
    <!-- Logo -->
    <a href="/" class="logo" aria-label="Photopedia Beranda">
      <div aria-hidden="true" style="display: flex; align-items: center;"><img src="/assets/images/Logo.png" alt="Logo" style="height: 32px; object-fit: contain;"></div>
    </a>

    <!-- Progress Steps -->
    <nav aria-label="Langkah proses">
      <ol class="progress-steps">
        <li class="step active" data-step="0">
          <span class="step-num" aria-label="Langkah 1">1</span>
          <span class="step-label">Mulai</span>
        </li>
        <li class="step" data-step="1">
          <span class="step-num" aria-label="Langkah 2">2</span>
          <span class="step-label">Frame</span>
        </li>
        <li class="step" data-step="2">
          <span class="step-num" aria-label="Langkah 3">3</span>
          <span class="step-label">Foto</span>
        </li>
        <li class="step" data-step="3">
          <span class="step-num" aria-label="Langkah 4">4</span>
          <span class="step-label">Edit</span>
        </li>
        <li class="step" data-step="4">
          <span class="step-num" aria-label="Langkah 5">5</span>
          <span class="step-label">Ekspor</span>
        </li>
      </ol>
    </nav>
    <!-- Additional Nav -->
      <div class="header-actions" style="margin-left:auto; display:flex; gap:12px;">
        <button id="nav-gallery-btn" class="btn btn-ghost btn-sm" aria-label="Buka Gallery">Gallery</button>
      </div>
    </div>
  </div>
</header>
<style>
  .header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    flex-wrap: wrap;
  }
</style>

<main id="app-main">

  <!-- ═══════════════════════════════════════════════
       PAGE 1: LANDING
    ════════════════════════════════════════════════ -->
  <section id="page-landing" class="page-section active" aria-label="Halaman utama">
    <div class="container">
      <div class="landing-hero">
        <div class="hero-badge">Photobooth Digital Gen-Z</div>

        <h1 class="hero-title">
          Abadikan Momen<br>
          <span>Paling Aesthetic</span>
        </h1>

        <p class="hero-sub">
          Photobooth digital dengan bingkai keren, filter aesthetic, dan stiker lucu.
          Gratis, tanpa download aplikasi!
        </p>

        <div class="hero-cta">
          <button id="start-btn" class="btn btn-primary btn-lg" aria-label="Mulai buat foto">
            Mulai Foto Sekarang
          </button>
          <button id="landing-gallery-btn" class="btn btn-surface btn-lg" aria-label="Lihat Gallery">
            Lihat Gallery
          </button>
          <a href="#cara-pakai" class="btn btn-ghost btn-lg">Cara Pakai →</a>
        </div>

        <div class="feature-pills" role="list" aria-label="Fitur utama">
          <div class="pill" role="listitem">Filter Instagram</div>
          <div class="pill" role="listitem">Bingkai Keren</div>
          <div class="pill" role="listitem">Stiker & Teks</div>
          <div class="pill" role="listitem">Kirim via Email</div>
          <div class="pill" role="listitem">Share Link</div>
        </div>

        <!-- Hero Mockup -->
        <div class="hero-mockup" aria-hidden="true">
          <div class="mockup-inner">
            <div class="mockup-screen">
              <div class="mockup-pulse"></div>
              <span class="mockup-camera-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Cara Pakai -->
      <div id="cara-pakai" style="padding: 48px 0;">
        <div class="section-header">
          <h2 class="section-title">Cara Pakai</h2>
          <p class="section-sub">Cuma 4 langkah mudah!</p>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;">
          <?php
          $steps = [
              ['01', 'Pilih Bingkai', 'Pilih template frame favoritmu'],
              ['02', 'Ambil Foto',    'Foto dengan kamera langsung dari browser'],
              ['03', 'Edit & Filter', 'Tambah filter, stiker, dan teks'],
              ['04', 'Bagikan',       'Download, share link, atau kirim via email'],
          ];
          foreach ($steps as $i => [$icon, $title, $desc]): ?>
          <div class="card" style="padding:24px;text-align:center;">
            <div style="font-size:36px;margin-bottom:12px;"><?= $icon ?></div>
            <div style="font-size:12px;font-weight:700;color:var(--primary);margin-bottom:6px;">
              LANGKAH <?= $i + 1 ?>
            </div>
            <h3 style="font-size:16px;font-weight:700;margin-bottom:6px;"><?= $title ?></h3>
            <p style="font-size:13px;color:var(--text-muted);"><?= $desc ?></p>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════
       PAGE 2: FRAME PICKER
    ════════════════════════════════════════════════ -->
  <section id="page-frames" class="page-section" aria-label="Pilih bingkai foto">
    <div class="container">
      <div class="section-header">
        <h2 class="section-title">Pilih Bingkai</h2>
        <p class="section-sub">Klik bingkai favoritmu untuk melanjutkan</p>
      </div>

      <!-- Controls: Search & Sort -->
      <div class="frames-controls" style="display:flex; gap:12px; margin-bottom:24px; flex-wrap:wrap;">
        <div style="flex:1; min-width:200px; position:relative;">
          <input type="text" id="frame-search" placeholder="Cari bingkai..." class="form-control" style="width:100%; padding:10px 16px; border-radius:8px; border:1px solid var(--border); background:var(--surface); color:var(--text-main);">
        </div>
        <div style="min-width:140px;">
          <select id="frame-sort" class="form-control" style="width:100%; padding:10px 16px; border-radius:8px; border:1px solid var(--border); background:var(--surface); color:var(--text-main);">
            <option value="alpha">Abjad (A-Z)</option>
            <option value="newest">Terbaru</option>
          </select>
        </div>
      </div>

      <!-- Frame cards akan diload oleh JS dari /api/frames.php -->
      <div class="frames-grid" role="list" aria-label="Daftar bingkai tersedia">
        <div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--text-muted);">
          <div style="margin-bottom:12px;"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
          <p>Memuat bingkai…</p>
        </div>
      </div>

      <div style="text-align:center;margin-top:40px;">
        <button id="frame-next-btn" class="btn btn-primary btn-lg" disabled
                aria-label="Lanjut ke halaman kamera">
          Lanjut ke Kamera →
        </button>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════
       PAGE 3: CAMERA
    ════════════════════════════════════════════════ -->
  <section id="page-camera" class="page-section" aria-label="Halaman kamera">
    <div class="container" style="max-width:1400px;">
      
      <div class="camera-layout">
        <!-- Center (Viewport) -->
        <div class="camera-center">
          <div class="video-container">
            <video id="video-stream" autoplay playsinline muted></video>
            <div id="countdown-overlay" aria-live="assertive" aria-atomic="true"></div>
            <div id="camera-flash" class="camera-flash" aria-hidden="true"></div>
            
            <!-- Floating Shutter -->
            <div class="camera-actions-overlay">
              <input type="file" id="camera-upload-input" accept="image/*" style="display:none;">
              <button id="capture-btn" class="floating-shutter" aria-label="Ambil foto">
                 <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
              </button>
              <button id="switch-camera-btn" class="floating-shutter" style="display:none; width:48px; height:48px;" aria-label="Ganti Kamera" title="Ganti Kamera">
                 <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.3"/></svg>
              </button>
            </div>
            
            <!-- Dropzone Overlay -->
            <div id="camera-dropzone" class="camera-dropzone">
              <div class="dropzone-content">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                <p>Lepas foto di sini untuk mengisi slot</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Photos (Right) -->
        <div class="camera-sidebar right-sidebar">
          
          <!-- Explicit Upload Box -->
          <div id="sidebar-upload-box" style="border: 2px dashed var(--primary); border-radius: var(--radius-sm); padding: 16px; text-align: center; cursor: pointer; margin-bottom: 16px; background: rgba(75, 63, 160, 0.05); transition: background 0.2s;">
            <div style="font-size: 24px; margin-bottom: 4px;">📁</div>
            <div style="font-size: 13px; font-weight: 700; color: var(--primary);">Upload / Drag & Drop</div>
            <div style="font-size: 11px; color: var(--text-muted);">Tarik fotomu ke kotak ini</div>
          </div>
          
          <div class="sidebar-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 class="sidebar-title" style="margin:0;">Antrean Foto</h3>
            <span id="photo-counter" style="color:var(--primary);font-weight:700;font-size:14px;">0/3</span>
          </div>
          
          <div id="captured-strip" class="photo-grid-jepreto">
            <!-- JS populated -->
          </div>
          
          <div style="text-align:center;margin-top:20px;">
            <button id="retake-btn" class="text-link">Hapus Semua</button>
          </div>
          
          <hr style="border:0; border-top:1px solid var(--bg-deep); margin: 20px 0;">
          
          <!-- Live Filters -->
          <div style="margin-bottom: 16px;">
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:8px;">Filter Kamera</label>
            <div class="live-filters" id="live-filters-container" style="justify-content: space-between; overflow-x: auto; padding-bottom: 4px;">
              <div class="filter-item active" data-filter="none">
                <div class="filter-preview" style="background:linear-gradient(45deg, #ccc, #eee)"></div>
                <span>None</span>
              </div>
              <div class="filter-item" data-filter="sepia(0.6) contrast(1.1)">
                <div class="filter-preview" style="background:linear-gradient(45deg, #d4a373, #faedcd)"></div>
                <span>Vintage</span>
              </div>
              <div class="filter-item" data-filter="saturate(2) hue-rotate(45deg)">
                <div class="filter-preview" style="background:linear-gradient(45deg, #ff9ff3, #feca57)"></div>
                <span>Neon</span>
              </div>
              <div class="filter-item" data-filter="grayscale(1) contrast(1.2)">
                <div class="filter-preview" style="background:linear-gradient(45deg, #555, #999)"></div>
                <span>B&W</span>
              </div>
            </div>
          </div>
          
          <!-- Timer and Mirror -->
          <div style="display:flex; flex-direction: column; gap: 12px; background: var(--surface); padding: 16px; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); margin-bottom: 24px;">
            <div class="control-group" style="display:flex; justify-content: space-between; align-items:center;">
              <label style="font-size:12px;font-weight:700;">Timer:</label>
              <div class="toggle-group" id="delay-toggles" role="group" aria-label="Pengaturan timer">
                <button class="toggle-btn active" data-delay="0">Off</button>
                <button class="toggle-btn" data-delay="3">3s</button>
                <button class="toggle-btn" data-delay="5">5s</button>
              </div>
            </div>
            <div class="control-group" style="display:flex; justify-content: space-between; align-items:center;">
              <label style="font-size:12px;font-weight:700;">Mirror Kamera:</label>
              <label class="switch">
                <input type="checkbox" id="mirror-toggle" checked aria-label="Aktifkan mirror kamera">
                <span class="slider"></span>
              </label>
            </div>
          </div>
          
          <button id="camera-next-btn" class="btn btn-primary btn-lg" style="width:100%;" disabled>
            Lanjut Edit →
          </button>
        </div>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════════════════
       PAGE 4: EDITOR
    ════════════════════════════════════════════════ -->
  <section id="page-editor" class="page-section" aria-label="Halaman editor foto">
    <div class="container" style="max-width:1400px;">
      
      <div class="editor-layout">
        <!-- Preview (Left) -->
        <div class="editor-preview-pane">
          <div class="editor-canvas-wrap" style="position: relative;">
            <canvas id="edit-canvas" aria-label="Canvas editor foto"></canvas>
            <button id="delete-sticker-btn" style="position: absolute; top: 16px; right: 16px; display: none; background: #FF3B30; color: white; border: none; border-radius: 8px; padding: 8px 16px; font-weight: 600; font-size: 13px; cursor: pointer; z-index: 100; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: transform 0.2s;">
              🗑️ Hapus Sticker
            </button>
          </div>
        </div>

        <!-- Options (Right) -->
        <div class="editor-options-pane">
          <button id="back-to-camera" class="btn btn-surface" style="width:100%;margin-bottom:16px;justify-content:flex-start;">
            ← Back to Options
          </button>
          
          <!-- Template Info -->
          <div class="options-card">
            <h3 class="card-title">Selected Template</h3>
            <div class="template-info-box">
              <img src="" id="selected-template-thumb" alt="">
              <div style="display:flex;flex-direction:column;justify-content:center;">
                <strong id="selected-template-name" style="font-size:14px;color:var(--text-main);">Template Name</strong>
                <span id="selected-template-slots" style="font-size:12px;color:var(--text-muted);">3 photo positions</span>
              </div>
            </div>
            <button id="change-template-btn" class="btn btn-ghost" style="width:100%;margin-top:12px;padding:8px;font-size:13px;">Change Template</button>
          </div>

          <!-- Filter Playground -->
          <div class="options-card">
            <h3 class="card-title">Filter Playground</h3>
            <p class="card-desc">Select a filter below to apply it to all photos.</p>
            <div class="filter-playground-grid" id="post-filters-container">
              <!-- JS populated to match live filters -->
            </div>
          </div>

          <!-- Photo Transform -->
          <div class="options-card" id="photo-transform-card">
            <h3 class="card-title">Atur Posisi Foto</h3>
            <p class="card-desc" style="color:var(--primary);font-weight:600;font-size:11px;margin-bottom:12px;">Klik foto di kanvas untuk memilih slot.</p>
            
            <div style="margin-bottom:12px;">
              <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                <label style="font-size:12px;font-weight:600;">Zoom / Skala</label>
                <span id="zoom-val" style="font-size:11px;color:var(--text-muted);">100%</span>
              </div>
              <input type="range" id="photo-scale" min="50" max="300" value="100" style="width:100%;">
            </div>

            <div style="margin-bottom:12px;">
              <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                <label style="font-size:12px;font-weight:600;">Rotasi</label>
                <span id="rotate-val" style="font-size:11px;color:var(--text-muted);">0&deg;</span>
              </div>
              <input type="range" id="photo-rotate" min="-180" max="180" value="0" style="width:100%;">
            </div>
          </div>

          <!-- Photo Adjustments -->
          <div class="options-card" id="photo-adjust-card">
            <h3 class="card-title">Penyesuaian Warna</h3>
            
            <div style="margin-bottom:10px;">
              <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                <label style="font-size:12px;font-weight:600;">Kecerahan</label>
              </div>
              <input type="range" id="photo-brightness" min="0" max="200" value="100" style="width:100%;">
            </div>

            <div style="margin-bottom:10px;">
              <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                <label style="font-size:12px;font-weight:600;">Kontras</label>
              </div>
              <input type="range" id="photo-contrast" min="0" max="200" value="100" style="width:100%;">
            </div>

            <div style="margin-bottom:10px;">
              <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                <label style="font-size:12px;font-weight:600;">Saturasi</label>
              </div>
              <input type="range" id="photo-saturate" min="0" max="300" value="100" style="width:100%;">
            </div>

            <div style="margin-bottom:10px;">
              <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                <label style="font-size:12px;font-weight:600;">Rona (Hue)</label>
              </div>
              <input type="range" id="photo-hue" min="-180" max="180" value="0" style="width:100%;">
            </div>
            
            <button id="reset-photo-btn" class="btn btn-ghost" style="width:100%;margin-top:8px;padding:8px;font-size:12px;">Reset Foto Terpilih</button>
          </div>


          <!-- Sticker Corner -->
          <div class="options-card">
            <h3 class="card-title">Sticker Corner</h3>
            <p class="card-desc">Tap sticker favoritmu untuk langsung tempel ke foto.</p>
            
            <div class="editor-tabs" style="margin-bottom:12px;">
              <button class="editor-tab active" data-tab="emoji" id="tab-btn-emoji">Emoji</button>
              <button class="editor-tab" data-tab="text" id="tab-btn-text">Text</button>
            </div>
            
            <div id="tab-emoji" class="editor-panel-body active" style="padding:0;">
              <div class="emoji-grid" role="list"></div>
            </div>
            
            <div id="tab-text" class="editor-panel-body" style="padding:0;">
              <div class="text-tool">
                <input type="text" id="text-input" placeholder="Ketik teksmu di sini…" maxlength="60" style="width:100%;padding:12px;border:1px solid var(--bg-deep);border-radius:var(--radius-sm);margin-bottom:12px;">
                <div style="display:flex;gap:8px;align-items:center;margin-bottom:12px;">
                  <label style="font-size:12px;font-weight:600;">Ukuran:</label>
                  <input type="range" id="text-size" min="24" max="120" value="48" step="4" style="flex:1;">
                </div>
                <div style="margin-bottom:16px;">
                  <p style="font-size:12px;font-weight:600;margin-bottom:8px;">Warna Teks:</p>
                  <div class="color-row" role="list"></div>
                </div>
                <button id="add-text-btn" class="btn btn-primary" style="width:100%;">
                  Tambah Teks
                </button>
              </div>
            </div>
          </div>
          
          <button id="editor-next-btn" class="btn btn-primary btn-lg" style="width:100%;margin-top:16px;">
            Selesai & Ekspor →
          </button>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════
       PAGE 5: EXPORT
    ════════════════════════════════════════════════ -->
  <section id="page-export" class="page-section" aria-label="Halaman ekspor foto">
    <div class="container">
      <div class="section-header">
        <h2 class="section-title">Foto Siap!</h2>
        <p class="section-sub">Download, bagikan link, atau kirim via email</p>
      </div>

      <div class="export-layout">
        <!-- Preview -->
        <div>
          <div class="export-preview">
            <img id="export-preview-img" src="" alt="Hasil foto Photopedia">
          </div>
          <div style="display:flex;gap:12px;margin-top:16px;justify-content:center;flex-wrap:wrap;">
            <button id="back-to-editor" class="btn btn-ghost" aria-label="Kembali ke editor">
              ← Edit Lagi
            </button>
            <button id="restart-btn" class="btn btn-surface" aria-label="Mulai dari awal">
              Foto Baru
            </button>
          </div>
        </div>

        <!-- Action Cards -->
        <div class="export-actions">
          <!-- Share Link + QR -->
          <div class="action-card">
            <h3>Share Link</h3>
            <div class="qr-container" style="flex-direction:column; align-items:center; text-align:center;">
              <div id="qr-canvas" aria-label="QR Code link foto"></div>
              <div style="width:100%;">
                <p style="font-size:12px;color:var(--text-muted);margin-bottom:8px;">
                  Scan QR atau salin link di bawah
                </p>
                <input id="share-link-input" type="text" readonly
                       style="width:100%;padding:8px 10px;border:1.5px solid var(--bg-deep);border-radius:8px;font-size:12px;background:var(--bg);text-align:center;"
                       placeholder="Link akan muncul setelah upload…"
                       aria-label="Link berbagi foto">
              </div>
            </div>
          </div>

          <!-- Email -->
          <div class="action-card">
            <h3>Kirim via Email</h3>
            <form id="email-form" class="email-form" novalidate>
              <input type="email" id="email-input"
                     placeholder="email1@kamu.com, email2@kamu.com"
                     required autocomplete="email"
                     multiple
                     aria-label="Alamat email tujuan"
                     aria-required="true">
              <button id="send-email-btn" type="submit" class="btn btn-primary"
                      aria-label="Kirim foto via email">
                <div class="spinner" aria-hidden="true"></div>
                <span class="btn-text">Kirim Email</span>
              </button>
            </form>
          </div>

          <!-- Download -->
          <div class="action-card">
            <h3>Download Foto</h3>
            <a id="download-btn" href="#" download="photopedia.jpg"
               class="btn btn-primary" style="width:100%;"
               aria-label="Unduh foto ke perangkat">
              Download JPG
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════
       PAGE 6: GALLERY
    ════════════════════════════════════════════════ -->
  <section id="page-gallery" class="page-section" aria-label="Halaman galeri foto">
    <div class="container">
      <div class="section-header">
        <h2 class="section-title">Galeri Photopedia</h2>
        <p class="section-sub">Koleksi foto keren dari pengguna Photopedia</p>
      </div>
      <div class="gallery-grid" id="gallery-container">
        <div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--text-muted);">
          <div class="spinner" style="margin:0 auto 12px;"></div>
          <p>Memuat galeri…</p>
        </div>
      </div>
      <div style="text-align:center;margin-top:40px;">
        <button id="back-to-landing-btn" class="btn btn-surface btn-lg" aria-label="Kembali ke beranda">
          ← Kembali ke Beranda
        </button>
      </div>
    </div>
  </section>

</main>

<!-- Lightbox Modal -->
<div id="lightbox-modal" class="lightbox-modal" style="display:none;">
  <button class="lightbox-close" aria-label="Tutup preview">&times;</button>
  <div class="lightbox-content">
    <img id="lightbox-img" src="" alt="Preview">
  </div>
</div>

<!-- ── Footer ──────────────────────────────────────────────── -->
<footer class="site-footer">
  <p>
    Dibuat oleh <strong>Photopedia</strong> &copy; <?= date('Y') ?>
    &nbsp;·&nbsp;
    Photobooth digital untuk Gen-Z Indonesia
  </p>
</footer>

<!-- ── Scripts ─────────────────────────────────────────────── -->
<!-- CamanJS (filters) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/camanjs/4.1.2/caman.full.min.js"></script>
<!-- QRCode.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<!-- App modules -->
<script src="/assets/js/camera.js?v=27"></script>
<script src="/assets/js/editor.js?v=27"></script>
<script src="/assets/js/app.js?v=27"></script>

</body>
</html>
