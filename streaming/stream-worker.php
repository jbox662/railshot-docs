<?php
/**
 * RailShot Stream Worker — runs as SYSTEM via scheduled task.
 * Owns FFmpeg start/stop/switch; the website only writes JSON commands.
 *
 * Install once: streaming/install-stream-worker.bat (Run as administrator)
 */

require_once __DIR__ . '/stream-worker-common.php';

// Heartbeat first so install/diagnostics can see the worker even if bootstrap fails.
file_put_contents(railshot_worker_heartbeat_file(), json_encode([
    'ts' => time(),
    'pid' => getmypid(),
    'phase' => 'starting',
], JSON_UNESCAPED_SLASHES), LOCK_EX);

require_once __DIR__ . '/streaming-common.php';
require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/api/stream-state.php';

railshot_streaming_require_cli();

$logFile = __DIR__ . DIRECTORY_SEPARATOR . 'worker.log';
$lastProcessedId = '';
$lastRecoveryAt = 0;

function worker_log(string $msg): void
{
    global $logFile;
    $line = date('[Y-m-d H:i:s]') . ' ' . railshot_streaming_redact($msg) . "\n";
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

function worker_heartbeat(): void
{
    file_put_contents(railshot_worker_heartbeat_file(), json_encode([
        'ts' => time(),
        'pid' => getmypid(),
    ], JSON_UNESCAPED_SLASHES), LOCK_EX);
}

/** @param array<string, mixed> $result */
function worker_write_result(array $result): void
{
    file_put_contents(
        railshot_worker_result_file(),
        json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function worker_stop_ffmpeg(): void
{
    railshot_streaming_stop_scheduled_tasks();
    for ($i = 0; $i < 15; $i++) {
        foreach (railshot_streaming_list_ffmpeg_pids() as $pid) {
            exec('taskkill /F /T /PID ' . $pid . ' 2>NUL');
        }
        exec('taskkill /F /T /IM ffmpeg.exe 2>NUL');
        if (railshot_streaming_is_ffmpeg_running() === 0) {
            break;
        }
        usleep(400000);
    }

    file_put_contents(railshot_worker_state_file(), json_encode([
        'managedPid' => 0,
        'activeTable' => '',
        'startedAt' => 0,
    ], JSON_UNESCAPED_SLASHES), LOCK_EX);
}

/** @return array{ok:bool,error?:string,action?:string,tableId?:string,sourcePort?:string,pid?:int} */
function worker_start_table(string $tableId): array
{
    $tableId = railshot_sanitize_table_id($tableId);
    if ($tableId === '') {
        return ['ok' => false, 'error' => 'Invalid table id'];
    }

    railshot_sync_cameras_conf();

    $camera = railshot_resolve_stream_camera($tableId);
    if ($camera === null) {
        return ['ok' => false, 'error' => railshot_stream_camera_missing_reason($tableId)];
    }

    $ffmpeg = railshot_streaming_find_ffmpeg();
    if ($ffmpeg === null) {
        return ['ok' => false, 'error' => 'FFmpeg not found on server'];
    }

    worker_stop_ffmpeg();
    usleep(500000);

    $state = railshot_stream_load_state();
    foreach (array_keys($state['tables']) as $id) {
        $state['tables'][$id] = 'stopped';
    }
    $state['tables'][$tableId] = 'live';
    railshot_stream_save_state($state);

    $streamLog = __DIR__ . DIRECTORY_SEPARATOR . 'stream-' . $tableId . '.log';
    $cmd = railshot_streaming_build_ffmpeg_cmd($ffmpeg, $camera);
    if (!railshot_streaming_launch_detached($cmd, $streamLog)) {
        $state['tables'][$tableId] = 'stopped';
        railshot_stream_save_state($state);
        return ['ok' => false, 'error' => 'Failed to launch FFmpeg'];
    }

    if (!railshot_streaming_verify_ffmpeg_started(6000)) {
        worker_stop_ffmpeg();
        $state['tables'][$tableId] = 'stopped';
        railshot_stream_save_state($state);
        return [
            'ok' => false,
            'error' => 'FFmpeg exited right after start — check streaming/stream-' . $tableId . '.log',
        ];
    }

    $pid = railshot_streaming_is_ffmpeg_running();
    file_put_contents(railshot_worker_state_file(), json_encode([
        'managedPid' => $pid,
        'activeTable' => $tableId,
        'startedAt' => time(),
    ], JSON_UNESCAPED_SLASHES), LOCK_EX);

    worker_log("Started FFmpeg for $tableId (PID $pid)");

    return [
        'ok' => true,
        'action' => 'started',
        'tableId' => $tableId,
        'sourcePort' => railshot_streaming_rtsp_port($camera['rtsp']),
        'pid' => $pid,
    ];
}

/** @return array{ok:bool,error?:string,action?:string} */
function worker_stop_all(): array
{
    worker_stop_ffmpeg();

    $state = railshot_stream_load_state();
    foreach (array_keys($state['tables']) as $tableId) {
        $state['tables'][$tableId] = 'stopped';
    }
    railshot_stream_save_state($state);

    worker_log('Stopped all FFmpeg streams');

    return ['ok' => true, 'action' => 'stopped'];
}

/** @param array<string, mixed> $command */
function worker_process_command(array $command): array
{
    $action = strtolower(trim((string) ($command['action'] ?? '')));
    $tableId = railshot_sanitize_table_id((string) ($command['tableId'] ?? ''));

    if ($action === 'stop') {
        return worker_stop_all();
    }
    if ($action === 'start' && $tableId !== '') {
        return worker_start_table($tableId);
    }

    return ['ok' => false, 'error' => 'Unknown command action'];
}

function worker_maybe_recover(): void
{
    global $lastRecoveryAt;

    if (time() - $lastRecoveryAt < 8) {
        return;
    }
    $lastRecoveryAt = time();

    if (file_exists(railshot_worker_command_file())) {
        return;
    }

    $liveTable = railshot_stream_live_table_id();
    $ffmpegRunning = railshot_streaming_is_ffmpeg_running() !== 0;

    $workerState = [];
    $stateFile = railshot_worker_state_file();
    if (file_exists($stateFile)) {
        $workerState = json_decode(file_get_contents($stateFile) ?: '', true) ?: [];
    }
    $activeTable = (string) ($workerState['activeTable'] ?? '');

    if ($liveTable === '') {
        if ($ffmpegRunning) {
            worker_log('No table marked live — stopping stray FFmpeg');
            worker_stop_ffmpeg();
        }
        return;
    }

    if (!$ffmpegRunning || $activeTable !== $liveTable) {
        worker_log("Recovery: ensuring live table $liveTable");
        $result = worker_start_table($liveTable);
        if (!($result['ok'] ?? false)) {
            worker_log('Recovery failed: ' . ($result['error'] ?? 'unknown'));
        }
    }
}

worker_log('=== Stream worker started (PID ' . getmypid() . ') ===');
worker_heartbeat();

while (true) {
    worker_heartbeat();

    $cmdFile = railshot_worker_command_file();
    if (file_exists($cmdFile)) {
        $command = json_decode(file_get_contents($cmdFile) ?: '', true);
        if (is_array($command)) {
            $cmdId = (string) ($command['id'] ?? '');
            if ($cmdId !== '' && $cmdId !== $lastProcessedId) {
                worker_log('Command ' . $cmdId . ': ' . ($command['action'] ?? '') . ' ' . ($command['tableId'] ?? ''));
                $result = worker_process_command($command);
                $result['id'] = $cmdId;
                $result['completedAt'] = time();
                worker_write_result($result);
                $lastProcessedId = $cmdId;
                @unlink($cmdFile);
            }
        }
    } else {
        worker_maybe_recover();
    }

    $lines = @file($logFile, FILE_IGNORE_NEW_LINES);
    if (is_array($lines) && count($lines) > 2000) {
        file_put_contents($logFile, implode("\n", array_slice($lines, -1500)) . "\n");
    }

    usleep(500000);
}
