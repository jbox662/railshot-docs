<?php
/**
 * One-time migration: reads cameras.conf and injects existing cameras into
 * config.json so they appear on the Cameras admin page and are linked to tables.
 *
 * Run once via browser: https://railshottv.com/admin/migrate-cameras.php
 * Then delete this file.
 */
require_once dirname(__DIR__) . '/api/bootstrap.php';

if (!railshot_admin_exists()) {
    http_response_code(403); echo 'Not configured.'; exit;
}
railshot_require_login();

$confPath = dirname(__DIR__) . '/streaming/cameras.conf';
if (!file_exists($confPath)) {
    echo '<p>cameras.conf not found — nothing to migrate.</p>'; exit;
}

$lines = file($confPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$parsed = [];
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    $parts = array_map('trim', explode('|', $line));
    if (count($parts) < 3) continue;
    [$tableId, $rtspUrl, $streamKey] = $parts;
    if (!$tableId || !$rtspUrl || !$streamKey) continue;
    $parsed[] = ['tableId' => $tableId, 'rtspUrl' => $rtspUrl, 'streamKey' => $streamKey];
}

if (!$parsed) {
    echo '<p>No active (uncommented) entries found in cameras.conf.</p>'; exit;
}

$config = railshot_load_config();

// Build cameras list (name = "Table X Camera", rtspUrl from conf)
$existingCameras = $config['cameras'] ?? [];
$existingNames   = array_column($existingCameras, 'name');

foreach ($parsed as $p) {
    $camName = ucfirst($p['tableId']) . ' Camera'; // e.g. "Table1 Camera"
    // Nicer: table1 -> "Table 1 Camera"
    $camName = preg_replace_callback('/^table(\d+)$/i', function($m) {
        return 'Table ' . $m[1] . ' Camera';
    }, $p['tableId']);
    if (!in_array($camName, $existingNames, true)) {
        $existingCameras[] = ['name' => $camName, 'rtspUrl' => $p['rtspUrl']];
        $existingNames[]   = $camName;
    }
}
$config['cameras'] = $existingCameras;

// Link each table to its camera and inject streamKey
$venues = $config['live']['venues'] ?? [];
foreach ($venues as &$venue) {
    foreach ($venue['tables'] as &$table) {
        foreach ($parsed as $p) {
            if ($table['id'] === $p['tableId']) {
                $camName = preg_replace_callback('/^table(\d+)$/i', function($m) {
                    return 'Table ' . $m[1] . ' Camera';
                }, $p['tableId']);
                $table['cameraName'] = $camName;
                $table['streamKey']  = $p['streamKey'];
            }
        }
    }
    unset($table);
}
unset($venue);
$config['live']['venues'] = $venues;

if (!railshot_save_config($config)) {
    echo '<p style="color:red">Failed to save config.json — check file permissions.</p>'; exit;
}

echo '<!DOCTYPE html><html><head><title>Migration complete</title></head><body style="font-family:sans-serif;padding:2rem;">';
echo '<h2 style="color:green">&#10003; Migration complete</h2>';
echo '<p>Migrated ' . count($parsed) . ' camera(s) from cameras.conf into the admin panel:</p><ul>';
foreach ($parsed as $p) {
    $camName = preg_replace_callback('/^table(\d+)$/i', function($m) { return 'Table ' . $m[1] . ' Camera'; }, $p['tableId']);
    echo '<li><strong>' . htmlspecialchars($camName) . '</strong> — ' . htmlspecialchars($p['rtspUrl']) . '</li>';
}
echo '</ul>';
echo '<p><a href="/admin/cameras.php">&#8594; Go to Cameras page</a> &nbsp; <a href="/admin/">&#8594; Go to Admin</a></p>';
echo '<p style="color:#888;font-size:0.85em">You can now delete this file from your server: <code>admin/migrate-cameras.php</code></p>';
echo '</body></html>';
