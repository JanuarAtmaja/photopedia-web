<?php
// api/gallery.php — Endpoint for fetching gallery images from Supabase Storage
// GET /api/gallery

require_once dirname(__DIR__) . '/config/helpers.php';

set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_json(['error' => 'Method not allowed'], 405);
}

$supabaseUrl = env('SUPABASE_URL');
$supabaseAnonKey = env('SUPABASE_ANON_KEY');
$bucket = env('SUPABASE_BUCKET', 'photopedia-photos');

if (!$supabaseUrl || !$supabaseAnonKey) {
    respond_json(['error' => 'Supabase not configured'], 500);
}

// Prefix to search for. For example, photos are in 'sessions/'
$folder = 'sessions';

// Supabase REST API for Storage: POST /storage/v1/object/list/[bucket_name]
$endpoint = rtrim($supabaseUrl, '/') . '/storage/v1/object/list/' . urlencode($bucket);

$payload = json_encode([
    'prefix' => $folder,
    'limit'  => 50,
    'offset' => 0,
    'sortBy' => [
        'column' => 'created_at',
        'order'  => 'desc'
    ]
]);

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $supabaseAnonKey,
        'apikey: ' . $supabaseAnonKey,
        'Content-Type: application/json'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 300) {
    $files = json_decode($response, true);
    $images = [];
    $publicUrlBase = rtrim($supabaseUrl, '/') . '/storage/v1/object/public/' . urlencode($bucket) . '/';
    
    if (is_array($files)) {
        foreach ($files as $file) {
            // Check if it's a file, not a folder metadata, and ends with .jpg/.png
            if (isset($file['name']) && !empty($file['id']) && preg_match('/\.(jpg|jpeg|png)$/i', $file['name'])) {
                // Supabase list API returns name without prefix
                // Actually, if prefix is 'sessions', the name might be 'YYYY/MM/DD/file.jpg'
                $images[] = [
                    'id' => $file['id'],
                    'name' => $file['name'],
                    'url' => $publicUrlBase . $folder . '/' . ltrim($file['name'], '/'),
                    'created_at' => $file['created_at']
                ];
            }
        }
    }
    
    respond_json(['images' => $images, 'count' => count($images)]);
} else {
    respond_json(['error' => 'Failed to fetch from storage', 'details' => json_decode($response, true)], 502);
}
