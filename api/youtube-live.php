<?php
/**
 * RailShot TV — YouTube Live Video Auto-Discovery
 *
 * Returns the current live video embed URL for a given YouTube Channel ID.
 * Uses YouTube Data API v3 search endpoint to find the active live broadcast.
 *
 * GET /api/youtube-live.php?channelId=UCxH4xyXjhMNDR_fLuirDOtA
 *
 * Response (live):   { "ok": true, "videoId": "CmNHWGEJtJo", "embedUrl": "https://www.youtube.com/embed/CmNHWGEJtJo?autoplay=1&mute=1" }
 * Response (offline): { "ok": false, "error": "No live stream found" }
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

// ── Load API key from config ──────────────────────────────────────────────────
$config = railshot_load_config();
$apiKey = trim($config['youtube']['apiKey'] ?? '');

if ($apiKey === '') {
    railshot_json_response(['ok' => false, 'error' => 'YouTube API key not configured. Set it in the admin panel under Settings.'], 503);
}

// ── Validate channelId param ──────────────────────────────────────────────────
$channelId = trim($_GET['channelId'] ?? '');
if ($channelId === '') {
    railshot_json_response(['ok' => false, 'error' => 'channelId parameter required'], 400);
}
// Basic sanity check — YouTube channel IDs are 24 chars starting with UC
if (!preg_match('/^UC[a-zA-Z0-9_-]{22}$/', $channelId)) {
    railshot_json_response(['ok' => false, 'error' => 'Invalid channelId format'], 400);
}

// ── Check cache (5-minute TTL to avoid hammering the API) ────────────────────
$cacheDir  = RAILSHOT_DATA . DIRECTORY_SEPARATOR . 'yt-cache';
$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'live-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $channelId) . '.json';
$cacheTtl  = 300; // 5 minutes

if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

if (file_exists($cacheFile)) {
    $cached = json_decode(file_get_contents($cacheFile) ?: '', true);
    if (is_array($cached) && isset($cached['ts']) && (time() - (int)$cached['ts']) < $cacheTtl) {
        // Return cached result
        unset($cached['ts']);
        railshot_json_response($cached);
    }
}

// ── Call YouTube Data API v3 ──────────────────────────────────────────────────
$url = 'https://www.googleapis.com/youtube/v3/search?' . http_build_query([
    'part'       => 'id',
    'channelId'  => $channelId,
    'eventType'  => 'live',
    'type'       => 'video',
    'maxResults' => 1,
    'key'        => $apiKey,
]);

$ctx = stream_context_create([
    'http' => [
        'timeout'       => 8,
        'ignore_errors' => true,
        'header'        => "User-Agent: RailShotTV/1.0\r\n",
    ],
    'ssl' => [
        'verify_peer'      => true,
        'verify_peer_name' => true,
    ],
]);

$raw = @file_get_contents($url, false, $ctx);

if ($raw === false) {
    railshot_json_response(['ok' => false, 'error' => 'YouTube API request failed — check server internet access'], 502);
}

$data = json_decode($raw, true);

if (!is_array($data)) {
    railshot_json_response(['ok' => false, 'error' => 'Invalid response from YouTube API'], 502);
}

// Handle API errors (e.g. invalid key, quota exceeded)
if (isset($data['error'])) {
    $errMsg = $data['error']['message'] ?? 'YouTube API error';
    $errCode = (int)($data['error']['code'] ?? 500);
    railshot_json_response(['ok' => false, 'error' => $errMsg], $errCode >= 400 ? $errCode : 500);
}

// Extract video ID from results
$items = $data['items'] ?? [];
if (empty($items)) {
    // No live stream found — cache the "offline" result for 30 seconds only
    // so that clicking Retry quickly picks up a newly started stream
    $offlineResult = ['ok' => false, 'error' => 'No live stream found for this channel'];
    file_put_contents($cacheFile, json_encode(array_merge($offlineResult, ['ts' => time() - 270])));
    railshot_json_response($offlineResult);
}

$videoId = $items[0]['id']['videoId'] ?? '';
if ($videoId === '') {
    railshot_json_response(['ok' => false, 'error' => 'Could not extract video ID from YouTube response'], 502);
}

$embedUrl = 'https://www.youtube.com/embed/' . $videoId . '?autoplay=1&mute=1';

$result = [
    'ok'       => true,
    'videoId'  => $videoId,
    'embedUrl' => $embedUrl,
];

// Cache the successful result
file_put_contents($cacheFile, json_encode(array_merge($result, ['ts' => time()])));

railshot_json_response($result);
