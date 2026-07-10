<?php
/**
 * Admin diagnostic — shows whether each table is ready for Go Live.
 * Upload to Plesk, open while logged into admin, then delete when done.
 */
require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/api/stream-engine.php';
require_once dirname(__DIR__) . '/streaming/streaming-common.php';

if (!railshot_admin_exists()) {
    http_response_code(403);
    echo 'Admin not configured.';
    exit;
}
railshot_require_login();

$config = railshot_load_config();
$rtspByName = railshot_camera_rtsp_by_name();
$confPath = RAILSHOT_ROOT . DIRECTORY_SEPARATOR . 'streaming' . DIRECTORY_SEPARATOR . 'cameras.conf';
$confExists = file_exists($confPath);
$confTables = [];
if ($confExists && function_exists('railshot_streaming_parse_cameras')) {
    require_once dirname(__DIR__) . '/streaming/streaming-common.php';
    foreach (railshot_streaming_parse_cameras($confPath) as $row) {
        $confTables[$row['table']] = true;
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stream status — RailShot Admin</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; background: #111; color: #eee; }
        h1 { color: #00A8E8; }
        table { border-collapse: collapse; width: 100%; max-width: 900px; }
        th, td { border: 1px solid #333; padding: 10px 12px; text-align: left; vertical-align: top; }
        th { background: #1a1a1a; }
        .ok { color: #4ade80; }
        .bad { color: #f87171; }
        a { color: #00A8E8; }
        code { background: #222; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Go Live stream status</h1>
    <p>Checks whether FFmpeg can start for each table. Fix anything marked <span class="bad">MISSING</span>, then click <strong>Save live settings</strong> in admin.</p>
    <p><a href="/admin/">&larr; Back to admin</a></p>

    <p>cameras.conf: <?php echo $confExists ? '<span class="ok">found</span>' : '<span class="bad">missing</span>'; ?>
        at <code><?php echo htmlspecialchars($confPath); ?></code></p>

    <?php foreach (railshot_normalize_venues($config['live'] ?? []) as $venue): ?>
        <h2><?php echo htmlspecialchars($venue['name'] ?? $venue['id'] ?? 'Venue'); ?>
            <small>(<?php echo htmlspecialchars($venue['id'] ?? ''); ?>)</small></h2>
        <table>
            <tr>
                <th>Table</th>
                <th>Camera selected</th>
                <th>RTSP resolved</th>
                <th>RTSP port</th>
                <th>Stream key</th>
                <th>In cameras.conf</th>
                <th>Ready?</th>
                <th>Issue</th>
            </tr>
            <?php foreach ($venue['tables'] ?? [] as $table):
                if (!is_array($table) || empty($table['id'])) continue;
                $id = railshot_sanitize_table_id($table['id']);
                $cameraName = trim($table['cameraName'] ?? '');
                $streamKey = trim($table['streamKey'] ?? '');
                $rtsp = $rtspByName[$cameraName] ?? trim($table['rtspUrl'] ?? '');
                $inConf = !empty($confTables[$id]);
                $cam = railshot_resolve_stream_camera($id);
                $ready = $cam !== null;
                $issue = $ready ? '' : railshot_stream_camera_missing_reason($id);
                $rtspPort = $cam !== null ? railshot_streaming_rtsp_port($cam['rtsp']) : ($rtsp !== '' ? railshot_streaming_rtsp_port($rtsp) : '');
            ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($table['name'] ?? $id); ?></strong><br><code><?php echo htmlspecialchars($id); ?></code></td>
                <td class="<?php echo $cameraName !== '' ? 'ok' : 'bad'; ?>"><?php echo $cameraName !== '' ? htmlspecialchars($cameraName) : 'MISSING'; ?></td>
                <td class="<?php echo $rtsp !== '' ? 'ok' : 'bad'; ?>"><?php echo $rtsp !== '' ? 'Yes (password hidden)' : 'MISSING'; ?></td>
                <td class="<?php echo $rtspPort === '8555' || ($id === 'table2' && $rtspPort === '8555') ? 'ok' : ($rtspPort !== '' ? 'ok' : 'bad'); ?>">
                    <?php echo $rtspPort !== '' ? htmlspecialchars($rtspPort) : '—'; ?>
                    <?php if ($id === 'table2' && $rtspPort === '8554'): ?>
                        <br><span class="bad">Should be 8555 for camera 2</span>
                    <?php endif; ?>
                </td>
                <td class="<?php echo $streamKey !== '' ? 'ok' : 'bad'; ?>"><?php echo $streamKey !== '' ? 'Set (' . strlen($streamKey) . ' chars)' : 'MISSING'; ?></td>
                <td class="<?php echo $inConf ? 'ok' : 'bad'; ?>"><?php echo $inConf ? 'Yes' : 'No'; ?></td>
                <td class="<?php echo $ready ? 'ok' : 'bad'; ?>"><?php echo $ready ? 'YES' : 'NO'; ?></td>
                <td><?php echo $issue !== '' ? htmlspecialchars($issue) : '—'; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endforeach; ?>

    <h3>Registered cameras (Admin &rarr; Cameras)</h3>
    <?php if (empty($config['cameras'])): ?>
        <p class="bad">No cameras in config — add them on <a href="/admin/cameras.php">Cameras page</a>.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($config['cameras'] as $cam): ?>
                <li><strong><?php echo htmlspecialchars($cam['name'] ?? ''); ?></strong>
                    — RTSP <?php echo !empty($cam['rtspUrl']) ? '<span class="ok">set</span>' : '<span class="bad">missing</span>'; ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p style="color:#fbbf24;margin-top:1.5rem"><strong>Still seeing camera 1 after switching?</strong>
        Check that table2 uses RTSP port <strong>8555</strong> (not 8554), has its <strong>own YouTube stream key</strong>,
        and delete old Windows scheduled tasks: <code>schtasks /delete /tn "RailShot-table1" /f</code></p>
</body>
</html>
