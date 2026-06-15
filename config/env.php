<?php
// config/env.php — Load & validasi environment variables
// Untuk dev lokal: buat file .env di root project
// Untuk Vercel: set via dashboard Settings > Environment Variables

$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        if (!array_key_exists($key, $_ENV)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Shorthand getter dengan default
function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// Validasi wajib di production
if (env('APP_ENV', 'production') === 'production') {
    $required = ['RESEND_API_KEY', 'SUPABASE_URL', 'SUPABASE_ANON_KEY', 'SUPABASE_BUCKET'];
    foreach ($required as $var) {
        if (!env($var)) {
            http_response_code(500);
            die(json_encode(['error' => "Missing required env var: $var"]));
        }
    }
}
