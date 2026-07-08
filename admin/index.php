<?php
require_once dirname(__DIR__) . '/api/bootstrap.php';

if (!railshot_admin_exists()) {
    header('Location: /admin/setup.php');
    exit;
}

railshot_require_login();

$config = railshot_load_config();
$live = $config['live'] ?? [];
$site = $config['site'] ?? [];
$mediamtxYaml = railshot_generate_mediamtx_yaml($live['tables'] ?? []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RailShot Admin</title>
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body class="admin-page">
    <header class="admin-header">
        <div class="admin-header-inner">
            <h1>RailShot Admin</h1>
            <div class="admin-header-actions">
                <a href="/live.html" target="_blank" class="admin-link">View Live</a>
                <a href="/" target="_blank" class="admin-link">View Site</a>
                <a href="/admin/logout.php" class="admin-link">Logout</a>
            </div>
        </div>
    </header>

    <main class="admin-main">
        <div id="adminMessage" class="admin-message hidden" role="status"></div>

        <section class="admin-panel">
            <h2>Live cameras / tables</h2>
            <p class="admin-hint">Each table <code>id</code> must match a path in MediaMTX on your VPS. RTSP URLs are stored here for admin use only.</p>

            <form id="liveForm" class="admin-form-grid">
                <label>MediaMTX host (VPS IP)
                    <input type="text" name="mediamtxHost" value="<?= htmlspecialchars($live['mediamtxHost'] ?? '') ?>" required>
                </label>
                <label>Preferred protocol
                    <select name="preferredProtocol">
                        <option value="hls" <?= ($live['preferredProtocol'] ?? '') === 'hls' ? 'selected' : '' ?>>HLS (best for HTTPS site)</option>
                        <option value="webrtc" <?= ($live['preferredProtocol'] ?? '') === 'webrtc' ? 'selected' : '' ?>>WebRTC (lower latency)</option>
                    </select>
                </label>
                <label class="admin-checkbox">
                    <input type="checkbox" name="useHttpsProxy" <?= !empty($live['useHttpsProxy']) ? 'checked' : '' ?>>
                    Use HTTPS proxy paths on production site (<code>/live-hls</code>, <code>/live-webrtc</code>)
                </label>
            </form>

            <div class="admin-table-editor">
                <div class="admin-table-editor-header">
                    <h3>Tables</h3>
                    <button type="button" id="addTableBtn" class="btn btn-secondary">+ Add table</button>
                </div>
                <div id="tablesContainer"></div>
            </div>

            <div class="admin-actions">
                <button type="button" id="saveLiveBtn" class="btn btn-primary">Save live settings</button>
            </div>
        </section>

        <section class="admin-panel">
            <h2>Site content</h2>
            <p class="admin-hint">Updates the homepage hero, contact email, and download note.</p>
            <form id="siteForm" class="admin-form-grid">
                <label>Hero title
                    <input type="text" name="heroTitle" value="<?= htmlspecialchars($site['heroTitle'] ?? '') ?>">
                </label>
                <label>Hero subtitle
                    <textarea name="heroSubtitle" rows="4"><?= htmlspecialchars($site['heroSubtitle'] ?? '') ?></textarea>
                </label>
                <label>Contact email
                    <input type="email" name="contactEmail" value="<?= htmlspecialchars($site['contactEmail'] ?? '') ?>">
                </label>
                <label>Download note
                    <input type="text" name="downloadNote" value="<?= htmlspecialchars($site['downloadNote'] ?? '') ?>">
                </label>
            </form>
            <div class="admin-actions">
                <button type="button" id="saveSiteBtn" class="btn btn-primary">Save site content</button>
            </div>
        </section>

        <section class="admin-panel">
            <h2>MediaMTX paths (copy to VPS)</h2>
            <p class="admin-hint">After saving cameras, copy this into <code>C:\mediamtx\mediamtx.yml</code> on your VPS and restart MediaMTX.</p>
            <textarea id="mediamtxYaml" class="admin-code" rows="12" readonly><?= htmlspecialchars($mediamtxYaml) ?></textarea>
            <div class="admin-actions">
                <button type="button" id="copyYamlBtn" class="btn btn-secondary">Copy YAML</button>
            </div>
        </section>

        <section class="admin-panel">
            <h2>Change admin password</h2>
            <form id="passwordForm" class="admin-form-grid admin-form-narrow">
                <label>Current password <input type="password" name="currentPassword" autocomplete="current-password"></label>
                <label>New password <input type="password" name="newPassword" minlength="8" autocomplete="new-password"></label>
                <label>Confirm new password <input type="password" name="confirmPassword" minlength="8" autocomplete="new-password"></label>
            </form>
            <div class="admin-actions">
                <button type="button" id="savePasswordBtn" class="btn btn-secondary">Update password</button>
            </div>
        </section>
    </main>

    <script>
        window.RAILSHOT_ADMIN_TABLES = <?= json_encode($live['tables'] ?? [], JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="/js/admin.js"></script>
</body>
</html>
