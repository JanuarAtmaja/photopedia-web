<?php
// public/admin/review.php — Detail review satu submission + tombol Approve/Reject
require_once dirname(__DIR__, 2) . '/config/admin-auth.php';

require_admin_auth();

$id = trim($_GET['id'] ?? '');
if (!$id) {
    header('Location: /admin');
    exit;
}

$token = csrf_token();

// Fetch submission dari Supabase
$supabaseUrl    = rtrim(env('SUPABASE_URL'), '/');
$serviceRoleKey = trim(env('SUPABASE_SERVICE_ROLE_KEY'));

$sub      = null;
$fetchErr = '';

if ($supabaseUrl && $serviceRoleKey) {
    $url = $supabaseUrl . '/rest/v1/frame_submissions?id=eq.' . urlencode($id) . '&select=*';
    $ch  = curl_init($url);
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
        $rows = json_decode($res, true) ?? [];
        $sub  = $rows[0] ?? null;
    } else {
        $fetchErr = 'Gagal memuat data (HTTP ' . $code . ')';
    }
} else {
    $fetchErr = 'Supabase belum dikonfigurasi.';
}

if (!$sub && !$fetchErr) {
    $fetchErr = 'Submission tidak ditemukan.';
}

// Hitung estimasi cm (300 DPI)
$widthCm  = $sub ? round(($sub['width_px']  ?? 0) / 300 * 2.54, 2) : 0;
$heightCm = $sub ? round(($sub['height_px'] ?? 0) / 300 * 2.54, 2) : 0;

$imgUrl = $supabaseUrl . '/storage/v1/object/public/submissions/' . htmlspecialchars($sub['filename'] ?? '');

