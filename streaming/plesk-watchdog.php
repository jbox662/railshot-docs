<?php
/**
 * RailShot TV — Stream Watchdog (Windows Plesk / PHP CLI only)
 *
 * When the stream worker is running, this only restarts the worker if its heartbeat is stale.
 * Otherwise it falls back to legacy direct FFmpeg control (not recommended).
 */

require_once __DIR__ . '/streaming-common.php';
require_once __DIR__ . '/stream-worker-common.php';
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

$worker = railshot_stream_worker_status(20);
if ($worker['alive']) {
    wlog('Stream worker is alive (PID ' . ($worker['pid'] ?? '?') . ', heartbeat ' . ($worker['ageSec'] ?? '?') . 's ago) — nothing to do');
    wlog('=== Watchdog done ===');
    exit(0);
}

wlog('Stream worker heartbeat missing — attempting to start RailShot-StreamWorker task');
exec('schtasks /run /tn "RailShot-StreamWorker" 2>NUL', $out, $ret);
if ($ret === 0) {
    wlog('Triggered RailShot-StreamWorker scheduled task');
    sleep(5);
    if (railshot_stream_worker_alive()) {
        wlog('Worker is now running');
        wlog('=== Watchdog done ===');
        exit(0);
    }
}

wlog('Worker still not running — install streaming/install-stream-worker.bat as Administrator');
wlog('=== Watchdog done ===');
