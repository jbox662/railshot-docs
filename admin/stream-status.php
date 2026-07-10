<?php
/**
 * Admin diagnostic — shows whether each table is ready for Go Live.
 * Upload to Plesk, open while logged into admin, then delete when done.
 */
require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/api/stream-engine.php';
require_once dirname(__DIR__) . '/streaming/streaming-common.php';
require_once dirname(__DIR__) . '/streaming/stream-worker-common.php';

$worker = railshot_stream_worker_status();
$ffmpegPids = railshot_streaming_list_ffmpeg_pids();

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
if ($confExists) {
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

    <h2>Stream worker</h2>
    <table>
        <tr><th>Worker running</th><td class="<?php echo $worker['alive'] ? 'ok' : 'bad'; ?>">
            <?php echo $worker['alive'] ? 'YES' : 'NO — run install-stream-worker.bat as Administrator'; ?>
        </td></tr>
        <tr><th>Worker PID</th><td><?php echo $worker['pid'] !== null ? (int) $worker['pid'] : '—'; ?></td></tr>
        <tr><th>Last heartbeat</th><td><?php echo $worker['ageSec'] !== null ? (int) $worker['ageSec'] . 's ago' : 'never'; ?></td></tr>
        <tr><th>FFmpeg PIDs</th><td><?php echo $ffmpegPids !== [] ? htmlspecialchars(implode(', ', $ffmpegPids)) : 'none'; ?></td></tr>
        <tr><th>Live table (state)</th><td><code><?php echo htmlspecialchars(railshot_stream_live_table_id() ?: 'none'); ?></code></td></tr>
    </table>

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

    <p style="color:#fbbf24;margin-top:1.5rem"><strong>Go Live / table switching not working?</strong>
        On the VPS, right-click <code>streaming/install-stream-worker.bat</code> &rarr; <strong>Run as administrator</strong> (one time).
        The worker uses PowerShell (no PHP path needed).</p>
    <p style="color:#fbbf24"><strong>Wrong camera?</strong>
        table1 = RTSP port <strong>8554</strong>, table2 = port <strong>8555</strong>. Each table needs its own YouTube stream key.</p>
</body>
</html>