function badge_review(string $s): string {
    $map = [
        'pending'  => ['#FEF3C7', '#92400E'],
        'approved' => ['#D1FAE5', '#065F46'],
        'rejected' => ['#FEE2E2', '#991B1B'],
    ];
    [$bg, $col] = $map[$s] ?? ['#E5E7EB', '#374151'];
    return "<span style=\"background:$bg;color:$col;padding:6px 14px;border-radius:999px;font-size:13px;font-weight:600\">" . strtoupper(htmlspecialchars($s)) . "</span>";
}
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Review Frame — Photopedia Admin</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --navy:#0F1B3D; --surface:#1A2755; --surface-2:#1F2F66;
      --border:rgba(255,255,255,0.10); --purple:#4B3FA0; --purple-lt:#6B5FD0;
      --lime:#D4F04A; --pink:#FF2D78; --green:#10B981;
      --text:#E8E8FF; --text-muted:rgba(232,232,255,0.55);
    }
    body { font-family:'Inter',sans-serif; background:var(--navy); color:var(--text); min-height:100vh; }

    .admin-header {
      background:rgba(15,27,61,0.90); backdrop-filter:blur(12px);
      border-bottom:1px solid var(--border); position:sticky; top:0; z-index:10; padding:0 24px;
    }
    .header-inner {
      max-width:1100px; margin:0 auto; display:flex; align-items:center;
      justify-content:space-between; height:64px;
    }
    .header-brand { display:flex; align-items:center; gap:12px; }
    .header-brand img { height:28px; }
    .header-badge {
      background:rgba(212,240,74,0.15); color:var(--lime);
      border:1px solid rgba(212,240,74,0.30); border-radius:50px;
      font-size:11px; font-weight:700; padding:3px 10px; letter-spacing:0.5px; text-transform:uppercase;
    }
    .btn-back {
      display:inline-flex; align-items:center; gap:6px;
      padding:8px 16px; border-radius:50px;
      background:var(--surface); border:1px solid var(--border);
      color:var(--text); text-decoration:none; font-size:14px; font-weight:500;
      transition:background 0.2s;
    }
    .btn-back:hover { background:var(--surface-2); }

    .page-wrap { max-width:1000px; margin:0 auto; padding:40px 24px 80px; }

    .breadcrumb { font-size:13px; color:var(--text-muted); margin-bottom:24px; }
    .breadcrumb a { color:var(--purple-lt); text-decoration:none; }
    .breadcrumb a:hover { text-decoration:underline; }

    .review-grid { display:grid; grid-template-columns:280px 1fr; gap:28px; align-items:start; }
    @media(max-width:680px) { .review-grid { grid-template-columns:1fr; } }

    /* Preview */
    .preview-card {
      background:var(--surface); border:1px solid var(--border); border-radius:16px;
      overflow:hidden; position:sticky; top:80px;
    }
    .preview-card img {
      display:block; width:100%; max-height:500px; object-fit:contain;
      background:#000;
    }
    .preview-meta {
      padding:16px;
      font-size:13px; color:var(--text-muted); text-align:center;
      border-top:1px solid var(--border);
    }
    .preview-meta strong { color:var(--text); display:block; font-size:15px; margin-bottom:4px; }

    /* Info */
    .info-card {
      background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:28px;
    }
    .info-card h1 { font-size:22px; font-weight:800; margin-bottom:8px; }

    .detail-row { margin-bottom:18px; }
    .d-label { font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); margin-bottom:6px; font-weight:600; }
    .d-value { font-size:15px; font-weight:500; }

    .divider { border:none; border-top:1px solid var(--border); margin:24px 0; }

    /* Rejection reason */
    .reject-box {
      background:rgba(255,45,120,0.10); border:1px solid rgba(255,45,120,0.30);
      border-radius:12px; padding:16px; margin-top:12px; font-size:14px;
    }
    .reject-box .rej-label { font-size:12px; font-weight:600; color:#FCA5A5; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; }
    .reject-box .rej-text  { color:var(--text); line-height:1.6; }

    /* Actions */
    .actions { display:flex; gap:12px; flex-wrap:wrap; margin-top:28px; }
    .btn-approve, .btn-reject {
      padding:12px 24px; border-radius:50px; font-size:15px; font-weight:700;
      border:none; cursor:pointer; transition:opacity 0.2s;
    }
    .btn-approve { background:var(--green); color:#fff; box-shadow:0 4px 20px rgba(16,185,129,0.35); }
    .btn-reject  { background:var(--pink);  color:#fff; box-shadow:0 4px 20px rgba(255,45,120,0.30); }
    .btn-approve:hover, .btn-reject:hover { opacity:0.85; }
    .btn-approve:disabled, .btn-reject:disabled { opacity:0.50; cursor:not-allowed; }

    /* Reject form */
    #reject-form { display:none; margin-top:24px; }
    .reject-form-inner {
      background:rgba(255,45,120,0.08); border:1px solid rgba(255,45,120,0.25);
      border-radius:12px; padding:20px;
    }
    .reject-form-inner label { display:block; font-size:14px; font-weight:600; margin-bottom:10px; }
    .reject-form-inner textarea {
      width:100%; background:rgba(255,255,255,0.06); border:1px solid rgba(255,45,120,0.30);
      border-radius:10px; padding:12px; font-size:14px; font-family:inherit;
      color:var(--text); outline:none; resize:vertical; min-height:90px;
    }
    .reject-form-inner textarea:focus { border-color:var(--pink); }
    .reject-form-inner textarea::placeholder { color:var(--text-muted); }
    .reject-btns { display:flex; gap:10px; margin-top:12px; }
    .btn-cancel {
      padding:10px 20px; border-radius:50px; background:var(--surface-2);
      border:1px solid var(--border); color:var(--text);
      font-size:14px; font-weight:600; cursor:pointer; transition:background 0.2s;
    }
    .btn-cancel:hover { background:rgba(255,255,255,0.10); }
    .btn-submit-reject {
      padding:10px 20px; border-radius:50px;
      background:var(--pink); color:#fff;
      font-size:14px; font-weight:700; border:none; cursor:pointer; transition:opacity 0.2s;
    }
    .btn-submit-reject:hover { opacity:0.85; }
    .btn-submit-reject:disabled { opacity:0.50; cursor:not-allowed; }

    /* Toast */
    #toast {
      position:fixed; bottom:28px; right:28px; z-index:999;
      padding:14px 20px; border-radius:12px; font-size:14px; font-weight:600;
      box-shadow:0 8px 32px rgba(0,0,0,0.40); display:none;
      animation:slideIn 0.3s ease;
    }
    #toast.success { background:#10B981; color:#fff; }
    #toast.error   { background:var(--pink); color:#fff; }
    @keyframes slideIn { from { transform:translateY(20px); opacity:0; } to { transform:none; opacity:1; } }

    .alert-error {
      background:rgba(255,45,120,0.12); border:1px solid rgba(255,45,120,0.35);
      border-radius:12px; padding:16px 20px; color:#FCA5A5; font-size:14px;
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
    <a href="/admin" class="btn-back">← Dashboard</a>
  </div>
</header>

<div class="page-wrap">

  <div class="breadcrumb">
    <a href="/admin">Dashboard</a> › Review Frame
  </div>

  <?php if ($fetchErr): ?>
    <div class="alert-error">⚠️ <?= htmlspecialchars($fetchErr) ?></div>
  <?php elseif ($sub): ?>

  <div class="review-grid">

    <!-- Preview -->
    <div class="preview-card">
      <img src="<?= $imgUrl ?>" alt="Frame preview"
           onerror="this.src='https://placehold.co/280x400/1A2755/6B5FD0?text=No+Image'">
      <div class="preview-meta">
        <strong><?= htmlspecialchars($sub['frame_title'] ?? '-') ?></strong>
        <?= ($sub['width_px'] ?? 0) ?> × <?= ($sub['height_px'] ?? 0) ?> px<br>
        ≈ <?= $widthCm ?>cm × <?= $heightCm ?>cm (300 DPI)
      </div>
    </div>

    <!-- Info + Actions -->
    <div class="info-card">
      <div><?= badge_review($sub['status'] ?? 'pending') ?></div>
      <h1 style="margin-top:14px"><?= htmlspecialchars($sub['frame_title'] ?? '-') ?></h1>

      <div class="detail-row" style="margin-top:20px">
        <div class="d-label">Tipe Strip</div>
        <div class="d-value"><?= htmlspecialchars(strtoupper($sub['strip_type'] ?? '-')) ?> Strip</div>
      </div>

      <div class="detail-row">
        <div class="d-label">Kreator</div>
        <div class="d-value">
          <?= htmlspecialchars($sub['submitter_name'] ?: 'Anonim') ?><br>
          <span style="font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($sub['submitter_email'] ?? '') ?></span>
        </div>
      </div>

      <div class="detail-row">
        <div class="d-label">Dimensi File</div>
        <div class="d-value"><?= ($sub['width_px'] ?? 0) ?> × <?= ($sub['height_px'] ?? 0) ?> px &nbsp;(≈ <?= $widthCm ?> × <?= $heightCm ?> cm)</div>
      </div>

      <div class="detail-row">
        <div class="d-label">Tanggal Submit</div>
        <div class="d-value"><?= date('d M Y, H:i', strtotime($sub['submitted_at'] ?? 'now')) ?></div>
      </div>

      <?php if (!empty($sub['reviewed_at'])): ?>
      <div class="detail-row">
        <div class="d-label">Tanggal Review</div>
        <div class="d-value"><?= date('d M Y, H:i', strtotime($sub['reviewed_at'])) ?></div>
      </div>
      <?php endif; ?>

      <?php if (!empty($sub['rejection_reason'])): ?>
      <div class="reject-box">
        <div class="rej-label">Alasan Penolakan</div>
        <div class="rej-text"><?= htmlspecialchars($sub['rejection_reason']) ?></div>
      </div>
      <?php endif; ?>

      <hr class="divider">

      <?php if (($sub['status'] ?? '') === 'pending'): ?>
      <!-- Actions hanya tampil untuk pending -->
      <div class="actions">
        <button class="btn-approve" id="btn-approve" onclick="doApprove()">
          ✅ Approve & Publikasikan
        </button>
        <button class="btn-reject" id="btn-reject-open" onclick="openReject()">
          ❌ Reject
        </button>
      </div>

      <div id="reject-form">
        <div class="reject-form-inner">
          <label for="reject-reason">Alasan Penolakan <span style="color:var(--pink)">*</span></label>
          <textarea id="reject-reason"
                    placeholder="cth: Ukuran tidak sesuai, resolusi terlalu rendah, desain melanggar panduan..."></textarea>
          <div class="reject-btns">
            <button class="btn-submit-reject" id="btn-submit-reject" onclick="doReject()">Kirim Penolakan</button>
            <button class="btn-cancel" onclick="closeReject()">Batal</button>
          </div>
        </div>
      </div>

      <?php else: ?>
      <div style="padding:16px;background:var(--surface-2);border-radius:10px;font-size:14px;color:var(--text-muted)">
        Submission ini sudah diproses dengan status <strong style="color:var(--text)"><?= strtoupper(htmlspecialchars($sub['status'])) ?></strong>.
      </div>
      <?php endif; ?>

    </div>
  </div>

  <?php endif; ?>
</div>

<div id="toast"></div>

<script>
const SUB_ID = <?= json_encode($id) ?>;

function showToast(type, msg) {
  const t = document.getElementById('toast');
  t.className = type;
  t.textContent = msg;
  t.style.display = 'block';
  setTimeout(() => { t.style.display = 'none'; }, 4000);
}

async function doApprove() {
  if (!confirm('Approve frame ini dan publikasikan ke semua pengguna?')) return;

  const btn = document.getElementById('btn-approve');
  const btnReject = document.getElementById('btn-reject-open');
  btn.disabled = true;
  if (btnReject) btnReject.disabled = true;
  btn.textContent = '⏳ Memproses…';

  try {
    const res  = await fetch('/api/admin/approve', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Admin-Secret': '' /* dihandle session */ },
      body: JSON.stringify({ id: SUB_ID })
    });

    // Coba dengan CSRF dulu, fallback ke X-Admin-Secret
    // Untuk kompatibilitas: kirim via form POST jika perlu
    const data = await res.json();

    if (data.success) {
      showToast('success', '✅ Frame berhasil di-approve!');
      setTimeout(() => { window.location.href = '/admin'; }, 1800);
    } else {
      showToast('error', '❌ ' + (data.error || 'Gagal approve'));
      btn.disabled = false;
      if (btnReject) btnReject.disabled = false;
      btn.textContent = '✅ Approve & Publikasikan';
    }
  } catch (err) {
    showToast('error', '❌ Koneksi gagal: ' + err.message);
    btn.disabled = false;
    if (btnReject) btnReject.disabled = false;
    btn.textContent = '✅ Approve & Publikasikan';
  }
}

function openReject() {
  document.getElementById('reject-form').style.display = 'block';
  document.getElementById('reject-reason').focus();
}

function closeReject() {
  document.getElementById('reject-form').style.display = 'none';
}

async function doReject() {
  const reason = document.getElementById('reject-reason').value.trim();
  if (!reason) { showToast('error', 'Alasan penolakan wajib diisi.'); return; }

  const btn = document.getElementById('btn-submit-reject');
  btn.disabled = true;
  btn.textContent = '⏳ Mengirim…';

  try {
    const res  = await fetch('/api/admin/reject', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Admin-Secret': '' },
      body: JSON.stringify({ id: SUB_ID, reason })
    });
    const data = await res.json();

    if (data.success) {
      showToast('success', '✅ Frame ditolak. Email notifikasi terkirim.');
      setTimeout(() => { window.location.href = '/admin'; }, 1800);
    } else {
      showToast('error', '❌ ' + (data.error || 'Gagal reject'));
      btn.disabled = false;
      btn.textContent = 'Kirim Penolakan';
    }
  } catch (err) {
    showToast('error', '❌ Koneksi gagal: ' + err.message);
    btn.disabled = false;
    btn.textContent = 'Kirim Penolakan';
  }
}
</script>

</body>
</html>
