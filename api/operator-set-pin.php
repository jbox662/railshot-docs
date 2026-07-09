<?php
require_once __DIR__ . '/bootstrap.php';

railshot_require_login_api(); // Only full admins can set the operator PIN

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    railshot_json_response(['error' => 'Method not allowed'], 405);
}

$body = railshot_read_json_body();
$newPin = trim($body['newPin'] ?? '');
$confirmPin = trim($body['confirmPin'] ?? '');

if (strlen($newPin) < 4) {
    railshot_json_response(['error' => 'PIN must be at least 4 digits'], 400);
}

if ($newPin !== $confirmPin) {
    railshot_json_response(['error' => 'PINs do not match'], 400);
}

if (!railshot_save_operator($newPin)) {
    railshot_json_response(['error' => 'Failed to save PIN'], 500);
}

railshot_json_response(['ok' => true]);
