<?php
// public/admin/index.php — Admin Dashboard: list frame_submissions pending
require_once dirname(__DIR__, 2) . '/config/admin-auth.php';

require_admin_auth();

$username = htmlspecialchars($_SESSION['admin_username'] ?? 'Admin');
$token    = csrf_token();

// Ambil status filter
$status = $_GET['status'] ?? 'pending';
$allowedStatuses = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($status, $allowedStatuses, true)) $status = 'pending';

// Fetch submissions dari Supabase
$supabaseUrl    = rtrim(env('SUPABASE_URL'), '/');
$serviceRoleKey = trim(env('SUPABASE_SERVICE_ROLE_KEY'));

$submissions = [];
$fetchError  = '';

if ($supabaseUrl && $serviceRoleKey) {
    $filter = ($status === 'all') ? '' : '&status=eq.' . $status;
    $url    = $supabaseUrl . '/rest/v1/frame_submissions?select=*' . $filter . '&order=submitted_at.desc';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $serviceRoleKey,
            'apikey: ' . $serviceRoleKey,
            'Accept: application/json',
        ],
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300) {
        $submissions = json_decode($res, true) ?? [];
    } else {
        $fetchError = 'Gagal memuat data dari Supabase (HTTP ' . $code . ')';
    }
} else {
    $fetchError = 'Supabase belum dikonfigurasi.';
}

function badge(string $s): string {
    $map = ['pending' => '#FEF3C7:#92400E', 'approved' => '#D1FAE5:#065F46', 'rejected' => '#FEE2E2:#991B1B'];
    [$bg, $color] = explode(':', $map[$s] ?? '#E5E7EB:#374151');
    return "<span style=\"background:$bg;color:$color;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600\">" . htmlspecialchars(strtoupper($s)) . "</span>";
}

