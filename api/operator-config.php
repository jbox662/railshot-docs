<?php
require_once __DIR__ . '/bootstrap.php';

railshot_require_operator_api();

$params = $_GET;
$venueId = trim($params['venue'] ?? '');

$config = railshot_load_config();
$live = $config['live'] ?? [];
$venues = railshot_normalize_venues($live);

$venue = null;
if ($venueId !== '') {
    $venue = railshot_find_venue($live, $venueId);
}
if ($venue === null) {
    $venue = $venues[0] ?? null;
}

if ($venue === null) {
    railshot_json_response(['ok' => true, 'tables' => [], 'activeTableId' => null, 'venueName' => '']);
}

$venueOverlayUrl = trim($venue['overlayUrl'] ?? '');
$tables = [];
foreach ($venue['tables'] ?? [] as $table) {
    if (empty($table['id']) || empty($table['name'])) {
        continue;
    }
    $tableOverlayUrl = trim($table['overlayUrl'] ?? '');
    $tables[] = [
        'id'         => $table['id'],
        'name'       => $table['name'],
        'youtubeUrl' => trim($table['youtubeUrl'] ?? ''),
        'rtspUrl'    => !empty($table['rtspUrl']) ? '(configured)' : '', // don't expose real RTSP URL
        'overlayUrl' => $tableOverlayUrl !== '' ? $tableOverlayUrl : $venueOverlayUrl,
    ];
}

railshot_json_response([
    'ok'            => true,
    'venueName'     => $venue['name'] ?? '',
    'venueId'       => $venue['id'] ?? '',
    'activeTableId' => railshot_sanitize_table_id($venue['activeTableId'] ?? ''),
    'tables'        => $tables,
]);
