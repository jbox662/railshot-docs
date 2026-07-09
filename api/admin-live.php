<?php
/**
 * RailShot TV — Admin live control API
 * GET  /api/admin-live.php?venue=<id>  → { isAdmin, tables, activeTableId }
 * POST /api/admin-live.php             → { venue, tableId }
 *   tableId = '<id>'     → switch to that table (Go Live)
 *   tableId = '__none__' → stop stream (Off Air)
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

    // Return ALL tables so admin can pick any
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
        'activeTableId' => $venue['activeTableId'] ?? '',
    ]);
}

// ── POST: switch active table or stop stream ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        railshot_json_response(['error' => 'Unauthorized'], 401);
    }

    $body    = railshot_read_json_body();
    $venueId = trim($body['venue'] ?? '');
    $tableId = trim($body['tableId'] ?? '');

    // '__none__' sentinel = stop the stream (set activeTableId to '')
    if ($tableId === '__none__') {
        $newActiveId = '';
    } else {
        $newActiveId = railshot_sanitize_table_id($tableId);
        if ($newActiveId === '') {
            railshot_json_response(['error' => 'tableId required'], 400);
        }
    }

    $config = railshot_load_config();
    $found  = false;

    foreach ($config['live']['venues'] ?? [] as &$venue) {
        $matchVenue = ($venueId === '' || ($venue['id'] ?? '') === $venueId);
        if (!$matchVenue) {
            continue;
        }

        // For a real table switch, verify the ID exists in this venue
        if ($newActiveId !== '') {
            $tableIds = array_column($venue['tables'] ?? [], 'id');
            if (!in_array($newActiveId, $tableIds, true)) {
                continue; // try next venue
            }
        }

        $venue['activeTableId'] = $newActiveId;
        $found = true;
        break;
    }
    unset($venue);

    if (!$found) {
        railshot_json_response(['error' => 'Venue or table not found'], 404);
    }

    if (!railshot_save_config($config)) {
        railshot_json_response(['error' => 'Failed to save config'], 500);
    }

    railshot_json_response(['ok' => true, 'activeTableId' => $newActiveId]);
}

railshot_json_response(['error' => 'Method not allowed'], 405);
