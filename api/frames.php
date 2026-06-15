<?php
// api/frames.php — Endpoint daftar frame (JSON)
// GET /api/frames

require_once dirname(__DIR__) . '/config/helpers.php';

set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_json(['error' => 'Method not allowed'], 405);
}

$frames = get_frames();
respond_json(['frames' => $frames, 'count' => count($frames)]);
