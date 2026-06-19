<?php
require_once dirname(__DIR__) . '/config/helpers.php';
$supabaseUrl = env('SUPABASE_URL');
$serviceRoleKey = env('SUPABASE_SERVICE_ROLE_KEY');

// 1. Get one photo to test
$endpoint1 = rtrim($supabaseUrl, '/') . '/rest/v1/photos?select=id,hidden&limit=1';
$ch = curl_init($endpoint1);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $serviceRoleKey,
        'apikey: ' . $serviceRoleKey,
        'Accept: application/json'
    ]
]);
$resp1 = curl_exec($ch);
$rows = json_decode($resp1, true);
curl_close($ch);

if (empty($rows)) {
    die("No photos found");
}

$id = $rows[0]['id'];
echo "Testing with ID: $id\n";

// 2. Try to update it
$endpoint2 = rtrim($supabaseUrl, '/') . '/rest/v1/photos?id=eq.' . urlencode($id);
$payload = json_encode(['hidden' => true]);

$ch2 = curl_init($endpoint2);
curl_setopt_array($ch2, [
    CURLOPT_CUSTOMREQUEST => 'PATCH',
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $serviceRoleKey,
        'apikey: ' . $serviceRoleKey,
        'Content-Type: application/json',
        'Prefer: return=representation' // Return the updated row so we can see it
    ]
]);
$resp2 = curl_exec($ch2);
$httpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "HTTP Code: $httpCode\n";
echo "Response: $resp2\n";

// 3. Revert
$payloadRev = json_encode(['hidden' => false]);
$ch3 = curl_init($endpoint2);
curl_setopt_array($ch3, [
    CURLOPT_CUSTOMREQUEST => 'PATCH',
    CURLOPT_POSTFIELDS => $payloadRev,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $serviceRoleKey,
        'apikey: ' . $serviceRoleKey,
        'Content-Type: application/json'
    ]
]);
curl_exec($ch3);
curl_close($ch3);
