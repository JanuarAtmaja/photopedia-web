<?php
// api/submit-frame.php — Backend handler submit frame oleh user
// POST /api/submit-frame  (multipart/form-data)
// Fields: submitter_email, submitter_name (opsional), frame_title, strip_type, frame_image

require_once dirname(__DIR__) . '/config/helpers.php';

set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(['error' => 'Method not allowed'], 405);
}

// Rate limit: max 3 submission per 10 menit per IP
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
rate_limit('submit_frame_' . $ip, 3, 600);

// ── Validasi Input ────────────────────────────────────────────
$email     = trim($_POST['submitter_email'] ?? '');
$name      = sanitize(trim($_POST['submitter_name'] ?? ''));
$title     = sanitize(trim($_POST['frame_title'] ?? ''));
$stripType = trim($_POST['strip_type'] ?? '');

if (!$email || !is_valid_email($email)) {
    respond_json(['error' => 'Email tidak valid atau wajib diisi.'], 422);
}
if (!$title) {
    respond_json(['error' => 'Judul frame wajib diisi.'], 422);
}
if (!in_array($stripType, ['single', 'double'], true)) {
    respond_json(['error' => 'Tipe strip tidak valid. Pilih single atau double.'], 422);
}

// ── Validasi File ─────────────────────────────────────────────
if (empty($_FILES['frame_image']) || $_FILES['frame_image']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['frame_image']['error'] ?? -1;
    $errMessages = [
        1 => 'File melebihi batas ukuran server.',
        2 => 'File melebihi batas ukuran form.',
        3 => 'File hanya terupload sebagian.',
        4 => 'Tidak ada file yang diupload.',
        6 => 'Folder temp tidak tersedia.',
        7 => 'Gagal menulis file.',
    ];
    respond_json(['error' => $errMessages[$errCode] ?? 'Gagal upload file.'], 400);
}

$file     = $_FILES['frame_image'];
$tmpPath  = $file['tmp_name'];
$mimeType = mime_content_type($tmpPath);
$allowed  = ['image/png'];

if (!in_array($mimeType, $allowed, true)) {
    respond_json(['error' => 'Format file tidak didukung. Upload PNG dengan area slot foto transparan.'], 422);
}

$maxSize = (int)(1.5 * 1024 * 1024); // 1.5 MB
if ($file['size'] > $maxSize) {
    respond_json(['error' => 'Ukuran file melebihi 1.5 MB. Mohon kompres gambar terlebih dahulu.'], 422);
}

// ── Validasi Dimensi Server-side (300 DPI) ───────────────────
$imgInfo = @getimagesize($tmpPath);
if (!$imgInfo) {
    respond_json(['error' => 'Gagal membaca dimensi gambar.'], 422);
}

[$widthPx, $heightPx] = $imgInfo;

// Konversi px ke cm: 1 inch = 2.54 cm, 300 DPI
$widthCm  = round($widthPx  / 300 * 2.54, 2);
$heightCm = round($heightPx / 300 * 2.54, 2);

// Toleransi ±2%
$targetW = ($stripType === 'single') ? 5.0  : 10.0;
$targetH = 15.0;

$tolW = $targetW * 0.02;
$tolH = $targetH * 0.02;

$widthOk  = abs($widthCm  - $targetW) <= $tolW;
$heightOk = abs($heightCm - $targetH) <= $tolH;

if (!$widthOk || !$heightOk) {
    $expected = ($stripType === 'single') ? '5cm × 15cm' : '10cm × 15cm';
    respond_json([
        'error' => "Ukuran gambar tidak sesuai. Frame $stripType strip harus $expected (≈300 DPI). "
                 . "Gambar kamu: {$widthCm}cm × {$heightCm}cm.",
        'width_cm'   => $widthCm,
        'height_cm'  => $heightCm,
        'width_px'   => $widthPx,
        'height_px'  => $heightPx,
    ], 422);
}

