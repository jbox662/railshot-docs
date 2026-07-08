<?php
/**
 * WebRTC / WHEP proxy for MediaMTX.
 * Usage: /api/webrtc.php?path=table1/whep
 */
declare(strict_types=1);

$path = $_GET['path'] ?? '';
$path = str_replace('\\', '/', $path);
$path = ltrim($path, '/');

if ($path === '' || strpos($path, '..') !== false) {
    http_response_code(400);
    exit('Invalid path');
}

$upstream = 'http://127.0.0.1:8889/' . $path;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$ch = curl_init($upstream);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 30,
]);

$bodyIn = file_get_contents('php://input');
if ($bodyIn !== false && $bodyIn !== '') {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyIn);
}

$forwardHeaders = [];
if (function_exists('getallheaders')) {
    foreach (getallheaders() as $name => $value) {
        $lower = strtolower($name);
        if (in_array($lower, ['host', 'connection', 'content-length'], true)) {
            continue;
        }
        $forwardHeaders[] = $name . ': ' . $value;
    }
}
if ($forwardHeaders) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $forwardHeaders);
}

$response = curl_exec($ch);
if ($response === false) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'MediaMTX WebRTC unavailable.';
    exit;
}

$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

$rawHeaders = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

http_response_code($status);

foreach (explode("\r\n", $rawHeaders) as $headerLine) {
    if ($headerLine === '' || stripos($headerLine, 'HTTP/') === 0) {
        continue;
    }
    if (stripos($headerLine, 'Transfer-Encoding:') === 0) {
        continue;
    }
    header($headerLine, false);
}

echo $body;
