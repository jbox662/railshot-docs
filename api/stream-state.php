<?php
/**
 * RailShot TV — persisted desired stream state (which table should be live).
 */

require_once __DIR__ . '/bootstrap.php';

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

function railshot_stream_live_table_id(): string
{
    foreach (railshot_stream_load_state()['tables'] as $tableId => $desired) {
        if ($desired === 'live') {
            return $tableId;
        }
    }
    return '';
}
