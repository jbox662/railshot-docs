<?php
/**
 * RailShot TV — FFmpeg stream start/stop (Windows Plesk).
 * Delegates to the RailShot Stream Worker (SYSTEM) — IIS never kills FFmpeg directly.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/stream-state.php';
require_once dirname(__DIR__) . '/streaming/streaming-common.php';
require_once dirname(__DIR__) . '/streaming/stream-worker-common.php';

/** @return array{ok:bool,error?:string,action?:string} */
function railshot_stream_stop_all(): array
{
    $result = railshot_stream_worker_send_command('stop');
    if (!($result['ok'] ?? false)) {
        return $result;
    }

    return ['ok' => true, 'action' => 'stopped'];
}

/** @return array{ok:bool,error?:string,action?:string,tableId?:string,sourcePort?:string} */
function railshot_stream_start_table(string $tableId): array
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

    if (railshot_streaming_find_ffmpeg() === null) {
        return ['ok' => false, 'error' => 'FFmpeg not found on server'];
    }

    $result = railshot_stream_worker_send_command('start', $tableId, 40000);
    if (!($result['ok'] ?? false)) {
        return $result;
    }

    return [
        'ok' => true,
        'action' => 'started',
        'tableId' => $tableId,
        'sourcePort' => $result['sourcePort'] ?? railshot_streaming_rtsp_port($camera['rtsp']),
    ];
}

/** @return array{ok:bool,error?:string,action?:string,tableId?:string} */
function railshot_stream_apply_active_table(string $newActiveId): array
{
    if ($newActiveId === '') {
        return railshot_stream_stop_all();
    }
    return railshot_stream_start_table($newActiveId);
}
