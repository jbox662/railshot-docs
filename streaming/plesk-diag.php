<?php
/**
 * RailShot TV — FFmpeg diagnostic (CLI or admin-only).
 * Plesk Scheduled Tasks → Run a PHP script → httpdocs/streaming/plesk-diag.php
 * Output: httpdocs/streaming/diag.log
 */

require_once __DIR__ . '/streaming-common.php';
railshot_streaming_require_cli_or_admin();

$logFile = __DIR__ . DIRECTORY_SEPARATOR . 'diag.log';

function dlog(string $msg): void
{
    global $logFile;
    $safe = railshot_streaming_redact($msg);
    $line = date('[Y-m-d H:i:s]') . ' ' . $safe . "\n";
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    dlog("PHP ERROR [$errno] $errstr on line $errline");
    return true;
});

dlog('=== DIAGNOSTIC START ===');

$ffmpeg = railshot_streaming_find_ffmpeg();
if (!$ffmpeg) {
    dlog('ERROR: ffmpeg not found.');
    dlog('=== DIAGNOSTIC DONE ===');
    exit(1);
}
dlog('FFmpeg path: ' . $ffmpeg);

$confFile = railshot_streaming_conf_path();
$cameras = railshot_streaming_parse_cameras($confFile);
if (empty($cameras)) {
    dlog('ERROR: No cameras in cameras.conf at ' . $confFile);
    dlog('=== DIAGNOSTIC DONE ===');
    exit(1);
}

$camera = $cameras[0];
$rtsp = $camera['rtsp'];
$ytUrl = 'rtmp://a.rtmp.youtube.com/live2/' . $camera['ytKey'];

dlog('Camera table: ' . $camera['table']);
dlog('RTSP URL:    ' . $rtsp);
dlog('YouTube URL: ' . $ytUrl);

dlog('--- Test 1: Probing RTSP camera (10 sec) ---');
$probeCmd = '"' . $ffmpeg . '" -loglevel warning -rtsp_transport tcp -timeout 10000000 '
    . '-i "' . $rtsp . '" -t 5 -f null - 2>&1';
dlog('Running probe for table ' . $camera['table']);
exec($probeCmd, $probeOut, $probeRet);
dlog('Exit code: ' . $probeRet);
foreach ($probeOut as $l) {
    dlog('  PROBE: ' . $l);
}

dlog('--- Test 2: Testing YouTube RTMP connection (5 sec) ---');
$rtmpCmd = '"' . $ffmpeg . '" -loglevel warning -f lavfi -i testsrc=duration=5:size=1280x720:rate=30 '
    . '-f flv "' . $ytUrl . '" 2>&1';
dlog('Running RTMP test');
exec($rtmpCmd, $rtmpOut, $rtmpRet);
dlog('Exit code: ' . $rtmpRet);
foreach ($rtmpOut as $l) {
    dlog('  RTMP: ' . $l);
}

dlog('=== DIAGNOSTIC DONE ===');