function fmt_date(string $ts): string {
    return date('d M Y, H:i', strtotime($ts));
}
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Photopedia Admin</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --navy: #0F1B3D; --surface: #1A2755; --surface-2: #1F2F66;
      --border: rgba(255,255,255,0.10); --purple: #4B3FA0; --purple-lt: #6B5FD0;
      --lime: #D4F04A; --pink: #FF2D78;
      --text: #E8E8FF; --text-muted: rgba(232,232,255,0.55);
    }
    body { font-family: 'Inter', sans-serif; background: var(--navy); color: var(--text); min-height: 100vh; }

    /* Header */
    .admin-header {
      background: rgba(15,27,61,0.90);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--border);
      position: sticky; top: 0; z-index: 10;
      padding: 0 24px;
    }
    .header-inner {
      max-width: 1100px; margin: 0 auto;
      display: flex; align-items: center; justify-content: space-between; height: 64px;
    }
    .header-brand { display: flex; align-items: center; gap: 12px; }
    .header-brand img { height: 28px; }
    .header-badge {
      background: rgba(212,240,74,0.15); color: var(--lime);
      border: 1px solid rgba(212,240,74,0.30);
      border-radius: 50px; font-size: 11px; font-weight: 700;
      padding: 3px 10px; letter-spacing: 0.5px; text-transform: uppercase;
    }
    .header-actions { display: flex; align-items: center; gap: 12px; }
    .user-label { font-size: 14px; color: var(--text-muted); }
    .btn-logout {
      padding: 7px 16px; border-radius: 50px;
      background: rgba(255,45,120,0.15); color: #FCA5A5;
      border: 1px solid rgba(255,45,120,0.30);
      font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none;
      transition: background 0.2s;
    }
    .btn-logout:hover { background: rgba(255,45,120,0.25); }

    /* Content */
    .page-wrap { max-width: 1100px; margin: 0 auto; padding: 40px 24px 80px; }

    /* Stats bar */
    .stats-bar {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 16px; margin-bottom: 32px;
    }
    .stat-card {
      background: var(--surface); border: 1px solid var(--border); border-radius: 14px;
      padding: 18px 20px;
    }
    .stat-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 6px; }
    .stat-val   { font-size: 28px; font-weight: 800; }
    .stat-val.pending  { color: #FCD34D; }
    .stat-val.approved { color: #6EE7B7; }
    .stat-val.rejected { color: #FCA5A5; }

    /* Toolbar */
    .toolbar {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 20px; flex-wrap: wrap; gap: 12px;
    }
    .toolbar h1 { font-size: 20px; font-weight: 700; }
    .filter-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
    .filter-tab {
      padding: 7px 16px; border-radius: 50px;
      background: var(--surface); border: 1px solid var(--border);
      color: var(--text-muted); font-size: 13px; font-weight: 600;
      text-decoration: none; transition: all 0.2s;
    }
    .filter-tab:hover, .filter-tab.active {
      background: var(--purple); border-color: var(--purple); color: #fff;
    }

    /* Table */
    .table-wrap {
      background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
      overflow: hidden;
    }
    table { width: 100%; border-collapse: collapse; }
    thead th {
      padding: 14px 16px; text-align: left;
      font-size: 12px; font-weight: 700; text-transform: uppercase;
      letter-spacing: 0.5px; color: var(--text-muted);
      border-bottom: 1px solid var(--border);
    }
    tbody tr { transition: background 0.15s; }
    tbody tr:hover { background: rgba(255,255,255,0.03); }
    tbody td {
      padding: 14px 16px;
      border-bottom: 1px solid rgba(255,255,255,0.05);
      font-size: 14px; vertical-align: middle;
    }
    tbody tr:last-child td { border-bottom: none; }
    .thumb {
      width: 48px; height: 60px; object-fit: cover;
      border-radius: 6px; border: 1px solid var(--border);
      background: var(--surface-2);
    }
    .td-title strong { font-size: 14px; font-weight: 600; display: block; }
    .td-title span   { font-size: 12px; color: var(--text-muted); }
    .td-creator span { font-size: 12px; color: var(--text-muted); display: block; }
    .td-date { font-size: 13px; color: var(--text-muted); white-space: nowrap; }
    .btn-review {
      padding: 7px 14px; border-radius: 8px;
      background: var(--purple); color: #fff;
      text-decoration: none; font-size: 13px; font-weight: 600;
      transition: opacity 0.2s; white-space: nowrap;
    }
    .btn-review:hover { opacity: 0.85; }
    .empty-state {
      text-align: center; padding: 60px 20px; color: var(--text-muted);
    }
    .empty-state .icon { font-size: 48px; margin-bottom: 12px; }
    .alert-error {
      background: rgba(255,45,120,0.12); border: 1px solid rgba(255,45,120,0.35);
      border-radius: 12px; padding: 16px 20px;
      color: #FCA5A5; margin-bottom: 24px; font-size: 14px;
    }
  </style>
</head>
<body>

<header class="admin-header">
  <div class="header-inner">
    <div class="header-brand">
      <img src="/assets/images/Logo.png" alt="Photopedia">
      <span class="header-badge">Admin</span>
    </div>
    <div class="header-actions">
      <span class="user-label">👤 <?= $username ?></span>
      <a href="/admin/logout" class="btn-logout">Logout</a>
    </div>
  </div>
</header>

<div class="page-wrap">

  <?php
  // Hitung stats per status
  $stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
  foreach ($submissions as $sub) {
      $s = $sub['status'] ?? '';
      if (isset($stats[$s])) $stats[$s]++;
  }
  $totalAll = count($submissions);
  ?>

  <!-- Stats (hanya tampil jika status=all supaya angka akurat) -->
  <?php if ($status === 'all'): ?>
  <div class="stats-bar">
    <div class="stat-card">
      <div class="stat-label">Total</div>
      <div class="stat-val" style="color:var(--text)"><?= $totalAll ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Pending</div>
      <div class="stat-val pending"><?= $stats['pending'] ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Approved</div>
      <div class="stat-val approved"><?= $stats['approved'] ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Rejected</div>
      <div class="stat-val rejected"><?= $stats['rejected'] ?></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Toolbar -->
  <div class="toolbar">
    <h1>📋 Frame Submissions</h1>
    <div class="filter-tabs">
      <?php foreach (['pending' => '⏳ Pending', 'approved' => '✅ Approved', 'rejected' => '❌ Rejected', 'all' => 'Semua'] as $val => $label): ?>
        <a href="/admin?status=<?= $val ?>" class="filter-tab <?= $status === $val ? 'active' : '' ?>">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($fetchError): ?>
    <div class="alert-error">⚠️ <?= htmlspecialchars($fetchError) ?></div>
  <?php endif; ?>

  <div class="table-wrap">
    <?php if (empty($submissions) && !$fetchError): ?>
      <div class="empty-state">
        <div class="icon">📭</div>
        <p>Tidak ada submission dengan status <strong><?= htmlspecialchars($status) ?></strong></p>
      </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Frame</th>
          <th>Judul & Tipe</th>
          <th>Kreator</th>
          <th>Status</th>
          <th>Tanggal Submit</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($submissions as $sub): ?>
        <tr>
          <td>
            <?php
            $imgUrl = rtrim($supabaseUrl, '/') . '/storage/v1/object/public/submissions/' . htmlspecialchars($sub['filename'] ?? '');
            ?>
            <img class="thumb" src="<?= $imgUrl ?>" alt="thumb" loading="lazy"
                 onerror="this.src='https://placehold.co/48x60/1A2755/6B5FD0?text=?'">
          </td>
          <td class="td-title">
            <strong><?= htmlspecialchars($sub['frame_title'] ?? '-') ?></strong>
            <span><?= htmlspecialchars(strtoupper($sub['strip_type'] ?? '')) ?> Strip</span>
          </td>
          <td class="td-creator">
            <?= htmlspecialchars($sub['submitter_name'] ?? 'Anonim') ?>
            <span><?= htmlspecialchars($sub['submitter_email'] ?? '') ?></span>
          </td>
          <td><?= badge($sub['status'] ?? 'pending') ?></td>
          <td class="td-date"><?= fmt_date($sub['submitted_at'] ?? '') ?></td>
          <td>
            <a href="/admin/review?id=<?= urlencode($sub['id'] ?? '') ?>" class="btn-review">Review →</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
