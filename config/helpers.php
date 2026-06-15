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
            'film-strip' => [
                ['x' => 10, 'y' => 8, 'width' => 80, 'height' => 22],
                ['x' => 10, 'y' => 32, 'width' => 80, 'height' => 22],
                ['x' => 10, 'y' => 56, 'width' => 80, 'height' => 22],
            ],
            'music-player' => [
                ['x' => 10, 'y' => 4, 'width' => 80, 'height' => 22],
                ['x' => 10, 'y' => 29, 'width' => 80, 'height' => 22],
                ['x' => 10, 'y' => 54, 'width' => 80, 'height' => 22],
            ],
            'y2k-grid' => [
                ['x' => 10, 'y' => 4, 'width' => 80, 'height' => 22],
                ['x' => 10, 'y' => 29, 'width' => 80, 'height' => 22],
                ['x' => 10, 'y' => 54, 'width' => 80, 'height' => 22],
            ],
            'happiness' => [
                ['x' => 7.78, 'y' => 2.15, 'width' => 84.44, 'height' => 23.11],
                ['x' => 7.78, 'y' => 28.71, 'width' => 84.44, 'height' => 23.06],
                ['x' => 7.78, 'y' => 55.18, 'width' => 84.44, 'height' => 23.11],
            ],
            'hello' => [
                ['x' => 7.78, 'y' => 2.2, 'width' => 84.44, 'height' => 23.06],
                ['x' => 7.78, 'y' => 28.66, 'width' => 84.44, 'height' => 23.11],
                ['x' => 7.78, 'y' => 55.18, 'width' => 84.44, 'height' => 23.06],
            ],
            'see-yourself' => [
                ['x' => 12.69, 'y' => 9.48, 'width' => 74.63, 'height' => 34.07],
                ['x' => 12.69, 'y' => 54.89, 'width' => 74.63, 'height' => 34.07],
            ]
        ];

        $slots = $slotsData[$slug] ?? [['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100]]; // fallback to 1 slot full size

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
