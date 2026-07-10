<?php
/**
 * RailShot TV — FFmpeg stream start/stop (Windows Plesk).
 * Streams only run when explicitly set to "live" via Go Live / admin controls.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/streaming/streaming-common.php';

function railshot_stream_state_path(): string
{
    railshot_ensure_data_dir();
    return RAILSHOT_DATA . DIRECTORY_SEPARATOR . 'stream-state.json';
}

/** @return array{tables: array<string, string>} */
function railshot_stream_load_state(): array
{
    $file = railshot_stream_state_path();
    if (!file_exists($file)) {
        return ['tables' => []];
    }
    $data = json_decode(file_get_contents($file) ?: '', true);
    if (!is_array($data)) {
        return ['tables' => []];
    }
    $tables = [];
    foreach ($data['tables'] ?? [] as $tableId => $desired) {
        $id = railshot_sanitize_table_id((string) $tableId);
        if ($id === '') {
            continue;
        }
        $tables[$id] = ($desired === 'live') ? 'live' : 'stopped';
    }
    return ['tables' => $tables];
}

function railshot_stream_save_state(array $state): bool
{
    $tables = [];
    foreach ($state['tables'] ?? [] as $tableId => $desired) {
        $id = railshot_sanitize_table_id((string) $tableId);
        if ($id === '') {
            continue;
        }
        $tables[$id] = ($desired === 'live') ? 'live' : 'stopped';
    }
    $json = json_encode(['tables' => $tables], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    return file_put_contents(railshot_stream_state_path(), $json, LOCK_EX) !== false;
}

function railshot_stream_table_desired(string $tableId): string
{
    $tableId = railshot_sanitize_table_id($tableId);
    $state = railshot_stream_load_state();
    return ($state['tables'][$tableId] ?? 'stopped') === 'live' ? 'live' : 'stopped';
}

/** @return array{ok:bool,error?:string,action?:string} */
function railshot_stream_stop_all(): array
{
    railshot_streaming_kill_ffmpeg();

    $state = railshot_stream_load_state();
    foreach (array_keys($state['tables']) as $tableId) {
        $state['tables'][$tableId] = 'stopped';
    }
    railshot_stream_save_state($state);

    return ['ok' => true, 'action' => 'stopped'];
}

/** @return array{ok:bool,error?:string,action?:string,tableId?:string} */
function railshot_stream_start_table(string $tableId): array
{
    $tableId = railshot_sanitize_table_id($tableId);
    if ($tableId === '') {
        return ['ok' => false, 'error' => 'Invalid table id'];
    }

    $camera = railshot_streaming_find_camera($tableId);
    if ($camera === null) {
        return ['ok' => false, 'error' => 'Camera not found in streaming/cameras.conf'];
    }

    $ffmpeg = railshot_streaming_find_ffmpeg();
    if ($ffmpeg === null) {
        return ['ok' => false, 'error' => 'FFmpeg not found on server'];
    }

    railshot_streaming_kill_ffmpeg();

    $state = railshot_stream_load_state();
    foreach (array_keys($state['tables']) as $id) {
        $state['tables'][$id] = 'stopped';
    }
    $state['tables'][$tableId] = 'live';
    railshot_stream_save_state($state);

    $logFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'streaming' . DIRECTORY_SEPARATOR . 'stream-' . $tableId . '.log';
    $cmd = railshot_streaming_build_ffmpeg_cmd($ffmpeg, $camera);
    if (!railshot_streaming_launch_detached($cmd, $logFile)) {
        $state['tables'][$tableId] = 'stopped';
        railshot_stream_save_state($state);
        return ['ok' => false, 'error' => 'Failed to launch FFmpeg'];
    }

    sleep(2);
    if (railshot_streaming_is_ffmpeg_running() === 0) {
        $state['tables'][$tableId] = 'stopped';
        railshot_stream_save_state($state);
        return ['ok' => false, 'error' => 'FFmpeg did not stay running — check stream log'];
    }

    return ['ok' => true, 'action' => 'started', 'tableId' => $tableId];
}

/** @return array{ok:bool,error?:string,action?:string,tableId?:string} */
function railshot_stream_apply_active_table(string $newActiveId): array
{
    if ($newActiveId === '') {
        return railshot_stream_stop_all();
    }
    return railshot_stream_start_table($newActiveId);
}
