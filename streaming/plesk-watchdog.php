<?php
/**
 * RailShot TV — Stream Watchdog (Windows Plesk / PHP CLI only)
 *
 * Plesk Scheduled Tasks → Run a PHP script → httpdocs/streaming/plesk-watchdog.php
 * Schedule: every minute (* * * * *)
 * Log: httpdocs/streaming/watchdog.log
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
wlog('PHP version: ' . PHP_VERSION);
wlog('SAPI: ' . php_sapi_name());

$confFile = railshot_streaming_conf_path();
if (!file_exists($confFile)) {
    wlog('ERROR: cameras.conf not found at ' . $confFile);
    exit(1);
}
wlog('Found cameras.conf at: ' . $confFile);

$ffmpeg = railshot_streaming_find_ffmpeg();
if (!$ffmpeg) {
    wlog('ERROR: ffmpeg not found. Aborting.');
    exit(1);
}
wlog('Found ffmpeg at: ' . $ffmpeg);

$cameras = railshot_streaming_parse_cameras($confFile);
if (empty($cameras)) {
    wlog('WARNING: No cameras found in cameras.conf');
    wlog('=== Watchdog done ===');
    exit(0);
}

foreach ($cameras as $camera) {
    $table = $camera['table'];
    $rtspUrl = $camera['rtsp'];
    $ytKey = $camera['ytKey'];
    $streamLog = __DIR__ . DIRECTORY_SEPARATOR . 'stream-' . $table . '.log';

    wlog("Checking camera: $table");

    $runningPid = railshot_streaming_is_ffmpeg_running();
    if ($runningPid !== 0) {
        wlog("  FFmpeg already running (PID $runningPid) — skipping");
        continue;
    }

    wlog('  FFmpeg not running — starting stream');

    $ytUrl = 'rtmp://a.rtmp.youtube.com/live2/' . $ytKey;
    $cmd = '"' . $ffmpeg . '" -loglevel warning -rtsp_transport tcp -timeout 10000000 '
        . '-i "' . $rtspUrl . '" -c:v libx264 -preset veryfast -b:v 2500k -maxrate 2500k -bufsize 5000k '
        . '-r 30 -g 60 -keyint_min 60 -sc_threshold 0 '
        . '-c:a aac -b:a 128k -ar 44100 '
        . '-f flv -flvflags no_duration_filesize "' . $ytUrl . '"';

    wlog('  Launching FFmpeg for ' . $table);

    if (!railshot_streaming_launch_detached($cmd, $streamLog)) {
        wlog('  ERROR: Could not launch FFmpeg');
        continue;
    }

    wlog('  Launch command sent');

    sleep(3);
    $verifyPid = railshot_streaming_is_ffmpeg_running();
    if ($verifyPid !== 0) {
        wlog("  Verified running — PID $verifyPid");
    } else {
        wlog('  WARNING: FFmpeg may not have started — check stream log: ' . $streamLog);
    }
}

$allLines = @file($logFile, FILE_IGNORE_NEW_LINES);
if (is_array($allLines) && count($allLines) > 1000) {
    file_put_contents($logFile, implode("\n", array_slice($allLines, -1000)) . "\n");
}

wlog('=== Watchdog done ===');
