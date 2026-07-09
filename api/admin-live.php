<?php
/**
 * RailShot TV — Admin live control API
 * GET  /api/admin-live.php?venue=<id>  → returns { isAdmin, tables, activeTableId }
 * POST /api/admin-live.php             → { venue, tableId } → switches active table
 */

require_once dirname(__DIR__) . '/api/bootstrap.php';

$isAdmin = railshot_is_logged_in();

// ── GET: return admin status + all tables for the venue ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$isAdmin) {
        railshot_json_response(['isAdmin' => false]);
    }

    $config = railshot_load_config();
    $live   = $config['live'] ?? [];
    $venues = $live['venues'] ?? [];

    $venueId = trim($_GET['venue'] ?? '');
    $venue   = null;
    foreach ($venues as $v) {
        if (($v['id'] ?? '') === $venueId) {
            $venue = $v;
            break;
        }
    }
    if ($venue === null && count($venues) > 0) {
        $venue = $venues[0];
    }

    if ($venue === null) {
        railshot_json_response(['isAdmin' => true, 'tables' => [], 'activeTableId' => '']);
    }

    // Return ALL tables (not just the active one) so admin can pick any
    $tables = [];
    foreach ($venue['tables'] ?? [] as $table) {
        if (empty($table['id']) || empty($table['name'])) {
            continue;
        }
        $tables[] = [
            'id'         => $table['id'],
            'name'       => $table['name'],
            'overlayUrl' => trim($table['overlayUrl'] ?? ''),
        ];
    }

    railshot_json_response([
        'isAdmin'       => true,
        'tables'        => $tables,
        'activeTableId' => $venue['activeTableId'] ?? ($tables[0]['id'] ?? ''),
    ]);
}

// ── POST: switch active table ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        railshot_json_response(['error' => 'Unauthorized'], 401);
    }

    $body    = railshot_read_json_body();
    $venueId = trim($body['venue'] ?? '');
    $tableId = railshot_sanitize_table_id($body['tableId'] ?? '');

    if ($tableId === '') {
        railshot_json_response(['error' => 'tableId required'], 400);
    }

    $config = railshot_load_config();
    $found  = false;

    foreach ($config['live']['venues'] ?? [] as &$venue) {
        $matchVenue = ($venueId === '' || ($venue['id'] ?? '') === $venueId);
        if (!$matchVenue) {
            continue;
        }
        // Verify the tableId actually exists in this venue
        $tableIds = array_column($venue['tables'] ?? [], 'id');
        if (in_array($tableId, $tableIds, true)) {
            $venue['activeTableId'] = $tableId;
            $found = true;
            break;
        }
    }
    unset($venue);

    if (!$found) {
        railshot_json_response(['error' => 'Table not found in venue'], 404);
    }

    if (!railshot_save_config($config)) {
        railshot_json_response(['error' => 'Failed to save config'], 500);
    }

    railshot_json_response(['ok' => true, 'activeTableId' => $tableId]);
}

railshot_json_response(['error' => 'Method not allowed'], 405);
