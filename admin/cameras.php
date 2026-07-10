<?php
require_once dirname(__DIR__) . '/api/bootstrap.php';

if (!railshot_admin_exists()) {
    header('Location: /admin/setup.php');
    exit;
}

railshot_require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cameras — RailShot Admin</title>
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body class="admin-page">
    <header class="admin-header">
        <div class="admin-header-inner">
            <h1>RailShot Admin</h1>
            <div class="admin-header-actions">
                <a href="/admin/" class="admin-link">&#8592; Back to Admin</a>
                <a href="/live.html" target="_blank" class="admin-link">View Live</a>
                <a href="/admin/logout.php" class="admin-link">Logout</a>
            </div>
        </div>
    </header>

    <main class="admin-main">
        <div id="adminMessage" class="admin-message hidden" role="status"></div>

        <section class="admin-panel">
            <h2>Cameras</h2>
            <p class="admin-hint">
                Add all your physical IP cameras here once. Each camera needs a name and its RTSP URL.
                When you add a table in <a href="/admin/">Venues &amp; cameras</a>, you'll pick a camera from this list.
            </p>
            <p class="admin-hint">
                <strong>RTSP URL format:</strong> <code>rtsp://username:password@192.168.1.100:554/stream</code><br>
                For Reolink cameras the path is usually <code>/h264Preview_01_main</code> (camera 1), <code>/h264Preview_02_main</code> (camera 2), etc.
            </p>

            <div id="camerasContainer">
                <p class="admin-hint">Loading cameras&hellip;</p>
            </div>

            <div class="admin-actions" style="margin-top:16px;">
                <button type="button" id="addCameraBtn" class="btn btn-secondary">+ Add camera</button>
                <button type="button" id="saveCamerasBtn" class="btn btn-primary" style="margin-left:12px;">Save cameras</button>
            </div>
        </section>
    </main>

    <script>
    (function () {
        var cameras = [];
        var container = document.getElementById('camerasContainer');
        var msgEl = document.getElementById('adminMessage');

        function showMessage(text, isError) {
            if (!msgEl) return;
            msgEl.textContent = text;
            msgEl.className = 'admin-message' + (isError ? ' admin-message-error' : '');
            msgEl.classList.remove('hidden');
            clearTimeout(msgEl._t);
            msgEl._t = setTimeout(function () { msgEl.classList.add('hidden'); }, 5000);
        }

        function escapeHtml(str) {
            return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function render() {
            if (!cameras.length) {
                container.innerHTML = '<p class="admin-hint">No cameras yet. Click &ldquo;+ Add camera&rdquo; to add your first one.</p>';
                return;
            }
            container.innerHTML = cameras.map(function (cam, i) {
                return '<div class="admin-table-card admin-nested-table" data-cam-idx="' + i + '">' +
                    '<div class="admin-table-card-header">' +
                        '<strong>' + escapeHtml(cam.name || ('Camera ' + (i + 1))) + '</strong>' +
                        '<button type="button" class="admin-remove-btn" data-remove="' + i + '">Remove</button>' +
                    '</div>' +
                    '<div class="admin-table-card-grid">' +
                        '<label>Camera name' +
                            '<input data-field="name" data-idx="' + i + '" value="' + escapeHtml(cam.name || '') + '" placeholder="Table 1 Camera">' +
                            '<span class="admin-field-hint">A friendly name shown in the table dropdown (e.g. &ldquo;Table 1 Camera&rdquo;).</span>' +
                        '</label>' +
                        '<label class="full-width">RTSP URL' +
                            '<input data-field="rtspUrl" data-idx="' + i + '" value="' + escapeHtml(cam.rtspUrl || '') + '" placeholder="rtsp://admin:password@192.168.1.100:554/h264Preview_01_main" autocomplete="off" spellcheck="false">' +
                            '<span class="admin-field-hint">Full RTSP address of this camera. Format: rtsp://user:pass@ip:port/path</span>' +
                        '</label>' +
                    '</div>' +
                '</div>';
            }).join('');

            container.querySelectorAll('[data-remove]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    cameras.splice(Number(btn.getAttribute('data-remove')), 1);
                    render();
                });
            });

            container.querySelectorAll('[data-field]').forEach(function (input) {
                input.addEventListener('input', function () {
                    cameras[Number(input.getAttribute('data-idx'))][input.getAttribute('data-field')] = input.value;
                });
            });
        }

        // Load cameras from server
        fetch('/admin/api/cameras.php', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (result) {
                if (result.ok && Array.isArray(result.cameras)) {
                    cameras = result.cameras;
                }
                render();
            })
            .catch(function () { render(); });

        document.getElementById('addCameraBtn').addEventListener('click', function () {
            var next = cameras.length + 1;
            cameras.push({ name: 'Camera ' + next, rtspUrl: '' });
            render();
            var cards = container.querySelectorAll('[data-cam-idx]');
            if (cards.length) cards[cards.length - 1].scrollIntoView({ behavior: 'smooth', block: 'center' });
        });

        document.getElementById('saveCamerasBtn').addEventListener('click', async function () {
            for (var i = 0; i < cameras.length; i++) {
                if (!cameras[i].name) { showMessage('Camera ' + (i + 1) + ': name is required.', true); return; }
                if (!cameras[i].rtspUrl) { showMessage('Camera ' + (i + 1) + ': RTSP URL is required.', true); return; }
                if (!cameras[i].rtspUrl.toLowerCase().startsWith('rtsp://')) {
                    showMessage('Camera ' + (i + 1) + ': RTSP URL must start with rtsp://', true); return;
                }
            }
            try {
                var res = await fetch('/admin/api/cameras.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ cameras: cameras })
                });
                var result = await res.json();
                if (!res.ok || !result.ok) throw new Error(result.error || 'Save failed');
                showMessage('Cameras saved successfully.');
            } catch (err) {
                showMessage('Error: ' + err.message, true);
            }
        });
    })();
    </script>
</body>
</html>
