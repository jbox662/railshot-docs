<?php
/**
 * RailShot TV — FFmpeg Diagnostic Script
 * Run once via Plesk Scheduled Tasks → Run a PHP script
 * Script: httpdocs/streaming/plesk-diag.php
 * Check output in: httpdocs/streaming/diag.log
 */

$logFile = 'C:\\Inetpub\\vhosts\\railshottv.com\\httpdocs\\streaming\\diag.log';

function dlog($msg) {
    global $logFile;
    $line = date('[Y-m-d H:i:s]') . ' ' . $msg . "\n";
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    dlog("PHP ERROR [$errno] $errstr on line $errline");
    return true;
});

dlog("=== DIAGNOSTIC START ===");

$ffmpeg   = 'C:\\Inetpub\\vhosts\\railshottv.com\\httpdocs\\streaming\\ffmpeg.exe';
$rtsp     = 'rtsp://admin:decoder1@140.106.76.67:8554/h264Preview_01_main';
$ytKey    = 'x0b3-tqcg-qqcg-shxk-ee8p';
$ytUrl    = "rtmp://a.rtmp.youtube.com/live2/$ytKey";

dlog("FFmpeg path: $ffmpeg");
dlog("RTSP URL:    $rtsp");
dlog("YouTube URL: $ytUrl");

// Test 1: Can we reach the RTSP camera at all? (probe only, 10 sec timeout)
dlog("--- Test 1: Probing RTSP camera (10 sec) ---");
$probeCmd = '"' . $ffmpeg . '" -loglevel verbose -rtsp_transport tcp -stimeout 10000000 -i "' . $rtsp . '" -t 5 -f null - 2>&1';
dlog("Running: $probeCmd");
exec($probeCmd, $probeOut, $probeRet);
dlog("Exit code: $probeRet");
foreach ($probeOut as $l) dlog("  PROBE: $l");

// Test 2: Can we connect to YouTube RTMP?
dlog("--- Test 2: Testing YouTube RTMP connection (5 sec) ---");
$rtmpCmd = '"' . $ffmpeg . '" -loglevel verbose -f lavfi -i testsrc=duration=5:size=1280x720:rate=30 -f flv "' . $ytUrl . '" 2>&1';
dlog("Running: $rtmpCmd");
exec($rtmpCmd, $rtmpOut, $rtmpRet);
dlog("Exit code: $rtmpRet");
foreach ($rtmpOut as $l) dlog("  RTMP: $l");

dlog("=== DIAGNOSTIC DONE ===");
