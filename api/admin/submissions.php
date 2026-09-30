<?php
// api/admin/submissions.php — List frame_submissions
// GET /api/admin/submissions?status=pending|approved|rejected|all
require_once dirname(__DIR__, 2) . '/config/helpers.php';

set_cors_headers();

$adminSecret = env('ADMIN_SECRET');
$provided    = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
if (!$adminSecret || $provided !== $adminSecret) {
    respond_json(['error' => 'Unauthorized'], 401);
}

$supabaseUrl    = env('SUPABASE_URL');
$serviceRoleKey = trim(env('SUPABASE_SERVICE_ROLE_KEY'));

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_json(['error' => 'Method not allowed'], 405);
}

$status = $_GET['status'] ?? 'pending';
$filter = ($status === 'all') ? '' : "&status=eq.$status";

$url = rtrim($supabaseUrl, '/') . '/rest/v1/frame_submissions'
     . '?select=*'
     . $filter
     . '&order=submitted_at.desc';

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
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode < 200 || $httpCode >= 300) {
    respond_json(['error' => 'Gagal ambil data', 'detail' => $response], 502);
}

$rows = json_decode($response, true) ?? [];
respond_json(['success' => true, 'submissions' => $rows, 'count' => count($rows)]);
