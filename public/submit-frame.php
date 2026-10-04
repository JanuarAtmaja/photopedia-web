<?php
// public/submit-frame.php — Halaman Submit Frame (User-Facing)
require_once dirname(__DIR__) . '/config/helpers.php';
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- SEO -->
  <title>Submit Frame — Photopedia</title>
  <meta name="description" content="Kirim desain frame photobooth kamu sendiri ke Photopedia! Frame yang lolos review akan langsung bisa dipakai semua pengguna.">
  <meta name="robots" content="noindex">

  <!-- Favicon -->
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --navy:       #0F1B3D;
      --purple:     #4B3FA0;
      --purple-lt:  #6B5FD0;
      --pink:       #FF2D78;
      --lime:       #D4F04A;
      --surface:    #1A2755;
      --surface-2:  #1F2F66;
      --border:     rgba(255,255,255,0.10);
      --text:       #E8E8FF;
      --text-muted: rgba(232,232,255,0.60);
      --radius:     16px;
      --shadow:     0 8px 32px rgba(0,0,0,0.40);
    }

    html { scroll-behavior: smooth; }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--navy);
      color: var(--text);
      min-height: 100vh;
      line-height: 1.6;
    }

    /* ── Header ── */
    .site-header {
      background: rgba(15, 27, 61, 0.85);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--border);
      position: sticky;
      top: 0;
      z-index: 100;
      padding: 0 24px;
    }
    .header-inner {
      max-width: 900px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 64px;
    }
    .logo-link {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      font-weight: 800;
      font-size: 20px;
      color: #fff;
    }
    .logo-link img { height: 30px; object-fit: contain; }
    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      border-radius: 50px;
      background: var(--surface);
      color: var(--text);
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      border: 1px solid var(--border);
      transition: background 0.2s;
    }
    .back-btn:hover { background: var(--surface-2); }

    /* ── Page Layout ── */
    .page-wrap {
      max-width: 900px;
      margin: 0 auto;
      padding: 48px 24px 80px;
    }

    /* ── Page Title ── */
    .page-hero {
      text-align: center;
      margin-bottom: 40px;
    }
    .page-badge {
      display: inline-block;
      padding: 6px 16px;
      border-radius: 50px;
      background: rgba(212,240,74,0.15);
      color: var(--lime);
      font-size: 13px;
      font-weight: 600;
      letter-spacing: 0.5px;
      margin-bottom: 16px;
      border: 1px solid rgba(212,240,74,0.30);
    }
    .page-title {
      font-size: clamp(28px, 5vw, 42px);
      font-weight: 800;
      line-height: 1.2;
      margin-bottom: 12px;
      background: linear-gradient(135deg, #fff 0%, #C4B5FD 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .page-sub {
      font-size: 16px;
      color: var(--text-muted);
      max-width: 540px;
      margin: 0 auto;
    }

    /* ── Dimension Notice ── */
    .dim-notice {
      background: linear-gradient(135deg, rgba(255,45,120,0.15), rgba(255,107,43,0.10));
      border: 1px solid rgba(255,45,120,0.40);
      border-radius: var(--radius);
      padding: 20px 24px;
      margin-bottom: 32px;
      display: flex;
      gap: 16px;
      align-items: flex-start;
    }
    .dim-notice .icon {
      font-size: 28px;
      flex-shrink: 0;
      line-height: 1;
    }
    .dim-notice h3 {
      font-size: 15px;
      font-weight: 700;
      color: #FF8FAB;
      margin-bottom: 8px;
    }
    .dim-notice p {
      font-size: 14px;
      color: var(--text-muted);
      line-height: 1.7;
    }
    .dim-notice strong { color: var(--text); }
    .dim-table {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
      margin-top: 12px;
    }
    .dim-row {
      background: rgba(255,255,255,0.06);
      border-radius: 8px;
      padding: 10px 14px;
    }
    .dim-row .type { font-size: 12px; font-weight: 600; color: var(--lime); text-transform: uppercase; letter-spacing: 0.5px; }
    .dim-row .val  { font-size: 13px; color: var(--text); margin-top: 4px; }

    /* ── Main Grid ── */
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 32px;
    }
    @media (max-width: 640px) {
      .form-grid { grid-template-columns: 1fr; }
    }

    /* ── Card ── */
    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 28px;
    }
    .card-title {
      font-size: 14px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: var(--text-muted);
      margin-bottom: 20px;
    }

    /* ── Form Fields ── */
    .field { margin-bottom: 20px; }
    .field:last-child { margin-bottom: 0; }
    .field label {
      display: block;
      font-size: 14px;
      font-weight: 600;
      color: var(--text);
      margin-bottom: 8px;
    }
    .field label .req { color: var(--pink); margin-left: 2px; }
    .field label .opt { color: var(--text-muted); font-weight: 400; font-size: 12px; margin-left: 4px; }

    .input, .select, textarea {
      width: 100%;
      background: rgba(255,255,255,0.06);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 12px 16px;
      font-size: 15px;
      font-family: inherit;
      color: var(--text);
      outline: none;
      transition: border-color 0.2s, background 0.2s;
      -webkit-appearance: none;
    }
    .input::placeholder, textarea::placeholder { color: var(--text-muted); }
    .input:focus, .select:focus, textarea:focus {
      border-color: var(--purple-lt);
      background: rgba(255,255,255,0.09);
    }
    .input.error, .select.error { border-color: var(--pink); }

    /* Strip type radio */
    .strip-group {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    .strip-option {
      position: relative;
    }
    .strip-option input[type="radio"] {
      position: absolute;
      opacity: 0;
      width: 0;
      height: 0;
    }
    .strip-label {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      padding: 16px 12px;
      border-radius: 12px;
      border: 2px solid var(--border);
      background: rgba(255,255,255,0.04);
      cursor: pointer;
      transition: border-color 0.2s, background 0.2s;
      text-align: center;
    }
    .strip-option input:checked + .strip-label {
      border-color: var(--purple-lt);
      background: rgba(107,95,208,0.15);
    }
    .strip-label:hover {
      border-color: rgba(107,95,208,0.50);
      background: rgba(107,95,208,0.08);
    }
    .strip-icon {
      display: flex;
      gap: 3px;
    }
    .strip-icon span {
      background: rgba(255,255,255,0.25);
      border-radius: 3px;
    }
    .strip-icon.single span { width: 18px; height: 52px; }
    .strip-icon.double span { width: 18px; height: 52px; }
    .strip-label .s-name { font-size: 14px; font-weight: 600; color: var(--text); }
    .strip-label .s-dim  { font-size: 11px; color: var(--text-muted); }

    /* ── Upload Area ── */
    .upload-area {
      border: 2px dashed var(--border);
      border-radius: 14px;
      padding: 32px 20px;
      text-align: center;
      cursor: pointer;
      transition: border-color 0.2s, background 0.2s;
      position: relative;
      min-height: 180px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .upload-area:hover, .upload-area.drag-over {
      border-color: var(--purple-lt);
      background: rgba(107,95,208,0.08);
    }
    .upload-area input[type="file"] {
      position: absolute;
      inset: 0;
      opacity: 0;
      cursor: pointer;
      width: 100%;
      height: 100%;
    }
    .upload-icon { font-size: 40px; line-height: 1; }
    .upload-title { font-size: 15px; font-weight: 600; color: var(--text); }
    .upload-sub   { font-size: 13px; color: var(--text-muted); }
    .upload-limit {
      font-size: 12px;
      color: rgba(212,240,74,0.80);
      margin-top: 4px;
    }

    /* ── Preview Area ── */
    #preview-section {
      display: none;
      margin-top: 20px;
    }
    .preview-wrap {
      position: relative;
      border-radius: 12px;
      overflow: hidden;
      background: #000;
      aspect-ratio: auto;
    }
    .preview-wrap img {
      display: block;
      width: 100%;
      height: auto;
      max-height: 500px;
      object-fit: contain;
    }
    /* Guide overlay */
    .preview-guide {
      position: absolute;
      inset: 0;
      border: 3px dashed rgba(212,240,74,0.70);
      border-radius: 4px;
      pointer-events: none;
      box-shadow: inset 0 0 0 1000px rgba(0,0,0,0.25);
    }
    .preview-label {
      font-size: 12px;
      color: var(--text-muted);
      margin-top: 8px;
      text-align: center;
    }
    .compress-status {
      margin-top: 10px;
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 500;
      display: none;
    }
    .compress-status.compressing { background: rgba(107,95,208,0.20); color: #C4B5FD; display: block; }
    .compress-status.success     { background: rgba(16,185,129,0.15); color: #6EE7B7; display: block; }
    .compress-status.warn        { background: rgba(245,158,11,0.15); color: #FDE68A; display: block; }
    .compress-status.error       { background: rgba(255,45,120,0.15); color: #FCA5A5; display: block; }

    /* ── Aspect Ratio Warning ── */
    #ratio-warning {
      display: none;
      background: rgba(245,158,11,0.15);
      border: 1px solid rgba(245,158,11,0.40);
      border-radius: 10px;
      padding: 12px 16px;
      font-size: 13px;
      color: #FDE68A;
      margin-top: 12px;
    }

    /* ── Submit Button ── */
    .submit-wrap {
      margin-top: 32px;
      text-align: center;
    }
    .btn-submit {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 16px 40px;
      border-radius: 50px;
      background: linear-gradient(135deg, var(--purple), var(--purple-lt));
      color: #fff;
      font-size: 16px;
      font-weight: 700;
      border: none;
      cursor: pointer;
      transition: opacity 0.2s, transform 0.2s;
      box-shadow: 0 4px 24px rgba(75,63,160,0.50);
    }
    .btn-submit:hover { opacity: 0.90; transform: translateY(-1px); }
    .btn-submit:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }
    .btn-submit .spinner {
      width: 18px; height: 18px;
      border: 2px solid rgba(255,255,255,0.35);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
      display: none;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Toast / Result ── */
    #result-box {
      display: none;
      border-radius: var(--radius);
      padding: 24px;
      text-align: center;
      margin-top: 32px;
    }
    #result-box.success {
      background: rgba(16,185,129,0.12);
      border: 1px solid rgba(16,185,129,0.40);
    }
    #result-box.error {
      background: rgba(255,45,120,0.12);
      border: 1px solid rgba(255,45,120,0.40);
    }
    #result-box .result-icon { font-size: 48px; margin-bottom: 12px; }
    #result-box h3 { font-size: 20px; font-weight: 700; margin-bottom: 8px; }
    #result-box p  { font-size: 14px; color: var(--text-muted); }
    #result-box.success h3 { color: #6EE7B7; }
    #result-box.error   h3 { color: #FCA5A5; }

    .btn-reset {
      margin-top: 16px;
      padding: 10px 24px;
      border-radius: 50px;
      background: var(--surface-2);
      color: var(--text);
      border: 1px solid var(--border);
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s;
    }
    .btn-reset:hover { background: rgba(255,255,255,0.12); }

    /* ── Guidelines card ── */
    .guidelines {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 24px;
      margin-top: 32px;
    }
    .guidelines h3 {
      font-size: 15px;
      font-weight: 700;
      margin-bottom: 14px;
      color: var(--lime);
    }
    .guidelines ul {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .guidelines li {
      font-size: 14px;
      color: var(--text-muted);
      padding-left: 20px;
      position: relative;
    }
    .guidelines li::before {
      content: '✓';
      position: absolute;
      left: 0;
      color: var(--lime);
      font-weight: 700;
    }
  </style>
</head>
<body>

<!-- ── Header ── -->
<header class="site-header">
  <div class="header-inner">
    <a href="/" class="logo-link">
      <img src="/assets/images/Logo.png" alt="Photopedia">
    </a>
    <a href="/" class="back-btn">← Kembali</a>
  </div>
</header>

<!-- ── Page Content ── -->
<div class="page-wrap">

  <!-- Hero -->
  <div class="page-hero">
    <div class="page-badge">✦ Community Frame</div>
    <h1 class="page-title">Submit Desain Frame Kamu</h1>
    <p class="page-sub">
      Punya desain frame photobooth keren? Kirim ke sini dan kalau lolos review,
      frame kamu akan langsung bisa dipakai oleh semua pengguna Photopedia!
    </p>
  </div>

  <!-- Dimension Notice -->
  <div class="dim-notice" id="dim-notice">
    <div class="icon">⚠️</div>
    <div>
      <h3>Ukuran Frame Wajib</h3>
      <p>Frame di luar ukuran berikut akan <strong>otomatis ditolak</strong>. Pastikan desain kamu sesuai sebelum upload. Toleransi ±2%.</p>
      <div class="dim-table">
        <div class="dim-row">
          <div class="type">Single Strip</div>
          <div class="val"><strong>5cm × 15cm</strong><br><span style="font-size:12px;color:var(--text-muted)">≈ 591 × 1772 px (300 DPI)</span></div>
        </div>
        <div class="dim-row">
          <div class="type">Double Strip</div>
          <div class="val"><strong>10cm × 15cm</strong><br><span style="font-size:12px;color:var(--text-muted)">≈ 1181 × 1772 px (300 DPI)</span></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Form Grid -->
  <form id="submit-form" novalidate>
    <div class="form-grid">

      <!-- Kolom Kiri: Info -->
      <div>
        <div class="card">
          <div class="card-title">Info Pengiriman</div>

          <div class="field">
            <label for="submitter_email">Email <span class="req">*</span></label>
            <input type="email" id="submitter_email" name="submitter_email" class="input"
                   placeholder="kamu@email.com" required
                   autocomplete="email">
          </div>

          <div class="field">
            <label for="submitter_name">Nama / Username <span class="opt">(opsional)</span></label>
            <input type="text" id="submitter_name" name="submitter_name" class="input"
                   placeholder="Nama kamu (opsional)"
                   maxlength="100">
          </div>

          <div class="field">
            <label for="frame_title">Judul Frame <span class="req">*</span></label>
            <input type="text" id="frame_title" name="frame_title" class="input"
                   placeholder="cth: Film Strip Vintage" required
                   maxlength="80">
          </div>

          <div class="field">
            <label>Tipe Strip <span class="req">*</span></label>
            <div class="strip-group">
              <label class="strip-option">
                <input type="radio" name="strip_type" value="single" id="strip-single" required>
                <span class="strip-label">
                  <span class="strip-icon single"><span></span></span>
                  <span class="s-name">Single Strip</span>
                  <span class="s-dim">5 × 15 cm</span>
                </span>
              </label>
              <label class="strip-option">
                <input type="radio" name="strip_type" value="double" id="strip-double">
                <span class="strip-label">
                  <span class="strip-icon double"><span></span><span></span></span>
                  <span class="s-name">Double Strip</span>
                  <span class="s-dim">10 × 15 cm</span>
                </span>
              </label>
            </div>
          </div>
        </div>

        <!-- Guidelines -->
        <div class="guidelines">
          <h3>📋 Panduan Desain</h3>
          <ul>
            <li>Format file: PNG dengan transparansi pada setiap area foto</li>
            <li>Ukuran file maksimal 1.5 MB</li>
            <li>Resolusi ideal 300 DPI sesuai tipe strip</li>
            <li>Area foto (slot) harus transparan (PNG)</li>
            <li>Desain bebas tapi tidak mengandung konten dewasa / SARA</li>
            <li>Kamu akan dapat email notifikasi hasil reviewnya</li>
          </ul>
        </div>
      </div>

      <!-- Kolom Kanan: Upload & Preview -->
      <div>
        <div class="card">
          <div class="card-title">Upload Frame</div>

          <div class="upload-area" id="upload-area">
            <input type="file" id="frame_image" name="frame_image" accept="image/png" required>
            <div class="upload-icon">🖼️</div>
            <div class="upload-title">Klik atau drag & drop gambar</div>
            <div class="upload-sub">PNG transparan</div>
            <div class="upload-limit">Maks. 1.5 MB • Transparansi diperlukan agar slot foto terdeteksi</div>
          </div>

          <!-- Aspect ratio warning -->
          <div id="ratio-warning">
            ⚠️ <strong>Peringatan:</strong> Aspect ratio gambar tampaknya tidak sesuai dengan tipe strip yang dipilih. Pastikan ukuran sudah benar (toleransi ±2%).
          </div>

          <!-- Compress status -->
          <div class="compress-status" id="compress-status"></div>

          <!-- Preview -->
          <div id="preview-section">
            <div class="preview-wrap">
              <img id="preview-img" src="" alt="Preview frame">
              <div class="preview-guide" id="preview-guide"></div>
            </div>
            <p class="preview-label" id="preview-label">Crop guide menunjukkan proporsi strip yang dipilih</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Submit -->
    <div class="submit-wrap">
      <button type="submit" class="btn-submit" id="btn-submit">
        <span class="spinner" id="spinner"></span>
        <span id="btn-text">🚀 Kirim Frame</span>
      </button>
    </div>
  </form>

  <!-- Result Box -->
  <div id="result-box"></div>

</div><!-- /page-wrap -->

<script>
(function () {
  'use strict';

  /* ── Konstanta ── */
  const MAX_SIZE           = 1.5 * 1024 * 1024; // 1.5 MB
  const TARGET_SINGLE = { w: 591,  h: 1772 };
  const TARGET_DOUBLE = { w: 1181, h: 1772 };

  /* ── Refs ── */
  const form          = document.getElementById('submit-form');
  const emailInput    = document.getElementById('submitter_email');
  const titleInput    = document.getElementById('frame_title');
  const fileInput     = document.getElementById('frame_image');
  const uploadArea    = document.getElementById('upload-area');
  const previewSec    = document.getElementById('preview-section');
  const previewImg    = document.getElementById('preview-img');
  const previewGuide  = document.getElementById('preview-guide');
  const previewLabel  = document.getElementById('preview-label');
  const compressStatus= document.getElementById('compress-status');
  const ratioWarning  = document.getElementById('ratio-warning');
  const btnSubmit     = document.getElementById('btn-submit');
  const btnText       = document.getElementById('btn-text');
  const spinner       = document.getElementById('spinner');
  const resultBox     = document.getElementById('result-box');
  const dimNotice     = document.getElementById('dim-notice');

  let processedBlob   = null; // file blob setelah compress (atau asli)
  let processedName   = '';

  /* ── Drag & drop ── */
  uploadArea.addEventListener('dragover', e => { e.preventDefault(); uploadArea.classList.add('drag-over'); });
  uploadArea.addEventListener('dragleave',  () => uploadArea.classList.remove('drag-over'));
  uploadArea.addEventListener('drop', e => {
    e.preventDefault();
    uploadArea.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) handleFile(file);
  });

  fileInput.addEventListener('change', () => {
    if (fileInput.files[0]) handleFile(fileInput.files[0]);
  });

  /* ── Strip type change → update dimension notice ── */
  document.querySelectorAll('input[name="strip_type"]').forEach(r => {
    r.addEventListener('change', () => {
      if (processedBlob) checkRatio();
      updateDimHighlight();
    });
  });

  function getStripType() {
    const checked = document.querySelector('input[name="strip_type"]:checked');
    return checked ? checked.value : null;
  }

  function getTarget() {
    return getStripType() === 'double' ? TARGET_DOUBLE : TARGET_SINGLE;
  }

  /* ── Highlight dimensi notice sesuai pilihan strip ── */
  function updateDimHighlight() {
    const type = getStripType();
    dimNotice.querySelectorAll('.dim-row').forEach((row, i) => {
      const isActive = (i === 0 && type === 'single') || (i === 1 && type === 'double');
      row.style.border = isActive ? '1px solid rgba(212,240,74,0.60)' : 'none';
      row.style.background = isActive ? 'rgba(212,240,74,0.10)' : 'rgba(255,255,255,0.06)';
    });
  }

  /* ── Handle file ── */
  function handleFile(file) {
    if (file.type !== 'image/png') {
      processedBlob = null;
      showCompressStatus('error', '❌ Upload PNG dengan area slot foto transparan. JPG tidak menyimpan transparansi.');
      return;
    }
    if (file.size > MAX_SIZE) {
      processedBlob = null;
      showCompressStatus('error', '❌ File melebihi 1.5 MB. Optimalkan PNG tanpa menghilangkan transparansi.');
      return;
    }

    // Reset
    processedBlob = null;
    ratioWarning.style.display = 'none';
    resultBox.style.display = 'none';

    // Load image untuk preview & ukuran
    const imgEl = new Image();
    const objUrl = URL.createObjectURL(file);

    imgEl.onload = () => {
      const origW = imgEl.naturalWidth;
      const origH = imgEl.naturalHeight;

      // Tampilkan preview
      previewImg.src = objUrl;
      previewSec.style.display = 'block';
      updatePreviewGuide();

      // Cek ratio
      checkRatio(origW, origH);

      processedBlob = file;
      processedName = file.name;
      showCompressStatus('success', `✅ PNG siap (${formatSize(file.size)}). Slot transparan akan dideteksi saat admin menyetujui.`);
    };

    imgEl.src = objUrl;
  }

  /* ── Preview guide overlay ── */
  function updatePreviewGuide() {
    // Overlay selalu full sesuai gambar, hanya sebagai border guide
    previewGuide.style.display = 'block';
    const type = getStripType() || 'single';
    previewLabel.textContent = `Preview proporsi ${type === 'single' ? 'Single Strip (5×15cm)' : 'Double Strip (10×15cm)'}`;
  }

  /* ── Cek aspect ratio ── */
  function checkRatio(w, h) {
    if (!w || !h) {
      if (!previewImg.naturalWidth) return;
      w = previewImg.naturalWidth;
      h = previewImg.naturalHeight;
    }
    const target   = getTarget();
    const expected = target.w / target.h;
    const actual   = w / h;
    const diff     = Math.abs(actual - expected) / expected;
    ratioWarning.style.display = diff > 0.02 ? 'block' : 'none';
  }

  /* ── Helpers ── */
  function showCompressStatus(type, msg) {
    compressStatus.className = 'compress-status ' + type;
    compressStatus.textContent = msg;
  }

  function formatSize(bytes) {
    if (bytes < 1024)       return bytes + ' B';
    if (bytes < 1024*1024)  return (bytes/1024).toFixed(1) + ' KB';
    return (bytes/1024/1024).toFixed(2) + ' MB';
  }

  /* ── Form Submit ── */
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const email     = emailInput.value.trim();
    const title     = titleInput.value.trim();
    const stripType = getStripType();

    // Client validasi
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      emailInput.classList.add('error');
      emailInput.focus();
      return;
    }
    emailInput.classList.remove('error');

    if (!title) {
      titleInput.classList.add('error');
      titleInput.focus();
      return;
    }
    titleInput.classList.remove('error');

    if (!stripType) {
      alert('Pilih tipe strip terlebih dahulu.');
      return;
    }

    if (!processedBlob) {
      if (!fileInput.files[0]) {
        alert('Upload gambar frame terlebih dahulu.');
        return;
      }
      // Fallback: pakai file asli jika belum diproses
      processedBlob = fileInput.files[0];
      processedName = fileInput.files[0].name;
      if (processedBlob.type !== 'image/png') {
        showCompressStatus('error', '❌ Upload PNG dengan area slot foto transparan.');
        return;
      }
    }

    if (processedBlob.size > MAX_SIZE) {
      showCompressStatus('error', '❌ File masih di atas 1.5 MB. Coba kompres manual dulu.');
      return;
    }

    // Set UI loading
    btnSubmit.disabled = true;
    spinner.style.display = 'block';
    btnText.textContent = 'Mengirim…';
    resultBox.style.display = 'none';

    try {
      const fd = new FormData();
      fd.append('submitter_email', email);
      fd.append('submitter_name',  document.getElementById('submitter_name').value.trim());
      fd.append('frame_title',     title);
      fd.append('strip_type',      stripType);
      fd.append('frame_image',     processedBlob, processedName);

      const res  = await fetch('/api/submit-frame', { method: 'POST', body: fd });
      const data = await res.json();

      if (data.success) {
        showResult('success', '🎉 Frame Terkirim!', data.message);
        form.reset();
        previewSec.style.display = 'none';
        compressStatus.className = 'compress-status';
        processedBlob = null;
      } else {
        const detail = typeof data.detail === 'string'
          ? data.detail
          : data.detail?.message || data.detail?.details;
        const message = [data.error || 'Terjadi kesalahan. Coba lagi.', detail]
          .filter(Boolean)
          .join(' ');
        showResult('error', '❌ Gagal Mengirim', message);
      }
    } catch (err) {
      showResult('error', '❌ Koneksi Gagal', 'Tidak bisa terhubung ke server. Periksa koneksi internet kamu.');
    } finally {
      btnSubmit.disabled = false;
      spinner.style.display = 'none';
      btnText.textContent = '🚀 Kirim Frame';
    }
  });

  function showResult(type, title, msg) {
    resultBox.className = type;
    resultBox.style.display = 'block';
    const escapeHtml = value => String(value).replace(/[&<>"']/g, char => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[char]);
    resultBox.innerHTML = `
      <div class="result-icon">${type === 'success' ? '✅' : '❌'}</div>
      <h3>${escapeHtml(title)}</h3>
      <p>${escapeHtml(msg)}</p>
      ${type === 'success' ? '<button class="btn-reset" onclick="location.reload()">Kirim Frame Lagi</button>' : ''}
    `;
    resultBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
})();
</script>

</body>
</html>
