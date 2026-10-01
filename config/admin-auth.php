<?php
// config/admin-auth.php — Helper autentikasi admin (session PHP native)

require_once __DIR__ . '/helpers.php';

/**
 * Mulai session dengan konfigurasi aman.
 */
function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,          // session cookie (hilang saat browser ditutup)
            'path'     => '/admin',
            'secure'   => (env('APP_ENV', 'production') === 'production'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('photopedia_admin');
        session_start();
    }
}

/**
 * Cek apakah admin sudah login.
 * Jika belum, redirect ke /admin/login.
 */
function require_admin_auth(): void
{
    admin_session_start();
    if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: /admin/login');
        exit;
    }
}

/**
 * Login admin: validasi username + password ke tabel admin_users.
 * Kembalikan true jika berhasil, false jika gagal.
 */
function admin_login(string $username, string $password): bool
{
    $supabaseUrl    = rtrim(env('SUPABASE_URL'), '/');
    $serviceRoleKey = trim(env('SUPABASE_SERVICE_ROLE_KEY'));

    if (!$supabaseUrl || !$serviceRoleKey) return false;

    $url = $supabaseUrl . '/rest/v1/admin_users?username=eq.' . urlencode($username) . '&select=password_hash';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $serviceRoleKey,
            'apikey: ' . $serviceRoleKey,
            'Accept: application/json',
        ],
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code < 200 || $code >= 300) return false;

    $rows = json_decode($res, true);
    if (!is_array($rows) || empty($rows)) return false;

    $hash = $rows[0]['password_hash'] ?? '';
    return password_verify($password, $hash);
}

/**
 * Set session sebagai logged-in admin.
 */
function admin_set_session(string $username): void
{
    admin_session_start();
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username']  = $username;
    $_SESSION['admin_login_at']  = time();
}

/**
 * Destroy session admin (logout).
 */
function admin_logout(): void
{
    admin_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Generate CSRF token dan simpan di session.
 */
function csrf_token(): string
{
    admin_session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validasi CSRF token dari POST.
 * Die 403 jika tidak valid.
 */
function csrf_verify(): void
{
    admin_session_start();
    $submitted = $_POST['csrf_token'] ?? '';
    $stored    = $_SESSION['csrf_token'] ?? '';
    if (!$stored || !hash_equals($stored, $submitted)) {
        http_response_code(403);
        die('<h1>403 – Token CSRF tidak valid. Silakan kembali dan coba lagi.</h1>');
    }
    // Regenerate token setelah dipakai
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
