<?php
/**
 * Admin: force-stop all FFmpeg processes (uses SYSTEM kill task when available).
 * GET /api/admin-kill-ffmpeg.php
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/streaming/streaming-common.php';

if (!railshot_is_logged_in()) {
    railshot_json_response(['error' => 'Unauthorized'], 401);
}

$before = railshot_streaming_list_ffmpeg_pids();
$ok = railshot_streaming_force_stop_ffmpeg(30000);
$after = railshot_streaming_list_ffmpeg_pids();

railshot_json_response([
    'ok' => $ok,
    'before' => $before,
    'after' => $after,
    'killFlag' => file_exists(railshot_streaming_kill_flag_path()),
]);
