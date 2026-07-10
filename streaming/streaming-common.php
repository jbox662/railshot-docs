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

/** @return list<int> */
function railshot_streaming_list_ffmpeg_pids(): array
{
    exec('tasklist /FI "IMAGENAME eq ffmpeg.exe" /NH 2>NUL', $tOut);
    $pids = [];
    foreach ($tOut as $tLine) {
        if (stripos($tLine, 'ffmpeg.exe') === false) {
            continue;
        }
        $parts = preg_split('/\s+/', trim($tLine));
        if (isset($parts[1]) && is_numeric($parts[1])) {
            $pids[] = (int) $parts[1];
        }
    }
    return $pids;
}

function railshot_streaming_is_ffmpeg_running(): int
{
    $pids = railshot_streaming_list_ffmpeg_pids();
    return $pids[0] ?? 0;
}

/** Wait until no ffmpeg.exe process remains (after kill). */
function railshot_streaming_wait_for_ffmpeg_exit(int $maxMs = 6000): bool
{
    $elapsed = 0;
    while ($elapsed < $maxMs) {
        if (railshot_streaming_is_ffmpeg_running() === 0) {
            return true;
        }
        usleep(350000);
        $elapsed += 350;
    }
    return railshot_streaming_is_ffmpeg_running() === 0;
}

/** After launch, confirm ffmpeg stays running for a few seconds. */
function railshot_streaming_verify_ffmpeg_started(int $maxMs = 5000): bool
{
    $elapsed = 0;
    while ($elapsed < $maxMs) {
        if (railshot_streaming_is_ffmpeg_running() === 0) {
            return false;
        }
        usleep(500000);
        $elapsed += 500;
    }
    return railshot_streaming_is_ffmpeg_running() !== 0;
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

function railshot_streaming_kill_flag_path(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'kill-done.flag';
}

/** @return bool true when ffmpeg.exe is gone */
function railshot_streaming_run_system_kill(int $maxWaitMs = 25000): bool
{
    $flag = railshot_streaming_kill_flag_path();
    @unlink($flag);

    exec('schtasks /run /tn "RailShot-KillFFmpeg" 2>NUL', $out, $ret);

    $cmd = __DIR__ . DIRECTORY_SEPARATOR . 'kill-ffmpeg.cmd';
    if ($ret !== 0 && file_exists($cmd)) {
        exec('cmd /c "' . $cmd . '" 2>NUL');
    }

    $elapsed = 0;
    $retriggerMs = 0;
    while ($elapsed < $maxWaitMs) {
        if (file_exists($flag)) {
            @unlink($flag);
            if (railshot_streaming_is_ffmpeg_running() === 0) {
                return true;
            }
        }
        if (railshot_streaming_is_ffmpeg_running() === 0) {
            @unlink($flag);
            return true;
        }
        usleep(500000);
        $elapsed += 500;
        $retriggerMs += 500;
        if ($ret === 0 && $retriggerMs >= 4000) {
            exec('schtasks /run /tn "RailShot-KillFFmpeg" 2>NUL');
            $retriggerMs = 0;
        }
    }

    return railshot_streaming_is_ffmpeg_running() === 0;
}

function railshot_streaming_kill_ffmpeg(): void
{
    railshot_streaming_stop_scheduled_tasks();
    @unlink(railshot_streaming_kill_flag_path());

    for ($attempt = 0; $attempt < 10; $attempt++) {
        foreach (railshot_streaming_list_ffmpeg_pids() as $pid) {
            exec('taskkill /F /T /PID ' . $pid . ' 2>NUL');
        }
        exec('taskkill /F /T /IM ffmpeg.exe 2>NUL');
        if (railshot_streaming_is_ffmpeg_running() === 0) {
            break;
        }
        usleep(400000);
    }

    if (railshot_streaming_is_ffmpeg_running() !== 0) {
        railshot_streaming_run_system_kill(20000);
    }

    usleep(300000);
}

/** @return bool true when no ffmpeg.exe remains */
function railshot_streaming_force_stop_ffmpeg(int $maxMs = 25000): bool
{
    railshot_streaming_kill_ffmpeg();
    if (railshot_streaming_wait_for_ffmpeg_exit(4000)) {
        return true;
    }

    if (railshot_streaming_run_system_kill($maxMs)) {
        return true;
    }

    railshot_streaming_kill_ffmpeg();
    return railshot_streaming_wait_for_ffmpeg_exit(8000);
}

/** Stop legacy Task Scheduler stream jobs that auto-restart FFmpeg and override Go Live. */
function railshot_streaming_stop_scheduled_tasks(): void
{
    exec('schtasks /query /fo TABLE /nh 2>NUL', $out);
    foreach ($out as $line) {
        if (preg_match('/(RailShot-table\S+)/i', $line, $m)) {
            exec('schtasks /end /tn "' . $m[1] . '" 2>NUL');
        }
    }
}

function railshot_streaming_rtsp_port(string $rtsp): string
{
    if (preg_match('#@[^/:]+:(\d+)#', $rtsp, $m)) {
        return $m[1];
    }
    return '';
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
    return '"' . $ffmpeg . '" -loglevel warning -fflags +genpts -rtsp_transport tcp -stimeout 10000000 '
        . '-i "' . $camera['rtsp'] . '" -c:v copy -an '
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
