<?php
/**
 * RailShot TV — Camera Streams Admin API
 *
 * GET  /admin/api/cameras.php  → returns current cameras.conf as JSON array
 * POST /admin/api/cameras.php  → receives JSON array, writes cameras.conf
 *
 * Each camera entry:
 *   { "tableId": "table1", "rtspUrl": "rtsp://...", "streamKey": "xxxx-xxxx-xxxx-xxxx" }
 */

require_once dirname(__DIR__, 2) . '/api/bootstrap.php';

if (!railshot_admin_exists()) {
    railshot_json_response(['error' => 'Admin not configured'], 503);
}

railshot_require_login_api();

// ── Locate cameras.conf ───────────────────────────────────────────────────────
$confCandidates = [
    dirname(__DIR__, 2) . '/streaming/cameras.conf',
    'C:\\Inetpub\\vhosts\\railshottv.com\\httpdocs\\streaming\\cameras.conf',
];

$confFile = null;
foreach ($confCandidates as $candidate) {
    if (file_exists($candidate)) {
        $confFile = $candidate;
        break;
    }
}

// If not found, default to the relative path (will be created on first save)
if (!$confFile) {
    $confFile = dirname(__DIR__, 2) . '/streaming/cameras.conf';
}

// ── GET: parse and return cameras ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $cameras = [];

    if (file_exists($confFile)) {
        $lines = file($confFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 3) continue;
            [$tableId, $rtspUrl, $streamKey] = $parts;
            if (!$tableId || !$rtspUrl || !$streamKey) continue;
            $cameras[] = [
                'tableId'   => $tableId,
                'rtspUrl'   => $rtspUrl,
                'streamKey' => $streamKey,
            ];
        }
    }

    railshot_json_response(['ok' => true, 'cameras' => $cameras]);
}

// ── POST: validate and write cameras.conf ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = railshot_read_json_body();
    $incoming = $body['cameras'] ?? [];

    if (!is_array($incoming)) {
        railshot_json_response(['error' => 'Invalid payload'], 400);
    }

    $lines = [];
    $lines[] = '# ═══════════════════════════════════════════════════════════════════════════';
    $lines[] = '# RailShot TV — Camera Streaming Config';
    $lines[] = '# Managed via Admin Panel — do not edit manually';
    $lines[] = '# ═══════════════════════════════════════════════════════════════════════════';
    $lines[] = '#';
    $lines[] = '# FORMAT:  TABLE_NAME | RTSP_URL | YOUTUBE_STREAM_KEY';
    $lines[] = '#';

    $written = 0;
    foreach ($incoming as $cam) {
        if (!is_array($cam)) continue;
        $tableId   = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($cam['tableId'] ?? ''));
        $rtspUrl   = trim($cam['rtspUrl'] ?? '');
        $streamKey = trim($cam['streamKey'] ?? '');

        if (!$tableId || !$rtspUrl || !$streamKey) continue;

        // Basic RTSP URL sanity check
        if (!preg_match('#^rtsp://#i', $rtspUrl)) {
            railshot_json_response(['error' => 'RTSP URL for "' . $tableId . '" must start with rtsp://'], 400);
        }

        $lines[] = $tableId . ' | ' . $rtspUrl . ' | ' . $streamKey;
        $written++;
    }

    $content = implode("\n", $lines) . "\n";

    // Ensure the streaming directory exists
    $dir = dirname($confFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    if (file_put_contents($confFile, $content, LOCK_EX) === false) {
        railshot_json_response(['error' => 'Failed to write cameras.conf — check file permissions'], 500);
    }

    railshot_json_response(['ok' => true, 'written' => $written]);
}

railshot_json_response(['error' => 'Method not allowed'], 405);
