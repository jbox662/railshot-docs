<?php
/**
 * HLS proxy for MediaMTX — works without IIS URL Rewrite.
 * Usage: /api/hls.php?path=table1/index.m3u8
 */
declare(strict_types=1);

$path = $_GET['path'] ?? '';
$path = str_replace('\\', '/', $path);
$path = ltrim($path, '/');

if ($path === '' || strpos($path, '..') !== false) {
    railshot_hls_error(400, 'Invalid path');
}

$upstream = 'http://127.0.0.1:8888/' . $path;

if (!function_exists('curl_init')) {
    railshot_hls_error(500, 'PHP curl extension is not enabled on this server.');
}

$ch = curl_init($upstream);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 30,
]);

$body = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($body === false) {
    railshot_hls_error(502, 'Cannot reach MediaMTX on 127.0.0.1:8888. ' . $curlError);
}

if ($status === 404) {
    railshot_hls_error(404, 'Stream path not found in MediaMTX. Add path "' . dirname($path) . '" to C:\\mediamtx\\mediamtx.yml on the VPS and restart mediamtx.exe.');
}

if ($status === 500 || $status === 502) {
    railshot_hls_error($status, 'MediaMTX could not pull the camera. Check RTSP URL/password in mediamtx.yml and your home port forward (8554 -> camera).');
}

if ($status < 200 || $status >= 400) {
    railshot_hls_error($status > 0 ? $status : 502, 'MediaMTX returned HTTP ' . $status);
}

if (preg_match('/\.m3u8$/i', $path)) {
    $body = railshot_rewrite_hls_playlist($body, $path);
    header('Content-Type: application/vnd.apple.mpegurl');
} elseif (preg_match('/\.(ts|mp4|m4s)$/i', $path)) {
    header('Content-Type: video/mp2t');
}

header('Cache-Control: no-cache');
http_response_code(200);
echo $body;
exit;

function railshot_hls_error(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}

function railshot_rewrite_hls_playlist(string $playlist, string $playlistPath): string
{
    $baseDir = str_replace('\\', '/', dirname($playlistPath));
    if ($baseDir === '.') {
        $baseDir = '';
    }

    $lines = preg_split('/\r\n|\n|\r/', $playlist) ?: [];
    $out = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            $out[] = $line;
            continue;
        }

        if ($trimmed[0] === '#') {
            if (preg_match('/URI="([^"]+)"/', $line, $matches)) {
                $rewritten = railshot_rewrite_hls_uri($matches[1], $baseDir);
                $out[] = str_replace($matches[1], $rewritten, $line);
            } else {
                $out[] = $line;
            }
            continue;
        }

        $out[] = railshot_rewrite_hls_uri($trimmed, $baseDir);
    }

    return implode("\n", $out);
}

function railshot_rewrite_hls_uri(string $uri, string $baseDir): string
{
    if (preg_match('#^https?://#i', $uri)) {
        $parts = parse_url($uri);
        $remotePath = ltrim($parts['path'] ?? '', '/');
        return '/api/hls.php?path=' . rawurlencode($remotePath);
    }

    if (strpos($uri, '/') === 0) {
        $fullPath = ltrim($uri, '/');
    } elseif ($baseDir !== '') {
        $fullPath = $baseDir . '/' . $uri;
    } else {
        $fullPath = $uri;
    }

    $fullPath = preg_replace('#/+#', '/', $fullPath) ?? $fullPath;
    return '/api/hls.php?path=' . rawurlencode($fullPath);
}
