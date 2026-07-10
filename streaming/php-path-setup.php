<?php
/**
 * One-time setup: save the PHP path for the SYSTEM stream worker.
 * Open while logged into admin, then re-run install-stream-worker.bat.
 */
require_once dirname(__DIR__) . '/api/bootstrap.php';

if (!railshot_admin_exists()) {
    http_response_code(403);
    echo 'Admin not configured.';
    exit;
}
railshot_require_login();

header('Content-Type: text/html; charset=utf-8');

$candidates = [];
$seen = [];

$add = static function (?string $path) use (&$candidates, &$seen): void {
    $path = trim(str_replace(['/', '"'], ['\\', ''], (string) $path));
    if ($path === '' || isset($seen[strtolower($path)])) {
        return;
    }
    $seen[strtolower($path)] = true;
    $candidates[] = $path;
};

if (defined('PHP_BINARY')) {
    $add(PHP_BINARY);
}
if (defined('PHP_BINDIR')) {
    $add(PHP_BINDIR . DIRECTORY_SEPARATOR . 'php.exe');
}

exec('where php 2>nul', $whereOut);
foreach ($whereOut as $line) {
    $add(trim($line));
}

foreach ([
    'C:\\Program Files (x86)\\Plesk\\Additional\\Plesk PHP',
    'C:\\Program Files\\Plesk\\Additional\\Plesk PHP',
] as $base) {
    if (!is_dir($base)) {
        continue;
    }
    foreach (glob($base . '\\*\\php.exe') ?: [] as $path) {
        $add($path);
    }
}

$chosen = null;
foreach ($candidates as $path) {
    if (!is_file($path)) {
        continue;
    }
    if (stripos($path, 'php-cgi') !== false) {
        continue;
    }
    $chosen = $path;
    break;
}
if ($chosen === null) {
    foreach ($candidates as $path) {
        if (is_file($path)) {
            $chosen = $path;
            break;
        }
    }
}

$outFile = __DIR__ . DIRECTORY_SEPARATOR . 'stream-worker-php.txt';
$written = false;
if ($chosen !== null) {
    $written = file_put_contents($outFile, $chosen . "\n", LOCK_EX) !== false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stream worker PHP setup</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; background: #111; color: #eee; max-width: 720px; }
        h1 { color: #00A8E8; }
        .ok { color: #4ade80; }
        .bad { color: #f87171; }
        code { background: #222; padding: 2px 6px; border-radius: 4px; }
        li { margin: 0.35rem 0; }
        a { color: #00A8E8; }
    </style>
</head>
<body>
    <h1>Stream worker PHP setup</h1>
    <p><a href="/admin/">&larr; Admin</a></p>

    <?php if ($written): ?>
        <p class="ok"><strong>Saved PHP path for the worker:</strong><br><code><?php echo htmlspecialchars($chosen); ?></code></p>
        <p>Next on the VPS:</p>
        <ol>
            <li>Right-click <code>streaming\install-stream-worker.bat</code> &rarr; <strong>Run as administrator</strong></li>
            <li>Open <a href="/admin/stream-status.php">stream status</a> — Worker running should be <strong>YES</strong></li>
        </ol>
    <?php else: ?>
        <p class="bad">Could not find a usable <code>php.exe</code> on this server.</p>
        <p>Run <code>streaming\find-php.bat</code> on the VPS, or paste the full path to php.exe into <code>streaming\stream-worker-php.txt</code> (one line).</p>
    <?php endif; ?>

    <h3>Paths checked</h3>
    <ul>
        <?php foreach ($candidates as $path): ?>
            <li class="<?php echo is_file($path) ? 'ok' : 'bad'; ?>"><?php echo htmlspecialchars($path); ?></li>
        <?php endforeach; ?>
        <?php if ($candidates === []): ?>
            <li class="bad">No candidates found</li>
        <?php endif; ?>
    </ul>
</body>
</html>
