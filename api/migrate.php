<?php
// api/migrate.php — Import foto dari Supabase Storage ke tabel photos
// GET /api/migrate  (hanya dijalankan sekali, hapus setelah selesai)

require_once dirname(__DIR__) . '/config/helpers.php';
set_cors_headers();

$supabaseUrl    = env('SUPABASE_URL');
$serviceRoleKey = env('SUPABASE_SERVICE_ROLE_KEY');
$bucket         = env('SUPABASE_BUCKET', 'photopedia-photos');

if (!$supabaseUrl || !$serviceRoleKey) {
    respond_json(['error' => 'Supabase not configured'], 500);
}

$publicUrlBase = rtrim($supabaseUrl, '/') . '/storage/v1/object/public/' . urlencode($bucket) . '/';
$imported      = 0;
$skipped       = 0;
$errors        = [];

// ── Fungsi list file dari Storage prefix tertentu ─────────────
function listStorageFiles(string $supabaseUrl, string $serviceRoleKey, string $bucket, string $prefix): array {
    $endpoint = rtrim($supabaseUrl, '/') . '/storage/v1/object/list/' . urlencode($bucket);
    $payload  = json_encode([
        'prefix' => $prefix,
        'limit'  => 1000,
        'offset' => 0,
        'sortBy' => ['column' => 'name', 'order' => 'asc'],
    ]);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $serviceRoleKey,
            'apikey: '             . $serviceRoleKey,
            'Content-Type: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?? [];
}

// ── Fungsi insert satu record ke tabel photos ─────────────────
function insertPhoto(string $supabaseUrl, string $serviceRoleKey, string $url, string $path, string $sessionId, string $createdAt): bool {
    $endpoint = rtrim($supabaseUrl, '/') . '/rest/v1/photos';
    $payload  = json_encode([
        'url'        => $url,
        'path'       => $path,
        'session_id' => $sessionId,
        'created_at' => $createdAt,
    ]);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $serviceRoleKey,
            'apikey: '             . $serviceRoleKey,
            'Content-Type: application/json',
            'Prefer: return=minimal',
            // Ignore duplicate paths
            'On-Conflict: path',
        ],
    ]);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code >= 200 && $code < 300);
}

// ── Rekursif scan bucket ──────────────────────────────────────
// Level 1: list "sessions" → dapat folder tahun (misal: 2026/)
$level1 = listStorageFiles($supabaseUrl, $serviceRoleKey, $bucket, 'sessions');

foreach ($level1 as $item1) {
    // item tanpa id = folder
    if (!empty($item1['id'])) {
        // File langsung di sessions/ (jarang terjadi)
        $path      = 'sessions/' . $item1['name'];
        $url       = $publicUrlBase . $path;
        $sessionId = pathinfo($item1['name'], PATHINFO_FILENAME);
        $createdAt = $item1['created_at'] ?? date('c');
        $ok = insertPhoto($supabaseUrl, $serviceRoleKey, $url, $path, $sessionId, $createdAt);
        $ok ? $imported++ : $skipped++;
        continue;
    }

    // Level 2: folder tahun → list bulan
    $yearPrefix = 'sessions/' . $item1['name'];
    $level2 = listStorageFiles($supabaseUrl, $serviceRoleKey, $bucket, $yearPrefix);

    foreach ($level2 as $item2) {
        if (!empty($item2['id'])) {
            $path      = $yearPrefix . '/' . $item2['name'];
            $url       = $publicUrlBase . $path;
            $sessionId = pathinfo($item2['name'], PATHINFO_FILENAME);
            $createdAt = $item2['created_at'] ?? date('c');
            $ok = insertPhoto($supabaseUrl, $serviceRoleKey, $url, $path, $sessionId, $createdAt);
            $ok ? $imported++ : $skipped++;
            continue;
        }

        // Level 3: folder bulan → list hari
        $monthPrefix = $yearPrefix . '/' . $item2['name'];
        $level3 = listStorageFiles($supabaseUrl, $serviceRoleKey, $bucket, $monthPrefix);

        foreach ($level3 as $item3) {
            if (!empty($item3['id'])) {
                $path      = $monthPrefix . '/' . $item3['name'];
                $url       = $publicUrlBase . $path;
                $sessionId = pathinfo($item3['name'], PATHINFO_FILENAME);
                $createdAt = $item3['created_at'] ?? date('c');
                $ok = insertPhoto($supabaseUrl, $serviceRoleKey, $url, $path, $sessionId, $createdAt);
                $ok ? $imported++ : $skipped++;
                continue;
            }

            // Level 4: folder hari → list file
            $dayPrefix = $monthPrefix . '/' . $item3['name'];
            $level4 = listStorageFiles($supabaseUrl, $serviceRoleKey, $bucket, $dayPrefix);

            foreach ($level4 as $item4) {
                if (empty($item4['id'])) continue; // skip subfolder
                if (!preg_match('/\.(jpg|jpeg|png|webp)$/i', $item4['name'])) continue;

                $path      = $dayPrefix . '/' . $item4['name'];
                $url       = $publicUrlBase . $path;
                $sessionId = pathinfo($item4['name'], PATHINFO_FILENAME);
                $createdAt = $item4['created_at'] ?? date('c');
                $ok = insertPhoto($supabaseUrl, $serviceRoleKey, $url, $path, $sessionId, $createdAt);
                $ok ? $imported++ : $skipped++;
            }
        }
    }
}

respond_json([
    'success'  => true,
    'imported' => $imported,
    'skipped'  => $skipped,
    'message'  => "Selesai! $imported foto berhasil diimport, $skipped gagal/sudah ada.",
]);
