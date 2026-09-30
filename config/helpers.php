<?php
// config/helpers.php — Fungsi pembantu global Photopedia

require_once __DIR__ . '/env.php';

// ── HTTP Response Helpers ────────────────────────────────────
function respond_json(mixed $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function respond_html(string $html, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

// ── CORS (untuk API endpoints) ───────────────────────────────
function set_cors_headers(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ── Frame Discovery ──────────────────────────────────────────
/**
 * Ambil daftar frame dari tabel Supabase `frames`.
 * Format return sama persis dengan versi hardcode sebelumnya
 * agar frontend tidak perlu diubah.
 *
 * Cache: Vercel edge akan cache response selama 5 menit
 * via header Cache-Control yang di-set di api/frames.php.
 */
function get_frames(): array
{
    $supabaseUrl = env('SUPABASE_URL');
    $anonKey     = env('SUPABASE_ANON_KEY');

    if (!$supabaseUrl || !$anonKey) {
        error_log('[Photopedia] get_frames: SUPABASE_URL atau SUPABASE_ANON_KEY tidak dikonfigurasi');
        return [];
    }

    $endpoint = rtrim($supabaseUrl, '/')
        . '/rest/v1/frames'
        . '?select=id,label,filename,slots,width,height,sort_order'
        . '&is_active=eq.true'
        . '&order=sort_order.asc,label.asc';

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $anonKey,
            'apikey: '             . $anonKey,
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log("[Photopedia] get_frames: cURL error — $curlErr");
        return [];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("[Photopedia] get_frames: Supabase HTTP $httpCode — $response");
        return [];
    }

    $rows = json_decode($response, true);
    if (!is_array($rows)) {
        error_log('[Photopedia] get_frames: Respons bukan JSON array yang valid');
        return [];
    }

    $publicBase = 'https://jkcrgurzbcvlwsyrmits.supabase.co/storage/v1/object/public/frames';
    $frames     = [];

    foreach ($rows as $row) {
        $filename = $row['filename'] ?? ('frame-' . ($row['id'] ?? 'unknown') . '.png');
        $slots    = $row['slots']   ?? [];

        // Supabase JSONB kadang dikembalikan sebagai string, decode jika perlu
        if (is_string($slots)) {
            $slots = json_decode($slots, true) ?? [];
        }

        // Fallback slot jika kosong
        if (empty($slots)) {
            $slots = [['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100]];
        }

        $frames[] = [
            'id'        => $row['id'],
            'label'     => $row['label'],
            'filename'  => $filename,
            'url'       => $publicBase . '/' . $filename,
            'thumbnail' => $publicBase . '/' . $filename,
            'width'     => (int) ($row['width']  ?? 1080),
            'height'    => (int) ($row['height'] ?? 1920),
            'slots'     => $slots,
        ];
    }

    return $frames;
}

// ── Request Helpers ──────────────────────────────────────────
function get_json_body(): array
{
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

function sanitize(string $value): string
{
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// ── Simple Rate Limiter (file-based, Vercel /tmp) ────────────
function rate_limit(string $key, int $max = 10, int $windowSec = 60): void
{
    $tmpDir  = sys_get_temp_dir();
    $file    = $tmpDir . '/rl_' . md5($key) . '.json';
    $now     = time();
    $data    = [];

    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?? [];
    }

    // Buang entri yang sudah expired
    $data = array_filter($data, fn($ts) => ($now - $ts) < $windowSec);

    if (count($data) >= $max) {
        respond_json(['error' => 'Too many requests. Coba lagi sebentar.'], 429);
    }

    $data[] = $now;
    file_put_contents($file, json_encode(array_values($data)));
}
