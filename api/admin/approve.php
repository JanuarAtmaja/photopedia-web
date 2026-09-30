<?php
// api/admin/approve.php — Approve sebuah frame submission
// POST /api/admin/approve
// Body: { "id": "<submission_uuid>" }
// Header: X-Admin-Secret: <ADMIN_SECRET>
//
// Alur:
//   1. Ambil data submission dari frame_submissions
//   2. Copy file dari bucket submissions/ ke bucket frames/
//   3. Insert row baru ke tabel frames
//   4. Update frame_submissions.status = 'approved', reviewed_at = now()
//   5. Kirim email notifikasi ke submitter via SMTP

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once dirname(__DIR__, 2) . '/config/helpers.php';
require_once dirname(__DIR__, 2) . '/lib/PHPMailer/Exception.php';
require_once dirname(__DIR__, 2) . '/lib/PHPMailer/PHPMailer.php';
require_once dirname(__DIR__, 2) . '/lib/PHPMailer/SMTP.php';

set_cors_headers();

// ── Auth ─────────────────────────────────────────────────────
$adminSecret = env('ADMIN_SECRET');
$provided    = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
if (!$adminSecret || $provided !== $adminSecret) {
    respond_json(['error' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(['error' => 'Method not allowed'], 405);
}

// ── Config ───────────────────────────────────────────────────
$supabaseUrl    = rtrim(env('SUPABASE_URL'), '/');
$serviceRoleKey = trim(env('SUPABASE_SERVICE_ROLE_KEY'));

if (!$supabaseUrl || !$serviceRoleKey) {
    respond_json(['error' => 'Supabase not configured'], 500);
}

$body = get_json_body();
$submissionId = trim($body['id'] ?? '');
if (!$submissionId) {
    respond_json(['error' => 'Parameter id wajib diisi'], 400);
}

// ── Helper: Supabase REST ────────────────────────────────────
function supa(string $method, string $path, ?array $payload = null, array $extra = []): array
{
    global $supabaseUrl, $serviceRoleKey;
    $url = $supabaseUrl . '/rest/v1/' . ltrim($path, '/');
    $headers = array_merge([
        'Authorization: Bearer ' . $serviceRoleKey,
        'apikey: ' . $serviceRoleKey,
        'Content-Type: application/json',
        'Accept: application/json',
        'Prefer: return=representation',
    ], $extra);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) return ['ok' => false, 'error' => $err, 'body' => null, 'raw' => ''];
    return ['ok' => $code >= 200 && $code < 300, 'status' => $code, 'body' => json_decode($response, true), 'raw' => $response];
}

// ── 1. Ambil data submission ──────────────────────────────────
$res = supa('GET', "frame_submissions?id=eq.$submissionId&select=*");
if (!$res['ok'] || empty($res['body'])) {
    respond_json(['error' => 'Submission tidak ditemukan', 'detail' => $res['raw']], 404);
}

$sub = $res['body'][0];

// Validasi: hanya proses yang masih pending
if (($sub['status'] ?? '') !== 'pending') {
    respond_json(['error' => "Submission sudah ber-status '{$sub['status']}'"], 409);
}

$filename        = $sub['filename'];       // e.g. frame-abc.png
$frameTitle      = $sub['frame_title'];
$submitterEmail  = $sub['submitter_email'];
$submitterName   = $sub['submitter_name'] ?? 'Kreator';
$slots           = $sub['slots'] ?? [];
$width           = (int) ($sub['width']  ?? 1080);
$height          = (int) ($sub['height'] ?? 1920);
$stripType       = $sub['strip_type'] ?? '';

// ── 2. Copy file submissions/ → frames/ bucket ───────────────
// Download dari submissions bucket
$downloadUrl = $supabaseUrl . '/storage/v1/object/submissions/' . $filename;
$ch = curl_init($downloadUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $serviceRoleKey,
        'apikey: ' . $serviceRoleKey,
    ],
]);
$fileContent = curl_exec($ch);
$dlCode      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($dlCode < 200 || $dlCode >= 300 || !$fileContent) {
    respond_json(['error' => 'Gagal download file dari submissions bucket', 'http' => $dlCode], 502);
}

