<?php
require_once dirname(__DIR__) . '/api/bootstrap.php';

if (!railshot_admin_exists()) {
    header('Location: /admin/setup.php');
    exit;
}

if (railshot_is_logged_in()) {
    header('Location: /admin/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? 'admin');
    $password = $_POST['password'] ?? '';

    if (railshot_attempt_login($username, $password)) {
        header('Location: /admin/index.php');
        exit;
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RailShot Admin — Login</title>
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body class="admin-page">
    <main class="admin-auth-card">
        <h1>RailShot Admin</h1>
        <p>Sign in to manage live cameras and site content.</p>
        <?php if ($error): ?><p class="admin-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="post" class="admin-form">
            <label>Username <input type="text" name="username" value="admin" required autocomplete="username"></label>
            <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
            <button type="submit" class="btn btn-primary">Sign in</button>
        </form>
    </main>
</body>
</html>
