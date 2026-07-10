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

    // Camera list is stored in config.json under cameras[]
    $config = railshot_load_config();
    $cameras = $config['cameras'] ?? [];

    railshot_json_response(['ok' => true, 'cameras' => $cameras]);
}

// ── POST: validate and write cameras.conf ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = railshot_read_json_body();
    $incoming = $body['cameras'] ?? [];

    if (!is_array($incoming)) {
        railshot_json_response(['error' => 'Invalid payload'], 400);
    }

    $cleaned = [];
    foreach ($incoming as $cam) {
        if (!is_array($cam)) continue;
        $name    = trim($cam['name'] ?? '');
        $rtspUrl = trim($cam['rtspUrl'] ?? '');
        if (!$name || !$rtspUrl) continue;
        if (!preg_match('#^rtsp://#i', $rtspUrl)) {
            railshot_json_response(['error' => 'RTSP URL for "' . htmlspecialchars($name) . '" must start with rtsp://'], 400);
        }
        $cleaned[] = ['name' => $name, 'rtspUrl' => $rtspUrl];
    }

    $config = railshot_load_config();
    $config['cameras'] = $cleaned;
    if (!railshot_save_config($config)) {
        railshot_json_response(['error' => 'Failed to save cameras'], 500);
    }

    railshot_json_response(['ok' => true, 'written' => count($cleaned)]);
}

railshot_json_response(['error' => 'Method not allowed'], 405);
