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
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Invalid path');
}

$upstream = 'http://127.0.0.1:8888/' . $path;

$ch = curl_init($upstream);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 30,
]);

$body = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($body === false || $status < 200 || $status >= 400) {
    http_response_code($status > 0 ? $status : 502);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'MediaMTX HLS unavailable. Is mediamtx.exe running on this VPS?';
    exit;
}

if (preg_match('/\.m3u8$/i', $path)) {
    $body = railshot_rewrite_hls_playlist($body, $path);
    header('Content-Type: application/vnd.apple.mpegurl');
} elseif (preg_match('/\.(ts|mp4|m4s)$/i', $path)) {
    header('Content-Type: video/mp2t');
}

header('Cache-Control: no-cache');
http_response_code($status);
echo $body;

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
