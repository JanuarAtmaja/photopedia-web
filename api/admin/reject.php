<?php
// api/admin/reject.php — Reject sebuah frame submission
// POST /api/admin/reject
// Body: { "id": "<uuid>", "reason": "Alasan penolakan" }
// Header: X-Admin-Secret: <ADMIN_SECRET>

use PHPMailer\PHPMailer\PHPMailer;

require_once dirname(__DIR__, 2) . '/config/helpers.php';
require_once dirname(__DIR__, 2) . '/lib/PHPMailer/Exception.php';
require_once dirname(__DIR__, 2) . '/lib/PHPMailer/PHPMailer.php';
require_once dirname(__DIR__, 2) . '/lib/PHPMailer/SMTP.php';

set_cors_headers();

// ── Auth ─────────────────────────────────────────────────────
require_once dirname(__DIR__, 2) . '/config/admin-auth.php';

$adminSecret = env('ADMIN_SECRET');
$provided    = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';

// Cek via session PHP native
admin_session_start();
$sessionOk = !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Cek via secret header (legacy API clients)
$secretOk = $adminSecret && $provided === $adminSecret;

if (!$sessionOk && !$secretOk) {
    respond_json(['error' => 'Unauthorized'], 401);
}

if ($sessionOk && !$secretOk) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $storedToken = $_SESSION['csrf_token'] ?? '';
    if (!$csrfToken || !$storedToken || !hash_equals($storedToken, $csrfToken)) {
        respond_json(['error' => 'Token CSRF tidak valid. Muat ulang halaman admin dan coba lagi.'], 403);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(['error' => 'Method not allowed'], 405);
}

$supabaseUrl    = rtrim(env('SUPABASE_URL'), '/');
$serviceRoleKey = trim(env('SUPABASE_SERVICE_ROLE_KEY'));

$body   = get_json_body();
$submissionId = trim($body['id'] ?? '');
$reason       = trim($body['reason'] ?? '');

if (!$submissionId) respond_json(['error' => 'Parameter id wajib diisi'], 400);
if (!$reason)       respond_json(['error' => 'Alasan penolakan wajib diisi'], 400);

// ── Helper cURL Supabase ─────────────────────────────────────
function supa_r(string $method, string $path, ?array $payload = null): array
{
    global $supabaseUrl, $serviceRoleKey;
    $url = $supabaseUrl . '/rest/v1/' . ltrim($path, '/');
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $serviceRoleKey,
            'apikey: ' . $serviceRoleKey,
            'Content-Type: application/json',
            'Accept: application/json',
            'Prefer: return=representation',
        ],
    ]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => $code >= 200 && $code < 300, 'body' => json_decode($res, true), 'raw' => $res];
}

// ── 1. Ambil submission ───────────────────────────────────────
$res = supa_r('GET', "frame_submissions?id=eq.$submissionId&select=*");
if (!$res['ok'] || empty($res['body'])) {
    respond_json(['error' => 'Submission tidak ditemukan'], 404);
}
$sub = $res['body'][0];

if (($sub['status'] ?? '') !== 'pending') {
    respond_json(['error' => "Submission sudah ber-status '{$sub['status']}'"], 409);
}

$frameTitle     = $sub['frame_title'];
$submitterEmail = $sub['submitter_email'];
$submitterName  = $sub['submitter_name'] ?? 'Kreator';

// ── 2. Update status → rejected ──────────────────────────────
$upd = supa_r('PATCH', "frame_submissions?id=eq.$submissionId", [
    'status'           => 'rejected',
    'rejection_reason' => $reason,
    'reviewed_at'      => date('c'),
]);

if (!$upd['ok']) {
    respond_json(['error' => 'Gagal update status', 'detail' => $upd['raw']], 502);
}

// ── 3. Kirim email notifikasi ─────────────────────────────────
if ($submitterEmail) {
    try {
        $resendApiKey = env('RESEND_API_KEY');
        $resendFrom   = env('RESEND_FROM', 'Photopedia <onboarding@resend.dev>');

        if ($resendApiKey) {
            $htmlBody = "
            <div style='font-family:sans-serif;max-width:600px;margin:auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #eee'>
              <div style='background:#0F1B3D;padding:32px;text-align:center'>
                <h1 style='color:#FF2D78;margin:0;font-size:24px'>📋 Hasil Review Frame</h1>
              </div>
              <div style='padding:32px'>
                <p style='font-size:16px'>Hei <strong>$submitterName</strong>,</p>
                <p>Terima kasih sudah mengirimkan frame <strong>\"$frameTitle\"</strong>.</p>
                <p>Sayangnya, frame kamu <strong>belum lolos review</strong> dengan alasan berikut:</p>
                <div style='background:#FFF3F5;border-left:4px solid #FF2D78;padding:16px;border-radius:4px;margin:16px 0'>
                  <p style='margin:0;font-weight:600;color:#7B1E1E'>$reason</p>
                </div>
                <p>Kamu boleh memperbaiki desainnya dan mengirim ulang kapan saja. Kami tunggu kreasi terbaikmu! 💪</p>
                <p style='color:#888;font-size:13px'>Salam,<br>Tim Photopedia</p>
              </div>
            </div>";

            $ch = curl_init('https://api.resend.com/emails');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $resendApiKey,
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'from'    => $resendFrom,
                    'to'      => [$submitterEmail],
                    'subject' => "Frame submission kamu belum lolos review — Photopedia",
                    'html'    => $htmlBody
                ])
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
    } catch (\Throwable $e) {
        error_log('[Reject] Email gagal: ' . $e->getMessage());
    }
}

respond_json([
    'success' => true,
    'message' => "Frame '$frameTitle' ditolak. Email notifikasi dikirim ke $submitterEmail.",
]);
