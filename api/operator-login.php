<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    railshot_json_response(['error' => 'Method not allowed'], 405);
}

$body = railshot_read_json_body();
$pin = trim($body['pin'] ?? '');

if ($pin === '') {
    railshot_json_response(['error' => 'PIN is required'], 400);
}

// If no operator PIN has been set yet, fall back to admin password check
if (!railshot_operator_exists()) {
    // Try admin password as a fallback
    $admin = railshot_load_admin();
    if ($admin && password_verify($pin, $admin['passwordHash'] ?? '')) {
        $_SESSION['railshot_operator'] = true;
        railshot_json_response(['ok' => true]);
    }
    railshot_json_response(['error' => 'No operator PIN configured. Please set one in the Admin Panel.'], 401);
}

if (railshot_attempt_operator_login($pin)) {
    railshot_json_response(['ok' => true]);
}

railshot_json_response(['error' => 'Incorrect PIN. Please try again.'], 401);
