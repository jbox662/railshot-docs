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
$youtube = $config['youtube'] ?? [];
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

        <!-- ── Stream Control ───────────────────────────────────────────── -->
        <section class="admin-panel" id="streamControlPanel">
            <h2>Stream control</h2>
            <p class="admin-hint">Start or stop the live stream for each venue. Viewers see an offline message when the stream is stopped.</p>
            <div id="streamControlVenues"></div>
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

        <!-- ── YouTube API Settings ─────────────────────────────────────── -->
        <section class="admin-panel">
            <h2>YouTube API settings</h2>
            <p class="admin-hint">
                Enter your Google API key to enable <strong>automatic live stream detection</strong>.
                Once set, each camera only needs a <strong>YouTube Channel ID</strong> — the system finds the live video automatically every time you go live.
                No more copying URLs.
            </p>
            <p class="admin-hint">
                Get a free key: <a href="https://console.cloud.google.com/apis/library/youtube.googleapis.com" target="_blank" rel="noopener">Google Cloud Console → YouTube Data API v3 → Enable → Credentials → Create API Key</a>
            </p>
            <form id="youtubeSettingsForm" class="admin-form-grid admin-form-narrow">
                <label class="full-width">Google API Key (YouTube Data API v3)
                    <input type="text" name="apiKey" id="youtubeApiKeyInput"
                           value="<?= htmlspecialchars($youtube['apiKey'] ?? '') ?>"
                           placeholder="AIzaSy..."
                           autocomplete="off"
                           spellcheck="false">
                    <span class="admin-field-hint">This key is stored securely on your server and never exposed to viewers.</span>
                </label>
            </form>
            <div class="admin-actions">
                <button type="button" id="saveYoutubeSettingsBtn" class="btn btn-primary">Save YouTube settings</button>
                <button type="button" id="testYoutubeApiBtn" class="btn btn-secondary" style="margin-left:12px;">Test API key</button>
                <span id="youtubeApiTestResult" style="margin-left:12px;font-size:0.9em;"></span>
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
        window.RAILSHOT_YOUTUBE_API_CONFIGURED = <?= json_encode(!empty($youtube['apiKey'])) ?>;
    </script>
    <script src="/js/admin.js?v=6"></script>
</body>
</html>
