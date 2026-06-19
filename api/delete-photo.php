<?php
// api/delete-photo.php — Soft delete foto dari gallery (set hidden = true)
// POST /api/delete-photo  body: { "id": "<uuid>" }

require_once dirname(__DIR__) . '/config/helpers.php';
set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(['error' => 'Method not allowed'], 405);
}

$body = get_json_body();
$id   = trim($body['id'] ?? '');

if (!$id || !preg_match('/^[0-9a-f\-]{36}$/i', $id)) {
    respond_json(['error' => 'ID tidak valid'], 400);
}

$supabaseUrl    = env('SUPABASE_URL');
$serviceRoleKey = env('SUPABASE_SERVICE_ROLE_KEY');

if (!$supabaseUrl || !$serviceRoleKey) {
    respond_json(['error' => 'Supabase not configured'], 500);
}

// PATCH /rest/v1/photos?id=eq.<uuid>  → set hidden = true
$endpoint = rtrim($supabaseUrl, '/') . '/rest/v1/photos?id=eq.' . urlencode($id);
$payload  = json_encode(['hidden' => true]);

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST  => 'PATCH',
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $serviceRoleKey,
        'apikey: '             . $serviceRoleKey,
        'Content-Type: application/json',
        'Prefer: return=minimal',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 300) {
    respond_json(['success' => true, 'message' => 'Foto berhasil disembunyikan dari gallery']);
} else {
    respond_json(['error' => 'Gagal update database', 'details' => json_decode($response, true)], 502);
}
