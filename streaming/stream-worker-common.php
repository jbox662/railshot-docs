<?php
/**
 * Command queue between IIS/PHP and the RailShot Stream Worker (SYSTEM).
 */

function railshot_worker_dir(): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'App_Data'
        . DIRECTORY_SEPARATOR . 'railshot' . DIRECTORY_SEPARATOR . 'stream-worker';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function railshot_worker_command_file(): string
{
    return railshot_worker_dir() . DIRECTORY_SEPARATOR . 'command.json';
}

function railshot_worker_result_file(): string
{
    return railshot_worker_dir() . DIRECTORY_SEPARATOR . 'result.json';
}

function railshot_worker_heartbeat_file(): string
{
    return railshot_worker_dir() . DIRECTORY_SEPARATOR . 'heartbeat.json';
}

function railshot_worker_state_file(): string
{
    return railshot_worker_dir() . DIRECTORY_SEPARATOR . 'worker-state.json';
}

function railshot_worker_read_json(string $file): ?array
{
    if (!file_exists($file)) {
        return null;
    }
    $raw = file_get_contents($file) ?: '';
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/** @return array{alive:bool,ageSec:int|null,pid:int|null} */
function railshot_stream_worker_status(int $maxAgeSec = 15): array
{
    $data = railshot_worker_read_json(railshot_worker_heartbeat_file());
    if ($data === null) {
        return ['alive' => false, 'ageSec' => null, 'pid' => null];
    }
    $ts = (int) ($data['ts'] ?? 0);
    $age = $ts > 0 ? max(0, time() - $ts) : 9999;
    return [
        'alive' => $age <= $maxAgeSec,
        'ageSec' => $age,
        'pid' => isset($data['pid']) ? (int) $data['pid'] : null,
    ];
}

function railshot_stream_worker_alive(int $maxAgeSec = 15): bool
{
    return railshot_stream_worker_status($maxAgeSec)['alive'];
}

/**
 * Send a command to the stream worker and wait for its result.
 *
 * @return array{ok:bool,error?:string,action?:string,tableId?:string,sourcePort?:string,pid?:int,workerAlive?:bool}
 */
function railshot_stream_worker_send_command(string $action, string $tableId = '', int $timeoutMs = 35000): array
{
    if (!railshot_stream_worker_alive()) {
        return [
            'ok' => false,
            'error' => 'Stream worker is not running. On the VPS, right-click streaming/install-stream-worker.bat and Run as administrator (one time).',
            'workerAlive' => false,
        ];
    }

    $id = 'cmd-' . bin2hex(random_bytes(8));
    $cmd = [
        'id' => $id,
        'action' => $action,
        'tableId' => $tableId,
        'issuedAt' => time(),
    ];

    $json = json_encode($cmd, JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents(railshot_worker_command_file(), $json, LOCK_EX) === false) {
        return ['ok' => false, 'error' => 'Could not write stream command'];
    }

    $resultFile = railshot_worker_result_file();
    $elapsed = 0;
    while ($elapsed < $timeoutMs) {
        if (file_exists($resultFile)) {
            $result = railshot_worker_read_json($resultFile);
            if ($result !== null && ($result['id'] ?? '') === $id) {
                $result['id'] = $id;
                return $result;
            }
        }
        usleep(250000);
        $elapsed += 250;
    }

    return [
        'ok' => false,
        'error' => 'Stream worker did not respond in time. Check streaming/worker.log on the VPS.',
        'workerAlive' => railshot_stream_worker_alive(),
    ];
}
