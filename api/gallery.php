<?php
// api/gallery.php — Fetch gallery dari Supabase Database (tabel: photos)
// GET /api/gallery

require_once dirname(__DIR__) . '/config/helpers.php';

set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_json(['error' => 'Method not allowed'], 405);
}

$supabaseUrl    = env('SUPABASE_URL');
$serviceRoleKey = env('SUPABASE_SERVICE_ROLE_KEY'); // pakai service role agar bypass RLS

if (!$supabaseUrl || !$serviceRoleKey) {
    respond_json(['error' => 'Supabase not configured'], 500);
}

// ── Query tabel photos, urut terbaru dulu, limit 60 ──────────
$endpoint = rtrim($supabaseUrl, '/') . '/rest/v1/photos'
    . '?select=id,url,session_id,created_at'
    . '&order=created_at.desc'
    . '&limit=60';

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $serviceRoleKey,
        'apikey: '             . $serviceRoleKey,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    respond_json(['error' => 'cURL error: ' . $curlErr, 'images' => []], 500);
}

if ($httpCode < 200 || $httpCode >= 300) {
    respond_json([
        'error'   => 'Failed to fetch from database',
        'details' => json_decode($response, true),
        'status'  => $httpCode,
        'images'  => [],
    ], 502);
}

$rows   = json_decode($response, true) ?? [];
$images = array_map(fn($row) => [
    'id'         => $row['id'],
    'url'        => $row['url'],
    'session_id' => $row['session_id'],
    'created_at' => $row['created_at'],
], $rows);

respond_json(['images' => $images, 'count' => count($images)]);
