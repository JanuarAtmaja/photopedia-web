<?php
// api/debug.php — Debug endpoint sementara (HAPUS setelah selesai debug)
require_once dirname(__DIR__) . '/config/helpers.php';
set_cors_headers();

$supabaseUrl    = env('SUPABASE_URL');
$serviceRoleKey = env('SUPABASE_SERVICE_ROLE_KEY');

// 1. Cek tabel photos
$endpoint = rtrim($supabaseUrl, '/') . '/rest/v1/photos?select=count&limit=1';
$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $serviceRoleKey,
        'apikey: ' . $serviceRoleKey,
        'Accept: application/json',
    ],
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 2. Cek bucket storage list
$storageEndpoint = rtrim($supabaseUrl, '/') . '/storage/v1/bucket';
$ch2 = curl_init($storageEndpoint);
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $serviceRoleKey,
        'apikey: ' . $serviceRoleKey,
    ],
]);
$storageResp = curl_exec($ch2);
$storageCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

respond_json([
    'photos_table' => [
        'http_code' => $code,
        'response'  => json_decode($resp, true),
        'table_exists' => $code === 200,
    ],
    'storage_buckets' => [
        'http_code' => $storageCode,
        'response'  => json_decode($storageResp, true),
    ],
    'env_check' => [
        'supabase_url_set'          => !empty($supabaseUrl),
        'service_role_key_set'      => !empty($serviceRoleKey),
        'supabase_url_preview'      => substr($supabaseUrl ?? '', 0, 30) . '...',
    ],
]);