// ── Konfigurasi Supabase ──────────────────────────────────────
$supabaseUrl    = rtrim(env('SUPABASE_URL'), '/');
$serviceRoleKey = trim(env('SUPABASE_SERVICE_ROLE_KEY'));

if (!$supabaseUrl || !$serviceRoleKey) {
    respond_json(['error' => 'Server belum dikonfigurasi dengan benar.'], 500);
}

// ── Upload ke Supabase Storage bucket `submissions/` ─────────
$ext      = ($mimeType === 'image/png') ? 'png' : 'jpg';
$uuid     = bin2hex(random_bytes(12));
$filename = "frame-{$uuid}.{$ext}";

$storageUrl  = $supabaseUrl . '/storage/v1/object/submissions/' . $filename;
$fileContent = file_get_contents($tmpPath);

$ch = curl_init($storageUrl);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_POSTFIELDS     => $fileContent,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $serviceRoleKey,
        'apikey: ' . $serviceRoleKey,
        'Content-Type: ' . $mimeType,
        'Content-Length: ' . strlen($fileContent),
        'x-upsert: false',
    ],
]);
$upResponse = curl_exec($ch);
$upCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$upErr      = curl_error($ch);
curl_close($ch);

if ($upErr) {
    respond_json(['error' => 'Gagal menghubungi server penyimpanan: ' . $upErr], 500);
}
if ($upCode < 200 || $upCode >= 300) {
    respond_json(['error' => 'Gagal upload gambar ke storage.', 'detail' => $upResponse], 502);
}

// Buat public URL dari bucket submissions
$imageUrl = $supabaseUrl . '/storage/v1/object/public/submissions/' . $filename;

// ── Insert ke tabel frame_submissions ────────────────────────
$dbUrl = $supabaseUrl . '/rest/v1/frame_submissions';
$dbPayload = json_encode([
    'submitter_name'  => $name ?: null,
    'submitter_email' => $email,
    'frame_title'     => $title,
    'strip_type'      => $stripType,
    'image_url'       => $imageUrl,
    'filename'        => $filename,
    'width_px'        => $widthPx,
    'height_px'       => $heightPx,
    'status'          => 'pending',
]);

$ch = curl_init($dbUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $dbPayload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $serviceRoleKey,
        'apikey: ' . $serviceRoleKey,
        'Content-Type: application/json',
        'Prefer: return=minimal',
    ],
]);
$dbResponse = curl_exec($ch);
$dbCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($dbCode < 200 || $dbCode >= 300) {
    error_log("[Photopedia] submit-frame database insert failed (HTTP $dbCode): $dbResponse");

    $cleanupUrl = $supabaseUrl . '/storage/v1/object/submissions/' . rawurlencode($filename);
    $cleanup = curl_init($cleanupUrl);
    curl_setopt_array($cleanup, [
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $serviceRoleKey,
            'apikey: ' . $serviceRoleKey,
        ],
    ]);
    $cleanupResponse = curl_exec($cleanup);
    $cleanupCode     = curl_getinfo($cleanup, CURLINFO_HTTP_CODE);
    $cleanupError    = curl_error($cleanup);
    curl_close($cleanup);

    if ($cleanupError || $cleanupCode < 200 || $cleanupCode >= 300) {
        error_log("[Photopedia] submit-frame orphan cleanup failed (HTTP $cleanupCode): " . ($cleanupError ?: $cleanupResponse));
    }

    $dbError = json_decode($dbResponse, true);
    respond_json([
        'error' => 'Gagal menyimpan data submission. Jalankan migrasi fix_frame_submissions_schema.sql di Supabase SQL Editor.',
        'detail' => is_array($dbError)
            ? ($dbError['message'] ?? $dbError['details'] ?? $dbResponse)
            : $dbResponse,
    ], 502);
}

respond_json([
    'success'  => true,
    'message'  => "Frame kamu berhasil dikirim dan sedang direview oleh admin. Cek email {$email} untuk notifikasi hasilnya!",
    'filename' => $filename,
]);
