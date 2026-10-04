<?php
// public/admin/login.php — Halaman login admin
require_once dirname(__DIR__, 2) . '/config/admin-auth.php';

admin_session_start();

// Redirect jika sudah login
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: /admin');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip       = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Rate limit: max 5 percobaan per menit per IP
    rate_limit('admin_login_' . $ip, 5, 60);

    if ($username && $password && admin_login($username, $password)) {
        admin_set_session($username);
        header('Location: /admin');
        exit;
    } else {
        $error = 'Username atau password salah.';
        // Tambah delay untuk brute-force mitigation
        sleep(1);
    }
}

$token = csrf_token();
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — Photopedia</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png?v=01ecc162">
  <link rel="shortcut icon" href="/favicon.ico?v=eca0c5b8">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --navy: #0F1B3D; --surface: #1A2755; --border: rgba(255,255,255,0.10);
      --purple: #4B3FA0; --purple-lt: #6B5FD0; --lime: #D4F04A;
      --text: #E8E8FF; --text-muted: rgba(232,232,255,0.55); --pink: #FF2D78;
    }
    body {
      font-family: 'Inter', sans-serif;
      background: var(--navy);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .login-wrap {
      width: 100%;
      max-width: 400px;
    }
    .login-logo {
      text-align: center;
      margin-bottom: 32px;
    }
    .login-logo img { height: 36px; }
    .login-badge {
      display: inline-block;
      background: rgba(212,240,74,0.15);
      color: var(--lime);
      border: 1px solid rgba(212,240,74,0.30);
      border-radius: 50px;
      font-size: 12px;
      font-weight: 600;
      padding: 4px 12px;
      letter-spacing: 0.5px;
      margin-top: 10px;
    }
    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 36px 32px;
    }
    h1 {
      font-size: 22px;
      font-weight: 800;
      margin-bottom: 6px;
    }
    .sub {
      font-size: 14px;
      color: var(--text-muted);
      margin-bottom: 28px;
    }
    .field { margin-bottom: 20px; }
    .field label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: var(--text);
      margin-bottom: 8px;
    }
    .input {
      width: 100%;
      background: rgba(255,255,255,0.06);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 12px 16px;
      font-size: 15px;
      font-family: inherit;
      color: var(--text);
      outline: none;
      transition: border-color 0.2s;
    }
    .input:focus { border-color: var(--purple-lt); }
    .input::placeholder { color: var(--text-muted); }
    .error-msg {
      background: rgba(255,45,120,0.12);
      border: 1px solid rgba(255,45,120,0.35);
      border-radius: 10px;
      padding: 12px 16px;
      font-size: 14px;
      color: #FCA5A5;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .btn-login {
      width: 100%;
      padding: 14px;
      border-radius: 50px;
      background: linear-gradient(135deg, var(--purple), var(--purple-lt));
      color: #fff;
      font-size: 16px;
      font-weight: 700;
      border: none;
      cursor: pointer;
      transition: opacity 0.2s;
      box-shadow: 0 4px 20px rgba(75,63,160,0.45);
    }
    .btn-login:hover { opacity: 0.90; }
    .back-link {
      display: block;
      text-align: center;
      margin-top: 20px;
      font-size: 14px;
      color: var(--text-muted);
      text-decoration: none;
    }
    .back-link:hover { color: var(--text); }
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="login-logo">
    <img src="/assets/images/Logo.png?v=a762fb34" alt="Photopedia">
    <div class="login-badge">Admin Panel</div>
  </div>

  <div class="card">
    <h1>Selamat datang</h1>
    <p class="sub">Masuk untuk mengelola frame submission.</p>

    <?php if ($error): ?>
      <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/admin/login">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">

      <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" class="input"
               placeholder="admin" required autocomplete="username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>

      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" class="input"
               placeholder="••••••••" required autocomplete="current-password">
      </div>

      <button type="submit" class="btn-login" id="btn-login">Masuk →</button>
    </form>
  </div>

  <a href="/" class="back-link">← Kembali ke Photopedia</a>
</div>
</body>
</html>
