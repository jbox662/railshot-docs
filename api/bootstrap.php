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
            'tables' => [
                [
                    'id' => 'table1',
                    'name' => 'Test Camera',
                    'description' => '192.168.68.89',
                    'rtspUrl' => 'rtsp://admin:CHANGE_ME@140.106.76.67:8554/h264Preview_01_main',
                ],
            ],
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
            'webrtcBaseUrl' => $origin . '/live-webrtc',
            'hlsBaseUrl' => $origin . '/live-hls',
            'preferredProtocol' => $liveConfig['preferredProtocol'] ?? 'hls',
        ];
    }

    return [
        'webrtcBaseUrl' => 'http://' . $host . ':8889',
        'hlsBaseUrl' => 'http://' . $host . ':8888',
        'preferredProtocol' => $liveConfig['preferredProtocol'] ?? 'webrtc',
    ];
}

function railshot_public_live_config(): array
{
    $config = railshot_load_config();
    $live = $config['live'] ?? [];
    $urls = railshot_build_live_urls($live);

    $tables = [];
    foreach ($live['tables'] ?? [] as $table) {
        if (empty($table['id']) || empty($table['name'])) {
            continue;
        }
        $tables[] = [
            'id' => $table['id'],
            'name' => $table['name'],
            'description' => $table['description'] ?? '',
        ];
    }

    return array_merge($urls, ['tables' => $tables]);
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
