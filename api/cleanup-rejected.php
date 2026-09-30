<?php
// api/cleanup-rejected.php — Cron job cleanup
// GET /api/cleanup-rejected
// Dijalankan via Vercel Cron Jobs tiap 1 jam
// Menghapus data frame_submissions (status = 'rejected') yang 'reviewed_at'-nya lebih dari 12 jam lalu,
// beserta file gambar yang ada di bucket `submissions`.

require_once dirname(__DIR__) . '/config/helpers.php';

// Auth: Vercel Cron mengirimkan bearer token spesifik, 
// tapi untuk sederhananya kita bisa verifikasi header khusus atau menggunakan Cron Secret dari Vercel
$cronSecret = env('CRON_SECRET'); 
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if ($cronSecret && $authHeader !== "Bearer $cronSecret") {
    // Hanya cek jika CRON_SECRET ter-set di environment variables (best practice)
    respond_json(['error' => 'Unauthorized'], 401);
}

$supabaseUrl    = rtrim(env('SUPABASE_URL'), '/');
$serviceRoleKey = trim(env('SUPABASE_SERVICE_ROLE_KEY'));

if (!$supabaseUrl || !$serviceRoleKey) {
    respond_json(['error' => 'Supabase not configured'], 500);
}

function supabase_curl($method, $path, $payload = null) {
    global $supabaseUrl, $serviceRoleKey;
    $url = $supabaseUrl . '/rest/v1/' . ltrim($path, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $serviceRoleKey,
            'apikey: ' . $serviceRoleKey,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ],
    ]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => $code >= 200 && $code < 300, 'body' => json_decode($res, true), 'code' => $code];
}

// 1. Ambil data yang expired (rejected, reviewed_at < 12 jam lalu)
// Menggunakan PostgREST filter: status=eq.rejected & reviewed_at=lt.<timestamp>
$twelveHoursAgo = date('c', strtotime('-12 hours'));
$queryPath = "frame_submissions?status=eq.rejected&reviewed_at=lt." . urlencode($twelveHoursAgo) . "&select=id,filename";

$res = supabase_curl('GET', $queryPath);
if (!$res['ok']) {
    respond_json(['error' => 'Gagal query data', 'detail' => $res], 502);
}

$expiredSubmissions = $res['body'] ?? [];
if (empty($expiredSubmissions)) {
    respond_json(['success' => true, 'message' => 'Tidak ada data expired yang perlu dibersihkan', 'deleted' => 0]);
}

$deletedCount = 0;
$errors = [];

foreach ($expiredSubmissions as $sub) {
    $id = $sub['id'];
    $filename = $sub['filename'];

    // 2. Hapus file di storage bucket 'submissions'
    $delUrl = $supabaseUrl . '/storage/v1/object/submissions/' . urlencode($filename);
    $ch = curl_init($delUrl);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $serviceRoleKey,
            'apikey: ' . $serviceRoleKey,
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);
    // Kita abaikan jika file tidak ditemukan di storage (mungkin sudah terhapus)

    // 3. Hapus row di database
    $delDbRes = supabase_curl('DELETE', "frame_submissions?id=eq.$id");
    if (!$delDbRes['ok']) {
        $errors[] = "Gagal hapus row ID $id";
    } else {
        $deletedCount++;
    }
}

respond_json([
    'success' => true,
    'message' => "Pembersihan selesai",
    'deleted' => $deletedCount,
    'errors'  => $errors
]);
