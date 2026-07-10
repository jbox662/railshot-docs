<?php
/**
 * RailShot TV — Stream Watchdog (Windows Plesk / PHP CLI only)
 *
 * Only restarts FFmpeg for tables explicitly marked "live" in stream-state.json.
 * Streams never auto-start on their own — use Go Live in admin or golive.html.
 */

require_once __DIR__ . '/streaming-common.php';
railshot_streaming_require_cli();

$logFile = __DIR__ . DIRECTORY_SEPARATOR . 'watchdog.log';

function wlog(string $msg): void
{
    global $logFile;
    $line = date('[Y-m-d H:i:s]') . ' ' . $msg . "\n";
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    wlog("PHP ERROR [$errno] $errstr in $errfile on line $errline");
    return true;
});

wlog('=== Watchdog started ===');

$confFile = railshot_streaming_conf_path();
if (!file_exists($confFile)) {
    wlog('ERROR: cameras.conf not found at ' . $confFile);
    exit(1);
}

$ffmpeg = railshot_streaming_find_ffmpeg();
if (!$ffmpeg) {
    wlog('ERROR: ffmpeg not found. Aborting.');
    exit(1);
}

$cameras = railshot_streaming_parse_cameras($confFile);
$liveTables = array_values(array_filter(
    $cameras,
    static fn(array $camera): bool => railshot_streaming_table_is_live($camera['table'])
));

if ($liveTables === []) {
    if (railshot_streaming_is_ffmpeg_running() !== 0) {
        wlog('No tables marked live — stopping stray FFmpeg');
        railshot_streaming_kill_ffmpeg();
    } else {
        wlog('No tables marked live — nothing to do');
    }
    wlog('=== Watchdog done ===');
    exit(0);
}

if (railshot_streaming_is_ffmpeg_running() !== 0) {
    $liveTable = $liveTables[0]['table'] ?? '';
    $state = railshot_streaming_load_state();
    $desiredTable = '';
    foreach ($state['tables'] ?? [] as $id => $desired) {
        if ($desired === 'live') {
            $desiredTable = $id;
            break;
        }
    }
    if ($desiredTable !== '' && $desiredTable !== $liveTable) {
        wlog("FFmpeg running but state says live table is $desiredTable — restarting");
        railshot_streaming_kill_ffmpeg();
        railshot_streaming_wait_for_ffmpeg_exit(6000);
    } else {
        wlog('FFmpeg already running for a live table — skipping');
        wlog('=== Watchdog done ===');
        exit(0);
    }
}

$camera = $liveTables[0];
$table = $camera['table'];
$streamLog = __DIR__ . DIRECTORY_SEPARATOR . 'stream-' . $table . '.log';

wlog("Restarting FFmpeg for live table: $table");

$cmd = railshot_streaming_build_ffmpeg_cmd($ffmpeg, $camera);
if (!railshot_streaming_launch_detached($cmd, $streamLog)) {
    wlog('ERROR: Could not launch FFmpeg for ' . $table);
    exit(1);
}

sleep(3);
$verifyPid = railshot_streaming_is_ffmpeg_running();
if ($verifyPid !== 0) {
    wlog("Verified running — PID $verifyPid");
} else {
    wlog('WARNING: FFmpeg may not have started — check ' . $streamLog);
}

$allLines = @file($logFile, FILE_IGNORE_NEW_LINES);
if (is_array($allLines) && count($allLines) > 1000) {
    file_put_contents($logFile, implode("\n", array_slice($allLines, -1000)) . "\n");
}

wlog('=== Watchdog done ===');
