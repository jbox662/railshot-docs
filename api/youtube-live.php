<?php
/**
 * RailShot TV — YouTube Live Video Auto-Discovery
 *
 * Primary method: scrape youtube.com/channel/{id}/live — works for Public, Unlisted, and Private streams.
 * Fallback: YouTube Data API v3 search (Public only).
 *
 * GET /api/youtube-live.php?channelId=UCxH4xyXjhMNDR_fLuirDOtA
 *
 * Response (live):    { "ok": true, "videoId": "ABC123", "embedUrl": "https://www.youtube.com/embed/ABC123?autoplay=1&mute=1" }
 * Response (offline): { "ok": false, "error": "No live stream found" }
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

// ── Load API key from config ──────────────────────────────────────────────────
$config = railshot_load_config();
$apiKey = trim($config['youtube']['apiKey'] ?? '');

// ── Validate channelId param ──────────────────────────────────────────────────
$channelId = trim($_GET['channelId'] ?? '');
if ($channelId === '') {
    railshot_json_response(['ok' => false, 'error' => 'channelId parameter required'], 400);
}
if (!preg_match('/^UC[a-zA-Z0-9_-]{22}$/', $channelId)) {
    railshot_json_response(['ok' => false, 'error' => 'Invalid channelId format'], 400);
}

// ── Cache setup (30s TTL for offline, 5min for live) ─────────────────────────
$cacheDir  = RAILSHOT_DATA . DIRECTORY_SEPARATOR . 'yt-cache';
$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'live-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $channelId) . '.json';
$liveTtl   = 300; // 5 minutes when live
$offlineTtl = 30; // 30 seconds when offline

if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

if (file_exists($cacheFile)) {
    $cached = json_decode(file_get_contents($cacheFile) ?: '', true);
    if (is_array($cached) && isset($cached['ts'])) {
        $ttl = ($cached['ok'] ?? false) ? $liveTtl : $offlineTtl;
        if ((time() - (int)$cached['ts']) < $ttl) {
            $out = $cached;
            unset($out['ts']);
            railshot_json_response($out);
        }
    }
}

// ── Method 1: Scrape youtube.com/channel/{id}/live ───────────────────────────
// This works for Public AND Unlisted streams — no API key needed.
$videoId = scrapeChannelLivePage($channelId);

// ── Method 2: YouTube Data API v3 search (Public only, fallback) ─────────────
if (!$videoId && $apiKey !== '') {
    $videoId = apiSearchLive($channelId, $apiKey);
}

// ── Build response ────────────────────────────────────────────────────────────
if ($videoId) {
    $embedUrl = 'https://www.youtube.com/embed/' . $videoId . '?autoplay=1&mute=1&controls=0&modestbranding=1&rel=0&playsinline=1';
    $result = [
        'ok'       => true,
        'videoId'  => $videoId,
        'embedUrl' => $embedUrl,
    ];
    file_put_contents($cacheFile, json_encode(array_merge($result, ['ts' => time()])));
    railshot_json_response($result);
}

$offline = ['ok' => false, 'error' => 'No live stream found for this channel'];
file_put_contents($cacheFile, json_encode(array_merge($offline, ['ts' => time()])));
railshot_json_response($offline);


// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Scrape the /live page of a YouTube channel to extract the current live video ID.
 * Works for Public and Unlisted streams. Returns empty string if not live.
 */
function scrapeChannelLivePage(string $channelId): string
{
    $urls = [
        'https://www.youtube.com/channel/' . $channelId . '/live',
        'https://www.youtube.com/@' . $channelId . '/live', // handle-based (fallback)
    ];

    $ctx = stream_context_create([
        'http' => [
            'timeout'       => 10,
            'ignore_errors' => true,
            'header'        => implode("\r\n", [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept-Language: en-US,en;q=0.9',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ]) . "\r\n",
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);

    foreach ($urls as $url) {
        $html = @file_get_contents($url, false, $ctx);
        if (!$html) continue;

        // Only return a video ID if the page indicates an active live stream.
        // Check for canonical live indicators in the page data.
        $isLive = (
            strpos($html, '"isLive":true') !== false ||
            strpos($html, '"liveBroadcastContent":"live"') !== false ||
            strpos($html, 'isLiveBroadcast') !== false
        );

        if (!$isLive) continue;

        // Extract videoId — appears multiple times, grab the first one
        if (preg_match('/"videoId"\s*:\s*"([a-zA-Z0-9_-]{11})"/', $html, $m)) {
            return $m[1];
        }
    }

    return '';
}

/**
 * Use YouTube Data API v3 search to find a live video for the channel.
 * Only finds Public streams.
 */
function apiSearchLive(string $channelId, string $apiKey): string
{
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
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);

    $raw  = @file_get_contents($url, false, $ctx);
    $data = $raw ? json_decode($raw, true) : null;

    if (!is_array($data) || isset($data['error']) || empty($data['items'])) {
        return '';
    }

    return $data['items'][0]['id']['videoId'] ?? '';
}
