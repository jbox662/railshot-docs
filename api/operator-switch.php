<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/stream-engine.php';

railshot_require_operator_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    railshot_json_response(['error' => 'Method not allowed'], 405);
}

$body = railshot_read_json_body();
$tableId = trim($body['tableId'] ?? '');
$venueId = trim($body['venueId'] ?? '');

$config = railshot_load_config();
$live = $config['live'] ?? [];
$venues = railshot_normalize_venues($live);

// Find the venue
$venueIndex = null;
for ($i = 0; $i < count($venues); $i++) {
    if ($venueId === '' || $venues[$i]['id'] === $venueId) {
        $venueIndex = $i;
        break;
    }
}

if ($venueIndex === null) {
    railshot_json_response(['error' => 'Venue not found'], 404);
}

$venue = $venues[$venueIndex];
$tableIds = array_column($venue['tables'] ?? [], 'id');

// '__none__' is a sentinel meaning "stop / go off air"
if ($tableId === '__none__') {
    $newActiveId = '';
} else {
    $sanitized = railshot_sanitize_table_id($tableId);
    if (!in_array($sanitized, $tableIds, true)) {
        railshot_json_response(['error' => 'Table not found in venue'], 404);
    }
    $newActiveId = $sanitized;
}

// Update config
$config['live']['venues'][$venueIndex]['activeTableId'] = $newActiveId;

if (!railshot_save_config($config)) {
    railshot_json_response(['error' => 'Failed to save config'], 500);
}

$streamResult = railshot_stream_apply_active_table($newActiveId);

railshot_json_response([
    'ok' => true,
    'activeTableId' => $newActiveId,
    'stream' => $streamResult,
]);
