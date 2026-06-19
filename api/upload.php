<?php
// api/upload.php — Upload foto ke Supabase Storage
// POST /api/upload  (multipart/form-data, field: "photo")

require_once dirname(__DIR__) . '/config/helpers.php';

set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(['error' => 'Method not allowed'], 405);
}

// ── Validasi file upload ──────────────────────────────────────
if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['photo']['error'] ?? -1;
    respond_json(['error' => "File upload error: $errCode"], 400);
}

$file     = $_FILES['photo'];
$mimeType = mime_content_type($file['tmp_name']);
$allowed  = ['image/jpeg', 'image/png', 'image/webp'];

if (!in_array($mimeType, $allowed, true)) {
    respond_json(['error' => 'Only JPEG, PNG, and WEBP images are allowed'], 400);
}

$maxSize = 15 * 1024 * 1024; // 15 MB
if ($file['size'] > $maxSize) {
    respond_json(['error' => 'File too large (max 15 MB)'], 400);
}

// ── Konfigurasi Supabase ──────────────────────────────────────
$supabaseUrl        = env('SUPABASE_URL');
$serviceRoleKey     = env('SUPABASE_SERVICE_ROLE_KEY');
$bucket             = env('SUPABASE_BUCKET', 'photopedia-photos');

if (!$supabaseUrl || !$serviceRoleKey) {
    respond_json(['error' => 'Supabase not configured'], 500);
}

// ── Buat path unik: sessions/YYYY/MM/DD/<uuid>.jpg ───────────
$ext       = match ($mimeType) {
    'image/png'  => 'png',
    'image/webp' => 'webp',
    default      => 'jpg',
};
$date      = date('Y/m/d');
$uuid      = bin2hex(random_bytes(8)); // 16 hex chars
$filePath  = "sessions/{$date}/{$uuid}.{$ext}";

// ── Upload ke Supabase Storage ────────────────────────────────
$endpoint = rtrim($supabaseUrl, '/') . '/storage/v1/object/' . urlencode($bucket) . '/' . $filePath;

$fileContent = file_get_contents($file['tmp_name']);

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_POSTFIELDS     => $fileContent,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $serviceRoleKey,
        'apikey: ' . $serviceRoleKey,
        'Content-Type: ' . $mimeType,
        'x-upsert: false',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    respond_json(['error' => 'cURL error: ' . $curlErr], 500);
}

$responseData = json_decode($response, true);

if ($httpCode < 200 || $httpCode >= 300) {
    respond_json([
        'error'   => 'Supabase upload failed',
        'details' => $responseData,
        'status'  => $httpCode,
    ], 502);
}

// ── Buat public URL ───────────────────────────────────────────
$publicUrl = rtrim($supabaseUrl, '/') . '/storage/v1/object/public/' . urlencode($bucket) . '/' . $filePath;

respond_json([
    'success'    => true,
    'url'        => $publicUrl,
    'path'       => $filePath,
    'session_id' => $uuid,
]);
