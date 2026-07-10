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

    exec('wmic process where "name=\'ffmpeg.exe\'" get ProcessId /FORMAT:LIST 2>NUL', $out);
    foreach ($out as $line) {
        if (stripos($line, 'ProcessId=') !== false) {
            $pid = (int) trim(str_ireplace('ProcessId=', '', $line));
            if ($pid > 0) {
                return $pid;
            }
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

function railshot_streaming_kill_ffmpeg(): void
{
    exec('taskkill /F /IM ffmpeg.exe 2>NUL');
}

/** @return array{table:string,rtsp:string,ytKey:string}|null */
function railshot_streaming_find_camera(string $tableId): ?array
{
    $tableId = strtolower(trim($tableId));
    foreach (railshot_streaming_parse_cameras(railshot_streaming_conf_path()) as $camera) {
        if ($camera['table'] === $tableId) {
            return $camera;
        }
    }
    return null;
}

function railshot_streaming_build_ffmpeg_cmd(string $ffmpeg, array $camera): string
{
    $ytUrl = 'rtmp://a.rtmp.youtube.com/live2/' . $camera['ytKey'];
    return '"' . $ffmpeg . '" -loglevel warning -rtsp_transport tcp -timeout 10000000 '
        . '-i "' . $camera['rtsp'] . '" -c:v libx264 -preset veryfast -b:v 2500k -maxrate 2500k -bufsize 5000k '
        . '-r 30 -g 60 -keyint_min 60 -sc_threshold 0 '
        . '-c:a aac -b:a 128k -ar 44100 '
        . '-f flv -flvflags no_duration_filesize "' . $ytUrl . '"';
}

/** @return array{tables: array<string, string>} */
function railshot_streaming_load_state(): array
{
    $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'App_Data' . DIRECTORY_SEPARATOR . 'railshot' . DIRECTORY_SEPARATOR . 'stream-state.json';
    if (!file_exists($file)) {
        return ['tables' => []];
    }
    $data = json_decode(file_get_contents($file) ?: '', true);
    if (!is_array($data)) {
        return ['tables' => []];
    }
    $tables = [];
    foreach ($data['tables'] ?? [] as $tableId => $desired) {
        $id = preg_replace('/[^a-z0-9_-]+/', '', strtolower((string) $tableId)) ?? '';
        if ($id === '') {
            continue;
        }
        $tables[$id] = ($desired === 'live') ? 'live' : 'stopped';
    }
    return ['tables' => $tables];
}

function railshot_streaming_table_is_live(string $tableId): bool
{
    $tableId = preg_replace('/[^a-z0-9_-]+/', '', strtolower(trim($tableId))) ?? '';
    $state = railshot_streaming_load_state();
    return ($state['tables'][$tableId] ?? 'stopped') === 'live';
}
