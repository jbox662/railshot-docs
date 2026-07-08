<?php
require_once dirname(__DIR__) . '/api/bootstrap.php';

if (railshot_admin_exists()) {
    header('Location: /admin/login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (railshot_create_admin($password)) {
        railshot_attempt_login('admin', $password);
        header('Location: /admin/index.php');
        exit;
    } else {
        $error = 'Could not save admin account.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RailShot Admin — Setup</title>
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body class="admin-page">
    <main class="admin-auth-card">
        <h1>RailShot Admin Setup</h1>
        <p>Create your admin password. Username will be <strong>admin</strong>.</p>
        <?php if ($error): ?><p class="admin-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="post" class="admin-form">
            <label>Password <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
            <label>Confirm password <input type="password" name="confirm" required minlength="8" autocomplete="new-password"></label>
            <button type="submit" class="btn btn-primary">Create admin account</button>
        </form>
    </main>
</body>
</html>
