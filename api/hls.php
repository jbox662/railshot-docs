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

[$pathFile, $pathQuery] = railshot_hls_split_path_query($path);
$upstream = 'http://127.0.0.1:8888/' . $pathFile . ($pathQuery !== '' ? '?' . $pathQuery : '');

if (!function_exists('curl_init')) {
    railshot_hls_error(500, 'PHP curl extension is not enabled on this server.');
}

$ch = curl_init($upstream);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 60,
]);

$body = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($body === false) {
    railshot_hls_error(502, 'Cannot reach MediaMTX on 127.0.0.1:8888. ' . $curlError);
}

if ($status === 404) {
    railshot_hls_error(404, 'Stream path not found in MediaMTX. Add path "' . dirname($pathFile) . '" to mediamtx.yml and restart mediamtx.exe.');
}

if ($status === 500 || $status === 502) {
    railshot_hls_error($status, 'MediaMTX could not pull the camera. Check RTSP URL/password in mediamtx.yml and your home port forward (8554 -> camera).');
}

if ($status < 200 || $status >= 400) {
    railshot_hls_error($status > 0 ? $status : 502, 'MediaMTX returned HTTP ' . $status);
}

if (preg_match('/\.m3u8$/i', $pathFile)) {
    $body = railshot_rewrite_hls_playlist($body, $path);
    header('Content-Type: application/vnd.apple.mpegurl');
} elseif (preg_match('/\.(ts|mp4|m4s)$/i', $pathFile)) {
    header('Content-Type: ' . (preg_match('/\.ts$/i', $pathFile) ? 'video/mp2t' : 'video/mp4'));
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

function railshot_hls_split_path_query(string $path): array
{
    $parts = explode('?', $path, 2);
    return [$parts[0], $parts[1] ?? ''];
}

function railshot_rewrite_hls_playlist(string $playlist, string $playlistPath): string
{
    $baseDir = railshot_hls_playlist_base_dir($playlistPath);
    $lines = preg_split('/\r\n|\n|\r/', $playlist) ?: [];
    $out = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            $out[] = $line;
            continue;
        }

        if ($trimmed[0] === '#') {
            $out[] = preg_replace_callback(
                '/URI="([^"]+)"/',
                static function (array $matches) use ($baseDir): string {
                    return 'URI="' . railshot_rewrite_hls_uri($matches[1], $baseDir) . '"';
                },
                $line
            ) ?? $line;
            continue;
        }

        $out[] = railshot_rewrite_hls_uri($trimmed, $baseDir);
    }

    return implode("\n", $out);
}

function railshot_hls_playlist_base_dir(string $playlistPath): string
{
    [$pathFile] = railshot_hls_split_path_query($playlistPath);
    $baseDir = str_replace('\\', '/', dirname($pathFile));
    return $baseDir === '.' ? '' : $baseDir;
}

function railshot_rewrite_hls_uri(string $uri, string $baseDir): string
{
    if (strpos($uri, '/api/hls.php?') === 0) {
        return $uri;
    }

    if (preg_match('#^https?://#i', $uri)) {
        $parts = parse_url($uri);
        $remotePath = ltrim($parts['path'] ?? '', '/');
        if (!empty($parts['query'])) {
            $remotePath .= '?' . $parts['query'];
        }
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