// Upload ke frames bucket
$uploadUrl = $supabaseUrl . '/storage/v1/object/frames/' . $filename;
$ch = curl_init($uploadUrl);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_POSTFIELDS     => $fileContent,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $serviceRoleKey,
        'apikey: ' . $serviceRoleKey,
        'Content-Type: image/png',
        'Content-Length: ' . strlen($fileContent),
        'x-upsert: true',
    ],
]);
$upResponse = curl_exec($ch);
$upCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($upCode < 200 || $upCode >= 300) {
    respond_json(['error' => 'Gagal upload ke frames bucket', 'detail' => $upResponse], 502);
}

// ── 3. Insert ke tabel frames ────────────────────────────────
$frameId = preg_replace('/[^a-z0-9\-_]/', '', strtolower(pathinfo($filename, PATHINFO_FILENAME)));
if (is_string($slots)) $slots = json_decode($slots, true) ?? [];

$insertRes = supa('POST', 'frames', [
    'id'         => $frameId,
    'label'      => $frameTitle,
    'filename'   => $filename,
    'slots'      => $slots,
    'width'      => $width,
    'height'     => $height,
    'is_active'  => true,
    'sort_order' => 0,
], ['Prefer: resolution=merge-duplicates,return=representation']);

if (!$insertRes['ok']) {
    respond_json(['error' => 'Gagal insert ke tabel frames', 'detail' => $insertRes['raw']], 502);
}

// ── 4. Update status submission → approved ───────────────────
$updateRes = supa('PATCH', "frame_submissions?id=eq.$submissionId", [
    'status'      => 'approved',
    'reviewed_at' => date('c'),
]);

if (!$updateRes['ok']) {
    respond_json(['error' => 'Gagal update status submission', 'detail' => $updateRes['raw']], 502);
}

// ── 5. Kirim email notifikasi ─────────────────────────────────
if ($submitterEmail) {
    try {
        $resendApiKey = env('RESEND_API_KEY');
        $resendFrom   = env('RESEND_FROM', 'Photopedia <onboarding@resend.dev>');

        if ($resendApiKey) {
            $htmlBody = "
            <div style='font-family:sans-serif;max-width:600px;margin:auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #eee'>
              <div style='background:#0F1B3D;padding:32px;text-align:center'>
                <h1 style='color:#D4F04A;margin:0;font-size:24px'>✅ Frame Disetujui!</h1>
              </div>
              <div style='padding:32px'>
                <p style='font-size:16px'>Hei <strong>$submitterName</strong>! 🎉</p>
                <p>Frame kamu <strong>\"$frameTitle\"</strong> sudah di-approve dan sekarang <strong>bisa dipakai</strong> oleh semua pengguna Photopedia!</p>
                <div style='text-align:center;margin:24px 0'>
                  <a href='https://www.photopedia.net' style='background:#0F1B3D;color:#fff;padding:12px 32px;border-radius:50px;text-decoration:none;font-weight:700'>Cek di Photopedia →</a>
                </div>
                <p style='color:#888;font-size:13px'>Terima kasih sudah berkontribusi! 💙</p>
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
                    'subject' => "🎉 Frame kamu sudah di-approve! — Photopedia",
                    'html'    => $htmlBody
                ])
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
    } catch (\Throwable $e) {
        error_log('[Approve] Email gagal: ' . $e->getMessage());
        // Non-fatal — lanjut meski email gagal
    }
}

respond_json([
    'success' => true,
    'message' => "Frame '$frameTitle' berhasil di-approve dan dipublikasikan.",
    'frame_id' => $frameId,
]);
