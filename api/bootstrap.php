<?php
/**
 * RailShot TV — shared API / admin bootstrap
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('RAILSHOT_ROOT', dirname(__DIR__));
define('RAILSHOT_DATA', RAILSHOT_ROOT . DIRECTORY_SEPARATOR . 'App_Data' . DIRECTORY_SEPARATOR . 'railshot');
define('RAILSHOT_CONFIG_FILE', RAILSHOT_DATA . DIRECTORY_SEPARATOR . 'config.json');
define('RAILSHOT_ADMIN_FILE', RAILSHOT_DATA . DIRECTORY_SEPARATOR . 'admin.json');

function railshot_ensure_data_dir(): void
{
    if (!is_dir(RAILSHOT_DATA)) {
        mkdir(RAILSHOT_DATA, 0755, true);
    }
}

function railshot_default_config(): array
{
    return [
        'live' => [
            'mediamtxHost' => '160.153.184.255',
            'useHttpsProxy' => true,
            'preferredProtocol' => 'hls',
            'landing' => [
                'headline' => 'Watch Live Billiard Action',
                'subtitle' => 'Stream tournament tables, league nights, and hall favorites from venues powered by RailShot TV — free for viewers, professional quality for operators.',
                'bullets' => [
                    'Multiple tables streaming from your favorite venue',
                    'Switch cameras instantly — controlled by the hall',
                    'Works in your browser — no app required',
                ],
            ],
            'venues' => [
                [
                    'id' => 'main-hall',
                    'name' => 'Main Hall',
                    'location' => 'Your Venue',
                    'description' => 'Watch live billiard tables from our main streaming venue.',
                    'tagline' => 'Live now',
                    'image' => '/images/logo.png',
                    'activeTableId' => 'table1',
                    'tables' => [
                        [
                            'id' => 'table1',
                            'name' => 'Test Camera',
                            'description' => '192.168.68.89',
                            'rtspUrl' => 'rtsp://admin:CHANGE_ME@140.106.76.67:8554/h264Preview_01_main',
                        ],
                    ],
                ],
            ],
            // Legacy fields kept for migration from older configs:
            'activeTableId' => 'table1',
            'tables' => [],
        ],
        'site' => [
            'heroTitle' => 'Professional Billiard Livestreaming',
            'heroSubtitle' => 'Stream your billiard matches to YouTube and Facebook with professional HD/4K quality. Go live in seconds with instant game setup and remote scoreboard control. Requires iOS 18 or higher.',
            'contactEmail' => 'support@railshottv.com',
            'downloadNote' => 'Requires iOS 18 or higher',
        ],
    ];
}

function railshot_config_file_path(): string
{
    railshot_ensure_data_dir();
    if (file_exists(RAILSHOT_CONFIG_FILE)) {
        return RAILSHOT_CONFIG_FILE;
    }
    $fallback = RAILSHOT_ROOT . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'railshot-config.json';
    if (file_exists($fallback)) {
        return $fallback;
    }
    return RAILSHOT_CONFIG_FILE;
}

function railshot_load_config(): array
{
    railshot_ensure_data_dir();
    $configFile = railshot_config_file_path();

    if (!file_exists($configFile)) {
        $defaults = railshot_default_config();
        file_put_contents(
            $configFile,
            json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        return $defaults;
    }

    $raw = file_get_contents($configFile);
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        return railshot_default_config();
    }

    return array_replace_recursive(railshot_default_config(), $data);
}

function railshot_save_config(array $config): bool
{
    railshot_ensure_data_dir();
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    $written = @file_put_contents(RAILSHOT_CONFIG_FILE, $json, LOCK_EX);
    if ($written !== false) {
        return true;
    }
    $fallback = RAILSHOT_ROOT . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'railshot-config.json';
    $fallbackDir = dirname($fallback);
    if (!is_dir($fallbackDir)) {
        mkdir($fallbackDir, 0755, true);
    }
    return file_put_contents($fallback, $json, LOCK_EX) !== false;
}

function railshot_admin_exists(): bool
{
    return file_exists(RAILSHOT_ADMIN_FILE);
}

function railshot_load_admin(): ?array
{
    if (!railshot_admin_exists()) {
        return null;
    }
    $data = json_decode(file_get_contents(RAILSHOT_ADMIN_FILE) ?: '', true);
    return is_array($data) ? $data : null;
}

function railshot_create_admin(string $password): bool
{
    railshot_ensure_data_dir();
    $payload = [
        'username' => 'admin',
        'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
        'createdAt' => gmdate('c'),
    ];
    return file_put_contents(
        RAILSHOT_ADMIN_FILE,
        json_encode($payload, JSON_PRETTY_PRINT)
    ) !== false;
}

function railshot_is_logged_in(): bool
{
    return !empty($_SESSION['railshot_admin']);
}

function railshot_require_login(): void
{
    if (!railshot_is_logged_in()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function railshot_require_login_api(): void
{
    if (!railshot_is_logged_in()) {
        railshot_json_response(['error' => 'Unauthorized'], 401);
    }
}

function railshot_attempt_login(string $username, string $password): bool
{
    $admin = railshot_load_admin();
    if (!$admin) {
        return false;
    }
    $expectedUser = $admin['username'] ?? 'admin';
    if ($username !== $expectedUser) {
        return false;
    }
    if (!password_verify($password, $admin['passwordHash'] ?? '')) {
        return false;
    }
    $_SESSION['railshot_admin'] = $expectedUser;
    return true;
}

function railshot_logout(): void
{
    unset($_SESSION['railshot_admin']);
}

function railshot_json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function railshot_build_live_urls(array $liveConfig): array
{
    $host = trim($liveConfig['mediamtxHost'] ?? '160.153.184.255');
    $useProxy = !empty($liveConfig['useHttpsProxy']);
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if ($useProxy && $isHttps && !empty($_SERVER['HTTP_HOST'])) {
        $origin = 'https://' . $_SERVER['HTTP_HOST'];
        return [
            'webrtcBaseUrl' => $origin . '/api/webrtc.php?path=',
            'hlsBaseUrl' => $origin . '/api/hls.php?path=',
            'preferredProtocol' => $liveConfig['preferredProtocol'] ?? 'hls',
        ];
    }

    return [
        'webrtcBaseUrl' => 'http://' . $host . ':8889',
        'hlsBaseUrl' => 'http://' . $host . ':8888',
        'preferredProtocol' => $liveConfig['preferredProtocol'] ?? 'webrtc',
    ];
}

function railshot_sanitize_venue_id(string $id): string
{
    $id = strtolower(trim($id));
    $id = preg_replace('/[^a-z0-9_-]+/', '', $id) ?? '';
    return $id;
}

function railshot_normalize_venues(array $live): array
{
    if (!empty($live['venues']) && is_array($live['venues'])) {
        $venues = [];
        foreach ($live['venues'] as $venue) {
            if (!is_array($venue)) {
                continue;
            }
            $id = railshot_sanitize_venue_id($venue['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $tables = [];
            foreach ($venue['tables'] ?? [] as $table) {
                if (!is_array($table) || empty($table['id']) || empty($table['name'])) {
                    continue;
                }
                $tables[] = $table;
            }
            $tableIds = array_map(static fn(array $t): string => railshot_sanitize_table_id($t['id'] ?? ''), $tables);
            $activeTableId = railshot_sanitize_table_id($venue['activeTableId'] ?? '');
            if ($activeTableId === '' || !in_array($activeTableId, $tableIds, true)) {
                $activeTableId = $tableIds[0] ?? '';
            }
            $venues[] = array_merge($venue, [
                'id' => $id,
                'tables' => $tables,
                'activeTableId' => $activeTableId,
            ]);
        }
        if ($venues !== []) {
            return $venues;
        }
    }

    $legacyTables = $live['tables'] ?? [];
    if ($legacyTables === []) {
        return railshot_default_config()['live']['venues'];
    }

    return [[
        'id' => 'main-hall',
        'name' => 'Main Hall',
        'location' => 'Your Venue',
        'description' => 'Watch live billiard tables from our main streaming venue.',
        'tagline' => 'Live now',
        'image' => '/images/logo.png',
        'activeTableId' => $live['activeTableId'] ?? 'table1',
        'tables' => $legacyTables,
    ]];
}

function railshot_find_venue(array $live, string $venueId): ?array
{
    $venueId = railshot_sanitize_venue_id($venueId);
    foreach (railshot_normalize_venues($live) as $venue) {
        if (($venue['id'] ?? '') === $venueId) {
            return $venue;
        }
    }
    return null;
}

function railshot_collect_all_tables(array $live): array
{
    $tables = [];
    $seen = [];
    foreach (railshot_normalize_venues($live) as $venue) {
        foreach ($venue['tables'] ?? [] as $table) {
            $id = railshot_sanitize_table_id($table['id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $tables[] = $table;
        }
    }
    return $tables;
}

function railshot_public_venues_config(): array
{
    $config = railshot_load_config();
    $live = $config['live'] ?? [];
    $landing = $live['landing'] ?? railshot_default_config()['live']['landing'];

    $venues = [];
    foreach (railshot_normalize_venues($live) as $venue) {
        $tables = [];
        foreach ($venue['tables'] ?? [] as $table) {
            if (empty($table['id']) || empty($table['name'])) {
                continue;
            }
            $tables[] = [
                'id' => $table['id'],
                'name' => $table['name'],
            ];
        }
        $activeId = railshot_sanitize_table_id($venue['activeTableId'] ?? '');
        $isLive = false;
        foreach ($tables as $table) {
            if ($table['id'] === $activeId) {
                $isLive = true;
                break;
            }
        }
        $venues[] = [
            'id' => $venue['id'],
            'name' => $venue['name'] ?? $venue['id'],
            'location' => $venue['location'] ?? '',
            'description' => $venue['description'] ?? '',
            'tagline' => $venue['tagline'] ?? '',
            'image' => $venue['image'] ?? '/images/logo.png',
            'tableCount' => count($tables),
            'isLive' => $isLive,
        ];
    }

    return [
        'landing' => [
            'headline' => $landing['headline'] ?? '',
            'subtitle' => $landing['subtitle'] ?? '',
            'bullets' => array_values(array_filter($landing['bullets'] ?? [], static fn($b) => trim((string) $b) !== '')),
        ],
        'venues' => $venues,
    ];
}

function railshot_public_live_config(?string $venueId = null): array
{
    $config = railshot_load_config();
    $live = $config['live'] ?? [];
    $urls = railshot_build_live_urls($live);

    $venues = railshot_normalize_venues($live);
    $venue = null;
    if ($venueId !== null && $venueId !== '') {
        $venue = railshot_find_venue($live, $venueId);
    }
    if ($venue === null) {
        $venue = $venues[0] ?? null;
    }

    $allTables = [];
    foreach ($venue['tables'] ?? [] as $table) {
        if (empty($table['id']) || empty($table['name'])) {
            continue;
        }
        $allTables[] = [
            'id' => $table['id'],
            'name' => $table['name'],
            // description intentionally omitted — it often contains the camera IP
        ];
    }

    $activeId = railshot_sanitize_table_id($venue['activeTableId'] ?? '');
    $activeTable = null;
    foreach ($allTables as $table) {
        if ($table['id'] === $activeId) {
            $activeTable = $table;
            break;
        }
    }
    if ($activeTable === null && $allTables !== []) {
        $activeTable = $allTables[0];
    }

    $publicTables = $activeTable !== null ? [$activeTable] : [];

    return array_merge($urls, [
        'venueId' => $venue['id'] ?? '',
        'venueName' => $venue['name'] ?? '',
        'tables' => $publicTables,
        'viewerLocked' => true,
        'activeTableId' => $activeTable['id'] ?? '',
        'overlayEnabled' => !empty($venue['overlayEnabled']),
        'overlayUrl' => $venue['overlayUrl'] ?? '',
    ]);
}

function railshot_sanitize_table_id(string $id): string
{
    $id = strtolower(trim($id));
    $id = preg_replace('/[^a-z0-9_-]+/', '', $id) ?? '';
    return $id;
}

function railshot_generate_mediamtx_yaml(array $tables): string
{
    $lines = ["paths:"];
    foreach ($tables as $table) {
        $id = railshot_sanitize_table_id($table['id'] ?? '');
        $rtsp = trim($table['rtspUrl'] ?? '');
        if ($id === '' || $rtsp === '') {
            continue;
        }
        $lines[] = "  {$id}:";
        $lines[] = "    source: {$rtsp}";
        $lines[] = "    sourceOnDemand: yes";
        $lines[] = "";
    }
    return implode("\n", $lines) . "\n";
}

function railshot_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : [];
}
