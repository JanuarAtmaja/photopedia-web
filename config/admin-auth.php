<?php
// config/admin-auth.php — Helper autentikasi admin (session PHP native)

require_once __DIR__ . '/helpers.php';

/**
 * Mulai session dengan konfigurasi aman.
 */
function admin_session_start(): void
{
    static $started = false;
    if ($started) return;

    $_SESSION = [];
    $cookie = $_COOKIE['photopedia_admin_auth'] ?? '';
    $parts = is_string($cookie) ? explode('.', $cookie, 2) : [];
    if (count($parts) !== 2) {
        $started = true;
        return;
    }

    $secret = admin_session_secret();
    if (!$secret || !hash_equals(hash_hmac('sha256', $parts[0], $secret), $parts[1])) {
        $started = true;
        return;
    }

    $encoded = strtr($parts[0], '-_', '+/');
    $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
    $json = base64_decode($encoded, true);
    $data = $json === false ? null : json_decode($json, true);
    $issuedAt = is_array($data) ? ($data['iat'] ?? null) : null;
    $csrfToken = is_array($data) ? ($data['csrf'] ?? '') : '';

    if (
        !is_array($data) ||
        !is_int($issuedAt) ||
        $issuedAt > time() + 60 ||
        time() - $issuedAt > 8 * 60 * 60 ||
        !is_string($csrfToken) ||
        strlen($csrfToken) !== 64
    ) {
        $started = true;
        return;
    }

    $_SESSION = [
        'csrf_token' => $csrfToken,
        'admin_login_at' => $issuedAt,
    ];
    if (($data['authenticated'] ?? false) === true && !empty($data['username']) && is_string($data['username'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $data['username'];
    }

    $started = true;
}

function admin_session_secret(): string
{
    return trim((string) (env('ADMIN_SESSION_SECRET') ?: env('SUPABASE_SERVICE_ROLE_KEY')));
}

function admin_save_cookie(bool $authenticated, string $username, string $csrfToken): void
{
    $secret = admin_session_secret();
    if (!$secret) {
        error_log('[Photopedia] Admin session signing key is not configured');
        return;
    }

    $payload = json_encode([
        'authenticated' => $authenticated,
        'username' => $username,
        'csrf' => $csrfToken,
        'iat' => time(),
    ]);
    if ($payload === false) {
        error_log('[Photopedia] Failed to encode admin session');
        return;
    }

    $encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $encoded, $secret);
    setcookie('photopedia_admin_auth', $encoded . '.' . $signature, [
        'expires' => 0,
        'path' => '/',
        'secure' => (env('APP_ENV', 'production') === 'production'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
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
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username']  = $username;
    $_SESSION['admin_login_at']  = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    admin_save_cookie(true, $username, $_SESSION['csrf_token']);
}

/**
 * Destroy session admin (logout).
 */
function admin_logout(): void
{
    admin_session_start();
    $_SESSION = [];
    $cookieOptions = [
        'expires' => time() - 42000,
        'path' => '/',
        'secure' => (env('APP_ENV', 'production') === 'production'),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    setcookie('photopedia_admin_auth', '', $cookieOptions);
    setcookie('photopedia_admin', '', $cookieOptions);
    setcookie('photopedia_admin', '', array_replace($cookieOptions, ['path' => '/admin']));
}

/**
 * Generate CSRF token dan simpan di session.
 */
function csrf_token(): string
{
    admin_session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        admin_save_cookie(
            !empty($_SESSION['admin_logged_in']),
            $_SESSION['admin_username'] ?? '',
            $_SESSION['csrf_token']
        );
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
    admin_save_cookie(
        !empty($_SESSION['admin_logged_in']),
        $_SESSION['admin_username'] ?? '',
        $_SESSION['csrf_token']
    );
}
