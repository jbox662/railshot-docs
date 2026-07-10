<?php
/**
 * Shared helpers for streaming/*.php CLI scripts on Windows Plesk.
 */

function railshot_streaming_require_cli(): void
{
    if (php_sapi_name() === 'cli') {
        return;
    }
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "This script runs via Plesk scheduled task (CLI only).\n";
    exit;
}

function railshot_streaming_require_cli_or_admin(): void
{
    if (php_sapi_name() === 'cli') {
        return;
    }
    require_once dirname(__DIR__) . '/api/bootstrap.php';
    railshot_require_login();
    header('Content-Type: text/plain; charset=utf-8');
}

function railshot_streaming_redact(string $msg): string
{
    $msg = preg_replace('/(rtsp:\/\/[^:]+:)[^@]+(@)/i', '$1***$2', $msg);
    $msg = preg_replace('/live2\/[a-z0-9\-]+/i', 'live2/***', $msg);
    return $msg;
}

function railshot_streaming_conf_path(): string
{
    $local = __DIR__ . DIRECTORY_SEPARATOR . 'cameras.conf';
    if (file_exists($local)) {
        return $local;
    }
    $fallback = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'streaming' . DIRECTORY_SEPARATOR . 'cameras.conf';
    return file_exists($fallback) ? $fallback : $local;
}

/** @return list<array{table:string,rtsp:string,ytKey:string}> */
function railshot_streaming_parse_cameras(string $confFile): array
{
    if (!file_exists($confFile)) {
        return [];
    }

    $cameras = [];
    $lines = file($confFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) < 3) {
            continue;
        }
        [$table, $rtsp, $ytKey] = $parts;
        if ($table !== '' && $rtsp !== '' && $ytKey !== '') {
            $cameras[] = ['table' => $table, 'rtsp' => $rtsp, 'ytKey' => $ytKey];
        }
    }
    return $cameras;
}

function railshot_streaming_find_ffmpeg(): ?string
{
    $candidates = [
        __DIR__ . DIRECTORY_SEPARATOR . 'ffmpeg.exe',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'streaming' . DIRECTORY_SEPARATOR . 'ffmpeg.exe',
    ];
    foreach ($candidates as $candidate) {
        if (file_exists($candidate)) {
            return $candidate;
        }
    }

    exec('where ffmpeg 2>NUL', $whereOut, $whereRet);
    if ($whereRet === 0 && !empty($whereOut[0])) {
        return trim($whereOut[0]);
    }
    return null;
}

function railshot_streaming_is_ffmpeg_running(): int
{
    exec('wmic process where "name=\'ffmpeg.exe\'" get ProcessId /FORMAT:LIST 2>NUL', $out);
    foreach ($out as $line) {
        if (stripos($line, 'ProcessId=') !== false) {
            $pid = (int) trim(str_ireplace('ProcessId=', '', $line));
            if ($pid > 0) {
                return $pid;
            }
        }
    }

    exec('tasklist /FI "IMAGENAME eq ffmpeg.exe" /NH 2>NUL', $tOut);
    foreach ($tOut as $tLine) {
        if (stripos($tLine, 'ffmpeg.exe') !== false) {
            $parts = preg_split('/\s+/', trim($tLine));
            if (isset($parts[1]) && is_numeric($parts[1])) {
                return (int) $parts[1];
            }
            return -1;
        }
    }
    return 0;
}

function railshot_streaming_launch_detached(string $cmd, string $logFile): bool
{
    $launch = 'cmd /c start "" /B ' . $cmd . ' >> "' . $logFile . '" 2>&1';
    $handle = @popen($launch, 'r');
    if ($handle) {
        pclose($handle);
        return true;
    }

    try {
        $wsh = new COM('WScript.Shell');
        $wsh->Run($launch, 0, false);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
