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
$landing = $live['landing'] ?? railshot_default_config()['live']['landing'];
$venues = railshot_normalize_venues($live);
$landingBullets = implode("\n", $landing['bullets'] ?? []);
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
            <h2>Live landing page</h2>
            <p class="admin-hint">Marketing copy on <a href="/live.html" target="_blank">live.html</a> before viewers pick a venue.</p>
            <form id="landingForm" class="admin-form-grid">
                <label class="full-width">Headline
                    <input type="text" name="headline" value="<?= htmlspecialchars($landing['headline'] ?? '') ?>">
                </label>
                <label class="full-width">Subtitle
                    <textarea name="subtitle" rows="3"><?= htmlspecialchars($landing['subtitle'] ?? '') ?></textarea>
                </label>
                <label class="full-width">Bullet points (one per line)
                    <textarea name="bullets" rows="4" placeholder="Feature one&#10;Feature two"><?= htmlspecialchars($landingBullets) ?></textarea>
                </label>
            </form>
        </section>

        <section class="admin-panel">
            <h2>Venues &amp; cameras</h2>
            <p class="admin-hint">Each venue appears on the live page. Viewers pick a venue, then watch the <strong>on-air</strong> camera you select for that venue.</p>

            <div class="admin-table-editor">
                <div class="admin-table-editor-header">
                    <h3>Venues</h3>
                    <button type="button" id="addVenueBtn" class="btn btn-secondary">+ Add venue</button>
                </div>
                <div id="venuesContainer"></div>
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
            <h2>Venue Operator PIN</h2>
            <p class="admin-hint">Set a short PIN for venue staff to use on the <a href="/golive.html" target="_blank">Go Live page</a>. Staff can switch cameras and start/stop streams without accessing the full admin panel.</p>
            <form id="operatorPinForm" class="admin-form-grid admin-form-narrow">
                <label>New Operator PIN
                    <input type="password" name="newPin" minlength="4" maxlength="12" inputmode="numeric" placeholder="4–12 digits" autocomplete="new-password">
                </label>
                <label>Confirm PIN
                    <input type="password" name="confirmPin" minlength="4" maxlength="12" inputmode="numeric" placeholder="Confirm PIN" autocomplete="new-password">
                </label>
                <p class="admin-field-hint">Tip: Use a simple 4–6 digit number that venue staff can remember easily.</p>
            </form>
            <div class="admin-actions">
                <button type="button" id="saveOperatorPinBtn" class="btn btn-secondary">Set Operator PIN</button>
                <a href="/golive.html" target="_blank" class="btn btn-primary" style="margin-left:12px;">Open Go Live Page &rarr;</a>
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
        window.RAILSHOT_ADMIN_LANDING = <?= json_encode($landing, JSON_UNESCAPED_SLASHES) ?>;
        window.RAILSHOT_ADMIN_VENUES = <?= json_encode($venues, JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="/js/admin.js?v=3"></script>
</body>
</html>
