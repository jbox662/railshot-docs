<?php
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

function railshot_probe(string $url, string $method = 'GET'): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'error' => 'curl extension missing'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'ok' => $code >= 200 && $code < 400,
        'status' => $code,
        'error' => $error ?: null,
    ];
}

$root = railshot_probe('http://127.0.0.1:8888/');
$hls = railshot_probe('http://127.0.0.1:8888/table1/index.m3u8');
$webrtc = railshot_probe('http://127.0.0.1:8889/table1/whep', 'OPTIONS');

$message = 'Unknown';
if ($root['status'] === 0) {
    $message = 'MediaMTX is NOT running on this VPS (cannot connect to port 8888).';
} elseif ($hls['status'] === 404) {
    $message = 'MediaMTX is running but path "table1" is missing. Copy paths from admin YAML to C:\\mediamtx\\mediamtx.yml and restart.';
} elseif ($hls['status'] === 500) {
    $message = 'MediaMTX path exists but camera RTSP failed. Check password, RTSP URL, and port forward 8554.';
} elseif ($hls['ok']) {
    $message = 'MediaMTX and table1 stream look good.';
}

railshot_json_response([
    'mediamtx' => [
        'root' => $root,
        'hls_table1' => $hls,
        'webrtc_table1' => $webrtc,
    ],
    'message' => $message,
]);
