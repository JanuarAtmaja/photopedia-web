<?php
// api/admin/frames.php — Admin CRUD untuk tabel frames
//
// Semua request WAJIB menyertakan header:
//   X-Admin-Secret: <nilai ADMIN_SECRET di .env>
//
// Endpoints:
//   GET    /api/admin/frames          → list semua frame (termasuk inactive)
//   POST   /api/admin/frames          → tambah frame baru
//   PATCH  /api/admin/frames?id=xxx   → update frame (slots, label, is_active, dll)
//   DELETE /api/admin/frames?id=xxx   → nonaktifkan frame (soft delete)

require_once dirname(__DIR__, 2) . '/config/helpers.php';

set_cors_headers();

// ── Auth: cek ADMIN_SECRET ────────────────────────────────────
$adminSecret = env('ADMIN_SECRET');
$provided    = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';

if (!$adminSecret || $provided !== $adminSecret) {
    respond_json(['error' => 'Unauthorized'], 401);
}

// ── Supabase config ───────────────────────────────────────────
$supabaseUrl    = env('SUPABASE_URL');
$serviceRoleKey = env('SUPABASE_SERVICE_ROLE_KEY');

if (!$supabaseUrl || !$serviceRoleKey) {
    respond_json(['error' => 'Supabase not configured'], 500);
}

$method = $_SERVER['REQUEST_METHOD'];

// ── Helper: cURL ke Supabase REST ────────────────────────────
function supabase_request(string $method, string $path, ?array $payload = null, array $extraHeaders = []): array
{
    global $supabaseUrl, $serviceRoleKey;

    $url = rtrim($supabaseUrl, '/') . '/rest/v1/' . ltrim($path, '/');

    $headers = array_merge([
        'Authorization: Bearer ' . $serviceRoleKey,
        'apikey: '             . $serviceRoleKey,
        'Content-Type: application/json',
        'Accept: application/json',
        'Prefer: return=representation',
    ], $extraHeaders);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => $headers,
    ]);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['ok' => false, 'status' => 0, 'body' => null, 'error' => $curlErr];
    }

    $body = json_decode($response, true);
    return [
        'ok'     => $httpCode >= 200 && $httpCode < 300,
        'status' => $httpCode,
        'body'   => $body,
        'raw'    => $response,
    ];
}

// ════════════════════════════════════════════════════════════
//  GET — list semua frame
// ════════════════════════════════════════════════════════════
if ($method === 'GET') {
    $result = supabase_request('GET', 'frames?select=*&order=sort_order.asc,label.asc');

    if (!$result['ok']) {
        respond_json(['error' => 'Gagal mengambil data frame', 'detail' => $result['raw']], 502);
    }

    respond_json([
        'success' => true,
        'frames'  => $result['body'] ?? [],
        'count'   => count($result['body'] ?? []),
    ]);
}

// ════════════════════════════════════════════════════════════
//  POST — tambah frame baru
// ════════════════════════════════════════════════════════════
if ($method === 'POST') {
    $body = get_json_body();

    // Validasi field wajib
    foreach (['id', 'label', 'filename'] as $field) {
        if (empty($body[$field])) {
            respond_json(['error' => "Field '$field' wajib diisi"], 400);
        }
    }

    // Sanitasi
    $id       = preg_replace('/[^a-z0-9\-\.\_]/', '', strtolower(trim($body['id'])));
    $label    = sanitize($body['label']);
    $filename = sanitize($body['filename']);
    $slots    = $body['slots']      ?? [];
    $width    = (int) ($body['width']   ?? 1080);
    $height   = (int) ($body['height']  ?? 1920);
    $isActive = isset($body['is_active']) ? (bool) $body['is_active'] : true;
    $sortOrder = (int) ($body['sort_order'] ?? 0);

    if (!is_array($slots)) {
        respond_json(['error' => "'slots' harus berupa array"], 400);
    }

    $payload = [
        'id'         => $id,
        'label'      => $label,
        'filename'   => $filename,
        'slots'      => $slots,
        'width'      => $width,
        'height'     => $height,
        'is_active'  => $isActive,
        'sort_order' => $sortOrder,
    ];

    $result = supabase_request('POST', 'frames', $payload, [
        'Prefer: resolution=merge-duplicates,return=representation',
    ]);

    if (!$result['ok']) {
        respond_json(['error' => 'Gagal insert frame', 'detail' => $result['raw']], 502);
    }

    respond_json(['success' => true, 'frame' => $result['body'][0] ?? $result['body']]);
}

// ════════════════════════════════════════════════════════════
//  PATCH — update frame (partial update)
// ════════════════════════════════════════════════════════════
if ($method === 'PATCH') {
    $id = trim($_GET['id'] ?? '');
    if (!$id) {
        respond_json(['error' => 'Parameter ?id= wajib diisi'], 400);
    }

    $body    = get_json_body();
    $payload = [];

    // Hanya update field yang dikirim
    if (isset($body['label']))      $payload['label']      = sanitize($body['label']);
    if (isset($body['filename']))   $payload['filename']   = sanitize($body['filename']);
    if (isset($body['slots']))      $payload['slots']      = $body['slots'];
    if (isset($body['width']))      $payload['width']      = (int) $body['width'];
    if (isset($body['height']))     $payload['height']     = (int) $body['height'];
    if (isset($body['is_active']))  $payload['is_active']  = (bool) $body['is_active'];
    if (isset($body['sort_order'])) $payload['sort_order'] = (int) $body['sort_order'];

    if (empty($payload)) {
        respond_json(['error' => 'Tidak ada field yang diupdate'], 400);
    }

    $frameId = urlencode($id);
    $result  = supabase_request('PATCH', "frames?id=eq.$frameId", $payload);

    if (!$result['ok']) {
        respond_json(['error' => 'Gagal update frame', 'detail' => $result['raw']], 502);
    }

    respond_json(['success' => true, 'frame' => $result['body'][0] ?? $result['body']]);
}

// ════════════════════════════════════════════════════════════
//  DELETE — soft delete (set is_active = false)
// ════════════════════════════════════════════════════════════
if ($method === 'DELETE') {
    $id = trim($_GET['id'] ?? '');
    if (!$id) {
        respond_json(['error' => 'Parameter ?id= wajib diisi'], 400);
    }

    $frameId = urlencode($id);
    $result  = supabase_request('PATCH', "frames?id=eq.$frameId", ['is_active' => false]);

    if (!$result['ok']) {
        respond_json(['error' => 'Gagal menonaktifkan frame', 'detail' => $result['raw']], 502);
    }

    respond_json(['success' => true, 'message' => "Frame '$id' dinonaktifkan"]);
}

respond_json(['error' => 'Method not allowed'], 405);
