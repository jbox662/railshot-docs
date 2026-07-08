<?php
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

function railshot_probe(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'ok' => $code >= 200 && $code < 500,
        'status' => $code,
        'error' => $error ?: null,
    ];
}

$hls = railshot_probe('http://127.0.0.1:8888/table1/index.m3u8');
$webrtc = railshot_probe('http://127.0.0.1:8889/table1/whep');

railshot_json_response([
    'mediamtx' => [
        'hls' => $hls,
        'webrtc' => $webrtc,
    ],
    'message' => $hls['ok']
        ? 'MediaMTX reachable on this server'
        : 'MediaMTX not running or table1 not available on this VPS',
]);
