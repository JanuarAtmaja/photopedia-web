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
 * Scan folder assets/frames dan kembalikan array frame siap pakai.
 * Konvensi nama: frame-[slug].png → label = ucwords(slug)
 */
function get_frames(): array
{
    $framesDir  = dirname(__DIR__) . '/assets/frames';
    $publicBase = '/assets/frames';
    $frames     = [];

    if (!is_dir($framesDir)) return $frames;

    $files = glob($framesDir . '/frame-*.png');
    sort($files); // urutan konsisten

    foreach ($files as $file) {
        $basename = basename($file, '.png');          // frame-film-strip
        $slug     = substr($basename, 6);             // film-strip
        $label    = ucwords(str_replace('-', ' ', $slug)); // Film Strip

        // Define slots metadata based on frame slug.
        // Slots are defined in percentages: x, y, width, height
        $slotsData = [
            'arctic-monkeys-am' => [
                ['x' => 10.75, 'y' => 10.86, 'width' => 78.36, 'height' => 22.11],
                ['x' => 10.89, 'y' => 35.02, 'width' => 78.36, 'height' => 22.11],
                ['x' => 10.75, 'y' => 59.13, 'width' => 78.36, 'height' => 22.11],
            ],
            'arctic-monkeys-wfn' => [
                ['x' => 5.52, 'y' => 2.7, 'width' => 88.97, 'height' => 31.47],
                ['x' => 5.52, 'y' => 36.57, 'width' => 88.97, 'height' => 31.47],
            ],
            'arctic-monkeys-whatever' => [
                ['x' => 5.66, 'y' => 2.3, 'width' => 88.54, 'height' => 23.51],
                ['x' => 5.66, 'y' => 28.11, 'width' => 88.54, 'height' => 23.51],
                ['x' => 5.66, 'y' => 53.93, 'width' => 88.54, 'height' => 23.51],
            ],
            'expo-stat-2026' => [
                ['x' => 15.37, 'y' => 13.48, 'width' => 69.26, 'height' => 32.74],
                ['x' => 15.37, 'y' => 54.74, 'width' => 69.26, 'height' => 32.67],
            ],
            'expo-stat-special' => [
                ['x' => 10.04, 'y' => 2.95, 'width' => 79.92, 'height' => 21.56],
                ['x' => 10.04, 'y' => 29.46, 'width' => 79.77, 'height' => 21.51],
                ['x' => 10.04, 'y' => 56.68, 'width' => 79.77, 'height' => 21.51],
            ],
            'happiness' => [
                ['x' => 7.92, 'y' => 2.2, 'width' => 84.3, 'height' => 23.06],
                ['x' => 7.92, 'y' => 28.71, 'width' => 84.3, 'height' => 23.01],
                ['x' => 7.92, 'y' => 55.23, 'width' => 84.3, 'height' => 23.01],
            ],
            'hello' => [
                ['x' => 7.92, 'y' => 2.2, 'width' => 84.3, 'height' => 23.06],
                ['x' => 7.92, 'y' => 28.71, 'width' => 84.3, 'height' => 23.01],
                ['x' => 7.92, 'y' => 55.23, 'width' => 84.3, 'height' => 23.01],
            ],
            'hindia-janji-palsu' => [
                ['x' => 13.89, 'y' => 19.37, 'width' => 72.22, 'height' => 21.05],
                ['x' => 13.89, 'y' => 41.26, 'width' => 72.22, 'height' => 21.05],
                ['x' => 13.89, 'y' => 63.14, 'width' => 72.22, 'height' => 21.05],
            ],
            'hold-up' => [
                ['x' => 8.41, 'y' => 2.32, 'width' => 83.18, 'height' => 23.54],
                ['x' => 8.5, 'y' => 25.96, 'width' => 83.09, 'height' => 27.24],
                ['x' => 8.5, 'y' => 55.46, 'width' => 83.09, 'height' => 22.37],
            ],
            'messsage-us' => [
                ['x' => 9.24, 'y' => 10.17, 'width' => 81.52, 'height' => 21.78],
                ['x' => 9.24, 'y' => 36.79, 'width' => 81.52, 'height' => 21.81],
                ['x' => 9.24, 'y' => 63.6, 'width' => 81.52, 'height' => 21.78],
            ],
            'music-player' => [
                ['x' => 10.12, 'y' => 3.8, 'width' => 79.75, 'height' => 21.8],
                ['x' => 10.12, 'y' => 28.85, 'width' => 79.75, 'height' => 21.8],
                ['x' => 10.12, 'y' => 53.9, 'width' => 79.75, 'height' => 21.8],
            ],
            'perunggu-dalam-dinamika' => [
                ['x' => 7.21, 'y' => 2.5, 'width' => 85.57, 'height' => 30.27],
                ['x' => 7.21, 'y' => 34.17, 'width' => 85.57, 'height' => 30.27],
            ],
            'please-verify' => [
                ['x' => 2.49, 'y' => 19.33, 'width' => 94.92, 'height' => 21.45],
                ['x' => 2.49, 'y' => 42.09, 'width' => 94.92, 'height' => 21.45],
                ['x' => 2.49, 'y' => 64.85, 'width' => 94.92, 'height' => 21.45],
            ],
            'reality-club-presents...' => [
                ['x' => 2.55, 'y' => 7.25, 'width' => 94.63, 'height' => 29.16],
                ['x' => 2.55, 'y' => 51.28, 'width' => 94.63, 'height' => 29.16],
            ],
            'reality-club-who-knows-where-life-will-take-you' => [
                ['x' => 6.93, 'y' => 2.4, 'width' => 86.28, 'height' => 19.96],
                ['x' => 6.93, 'y' => 25.21, 'width' => 86.28, 'height' => 19.96],
                ['x' => 6.93, 'y' => 47.92, 'width' => 86.28, 'height' => 19.96],
            ],
            'the-jeblogs-sambutlah' => [
                ['x' => 3.96, 'y' => 4.15, 'width' => 92.5, 'height' => 22.16],
                ['x' => 3.96, 'y' => 27.86, 'width' => 92.5, 'height' => 22.16],
                ['x' => 3.96, 'y' => 51.58, 'width' => 92.5, 'height' => 22.16],
            ],
            'y2k-grid' => [
                ['x' => 10.12, 'y' => 3.8, 'width' => 80.06, 'height' => 21.8],
                ['x' => 10.12, 'y' => 28.85, 'width' => 79.75, 'height' => 21.8],
                ['x' => 10.12, 'y' => 53.9, 'width' => 79.75, 'height' => 21.8],
            ]
        ];

        $slots = $slotsData[strtolower($slug)] ?? [['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100]]; // fallback to 1 slot full size

        $size = getimagesize($file);
        $frameWidth = $size[0] ?? 1080;
        $frameHeight = $size[1] ?? 1920;

        $frames[] = [
            'id'        => $slug,
            'label'     => $label,
            'filename'  => basename($file),
            'url'       => $publicBase . '/' . basename($file),
            'thumbnail' => $publicBase . '/' . basename($file),
            'width'     => $frameWidth,
            'height'    => $frameHeight,
            'slots'     => $slots,
            'mtime'     => filemtime($file),
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
