<?php
// TEMPORARY DEBUG FILE — DELETE AFTER FIXING
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo "<pre>";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "RAILSHOT_ROOT would be: " . dirname(__DIR__) . "\n";
echo "App_Data path: " . dirname(__DIR__) . DIRECTORY_SEPARATOR . 'App_Data' . DIRECTORY_SEPARATOR . 'railshot' . "\n";

$dataDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'App_Data' . DIRECTORY_SEPARATOR . 'railshot';
echo "App_Data exists: " . (is_dir($dataDir) ? 'YES' : 'NO') . "\n";
echo "App_Data readable: " . (is_readable($dataDir) ? 'YES' : 'NO') . "\n";
echo "App_Data writable: " . (is_writable($dataDir) ? 'YES' : 'NO') . "\n";

$configFile = $dataDir . DIRECTORY_SEPARATOR . 'config.json';
echo "config.json exists: " . (file_exists($configFile) ? 'YES' : 'NO') . "\n";
echo "config.json readable: " . (is_readable($configFile) ? 'YES' : 'NO') . "\n";

if (file_exists($configFile)) {
    $raw = file_get_contents($configFile);
    $data = json_decode($raw, true);
    echo "config.json valid JSON: " . ($data !== null ? 'YES' : 'NO') . "\n";
    echo "Venues count: " . count($data['live']['venues'] ?? []) . "\n";
}

echo "\nSession test:\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "Session started: YES\n";

echo "\nTrying to load bootstrap...\n";
require_once __DIR__ . '/bootstrap.php';
echo "Bootstrap loaded: YES\n";

$config = railshot_load_config();
echo "Config loaded: YES\n";
echo "Venues: " . count($config['live']['venues'] ?? []) . "\n";
echo "</pre>";
