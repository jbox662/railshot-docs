<?php
/**
 * RailShot TV — YouTube API Key Test
 * POST /api/youtube-test.php  { "apiKey": "AIza..." }
 * Returns { "ok": true } if the key works, or { "ok": false, "error": "..." }
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

railshot_require_login_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    railshot_json_response(['error' => 'Method not allowed'], 405);
}

$body   = railshot_read_json_body();
$apiKey = trim($body['apiKey'] ?? '');

if ($apiKey === '') {
    railshot_json_response(['ok' => false, 'error' => 'No API key provided'], 400);
}

// Make a minimal YouTube API call — just list video categories (cheap, 1 unit)
$url = 'https://www.googleapis.com/youtube/v3/videoCategories?' . http_build_query([
    'part'       => 'snippet',
    'regionCode' => 'US',
    'key'        => $apiKey,
]);

$ctx = stream_context_create([
    'http' => [
        'timeout'       => 8,
        'ignore_errors' => true,
        'header'        => "User-Agent: RailShotTV/1.0\r\n",
    ],
]);

$raw  = @file_get_contents($url, false, $ctx);
$data = $raw ? json_decode($raw, true) : null;

if (!is_array($data)) {
    railshot_json_response(['ok' => false, 'error' => 'Could not reach YouTube API — check server internet access']);
}

if (isset($data['error'])) {
    $msg  = $data['error']['message'] ?? 'YouTube API error';
    $code = (int)($data['error']['code'] ?? 400);
    // Friendly messages for common errors
    if (stripos($msg, 'API key not valid') !== false || $code === 400) {
        $msg = 'API key is invalid. Double-check you copied it correctly from Google Cloud Console.';
    } elseif ($code === 403) {
        $msg = 'API key is valid but YouTube Data API v3 is not enabled. Go to Google Cloud Console → APIs & Services → Library → search "YouTube Data API v3" → Enable.';
    }
    railshot_json_response(['ok' => false, 'error' => $msg]);
}

// Success — key works and YouTube Data API v3 is enabled
railshot_json_response(['ok' => true, 'message' => 'YouTube Data API v3 is working correctly.']);
