<?php
require_once dirname(__DIR__, 2) . '/api/bootstrap.php';

if (!railshot_admin_exists()) {
    railshot_json_response(['error' => 'Admin not configured'], 503);
}

railshot_require_login_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    railshot_json_response(['error' => 'Method not allowed'], 405);
}

$body = railshot_read_json_body();
$section = $body['section'] ?? '';
$config = railshot_load_config();

if ($section === 'live') {
    $venues = [];
    foreach ($body['venues'] ?? [] as $venue) {
        if (!is_array($venue)) {
            continue;
        }
        $venueId = railshot_sanitize_venue_id($venue['id'] ?? '');
        $venueName = trim($venue['name'] ?? '');
        if ($venueId === '' || $venueName === '') {
            continue;
        }

        $tables = [];
        foreach ($venue['tables'] ?? [] as $table) {
            $id = railshot_sanitize_table_id($table['id'] ?? '');
            $name = trim($table['name'] ?? '');
            if ($id === '' || $name === '') {
                continue;
            }
            $tables[] = [
                'id' => $id,
                'name' => $name,
                'description' => trim($table['description'] ?? ''),
                'rtspUrl' => trim($table['rtspUrl'] ?? ''),
            ];
        }

        $tableIds = array_column($tables, 'id');
        $activeTableId = railshot_sanitize_table_id($venue['activeTableId'] ?? '');
        if ($activeTableId === '' || !in_array($activeTableId, $tableIds, true)) {
            $activeTableId = $tableIds[0] ?? '';
        }

        $venues[] = [
            'id' => $venueId,
            'name' => $venueName,
            'location' => trim($venue['location'] ?? ''),
            'description' => trim($venue['description'] ?? ''),
            'tagline' => trim($venue['tagline'] ?? ''),
            'image' => trim($venue['image'] ?? '/images/logo.png') ?: '/images/logo.png',
            'activeTableId' => $activeTableId,
            'tables' => $tables,
            'overlayEnabled' => !empty($venue['overlayEnabled']),
            'overlayUrl' => trim($venue['overlayUrl'] ?? ''),
        ];
    }

    $landing = $body['landing'] ?? [];
    $bullets = [];
    foreach ($landing['bullets'] ?? [] as $bullet) {
        $bullet = trim((string) $bullet);
        if ($bullet !== '') {
            $bullets[] = $bullet;
        }
    }

    $config['live'] = [
        'mediamtxHost' => trim($body['mediamtxHost'] ?? '160.153.184.255'),
        'useHttpsProxy' => !empty($body['useHttpsProxy']),
        'preferredProtocol' => in_array($body['preferredProtocol'] ?? '', ['hls', 'webrtc'], true)
            ? $body['preferredProtocol']
            : 'hls',
        'landing' => [
            'headline' => trim($landing['headline'] ?? ''),
            'subtitle' => trim($landing['subtitle'] ?? ''),
            'bullets' => $bullets,
        ],
        'venues' => $venues,
    ];

    if (!railshot_save_config($config)) {
        railshot_json_response(['error' => 'Failed to save config'], 500);
    }

    railshot_json_response([
        'ok' => true,
        'mediamtxYaml' => railshot_generate_mediamtx_yaml(railshot_collect_all_tables($config['live'])),
    ]);
}

if ($section === 'site') {
    $config['site'] = [
        'heroTitle' => trim($body['heroTitle'] ?? ''),
        'heroSubtitle' => trim($body['heroSubtitle'] ?? ''),
        'contactEmail' => trim($body['contactEmail'] ?? ''),
        'downloadNote' => trim($body['downloadNote'] ?? ''),
    ];

    if (!railshot_save_config($config)) {
        railshot_json_response(['error' => 'Failed to save config'], 500);
    }

    railshot_json_response(['ok' => true]);
}

if ($section === 'password') {
    $admin = railshot_load_admin();
    $current = $body['currentPassword'] ?? '';
    $new = $body['newPassword'] ?? '';
    $confirm = $body['confirmPassword'] ?? '';

    if (!password_verify($current, $admin['passwordHash'] ?? '')) {
        railshot_json_response(['error' => 'Current password is incorrect'], 400);
    }
    if (strlen($new) < 8) {
        railshot_json_response(['error' => 'New password must be at least 8 characters'], 400);
    }
    if ($new !== $confirm) {
        railshot_json_response(['error' => 'New passwords do not match'], 400);
    }

    $admin['passwordHash'] = password_hash($new, PASSWORD_DEFAULT);
    file_put_contents(RAILSHOT_ADMIN_FILE, json_encode($admin, JSON_PRETTY_PRINT));
    railshot_json_response(['ok' => true]);
}

railshot_json_response(['error' => 'Unknown section'], 400);
