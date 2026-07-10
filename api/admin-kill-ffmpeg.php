<?php
/**
 * Admin: force-stop all FFmpeg processes via the stream worker.
 * GET /api/admin-kill-ffmpeg.php
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/streaming/streaming-common.php';
require_once dirname(__DIR__) . '/streaming/stream-worker-common.php';

if (!railshot_is_logged_in()) {
    railshot_json_response(['error' => 'Unauthorized'], 401);
}

$before = railshot_streaming_list_ffmpeg_pids();
$worker = railshot_stream_worker_status();

if ($worker['alive']) {
    $result = railshot_stream_worker_send_command('stop', '', 40000);
    $ok = (bool) ($result['ok'] ?? false);
} else {
    $ok = railshot_streaming_force_stop_ffmpeg(30000);
}

$after = railshot_streaming_list_ffmpeg_pids();

railshot_json_response([
    'ok' => $ok,
    'before' => $before,
    'after' => $after,
    'worker' => $worker,
]);
