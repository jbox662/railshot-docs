/**
 * RailShot TV — Go Live / Venue Operator Page
 * Handles PIN login, table card rendering, and Go Live / Stop / Switch controls.
 */
(function () {
    'use strict';

    // ── DOM refs ──────────────────────────────────────────────────────────────
    var pinScreen      = document.getElementById('pinScreen');
    var controlScreen  = document.getElementById('controlScreen');
    var pinForm        = document.getElementById('pinForm');
    var pinInput       = document.getElementById('pinInput');
    var pinError       = document.getElementById('pinError');
    var logoutBtn      = document.getElementById('logoutBtn');
    var venueNameEl    = document.getElementById('venueName');
    var statusBanner   = document.getElementById('statusBanner');
    var statusBannerText = document.getElementById('statusBannerText');
    var tableCards     = document.getElementById('tableCards');
    var noTablesMsg    = document.getElementById('noTablesMsg');

    // ── State ─────────────────────────────────────────────────────────────────
    var config = null;         // live config from API
    var activeTableId = null;  // currently on-air table
    var pollTimer = null;

    // ── PIN Login ─────────────────────────────────────────────────────────────
    pinForm && pinForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        var pin = pinInput.value.trim();
        if (!pin) return;

        try {
            var res = await fetch('/api/operator-login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ pin: pin })
            });
            var data = await res.json();
            if (res.ok && data.ok) {
                showControlScreen();
            } else {
                showPinError(data.error || 'Incorrect PIN. Please try again.');
            }
        } catch (err) {
            showPinError('Could not connect to server. Please try again.');
        }
    });

    function showPinError(msg) {
        if (pinError) {
            pinError.textContent = msg;
            pinError.classList.remove('hidden');
        }
        if (pinInput) {
            pinInput.value = '';
            pinInput.focus();
        }
    }

    // ── Logout ────────────────────────────────────────────────────────────────
    logoutBtn && logoutBtn.addEventListener('click', async function () {
        try {
            await fetch('/api/operator-logout.php', {
                method: 'POST',
                credentials: 'same-origin'
            });
        } catch (err) { /* ignore */ }
        showPinScreen();
    });

    // ── Screen switching ──────────────────────────────────────────────────────
    function showPinScreen() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        pinScreen.classList.remove('hidden');
        controlScreen.classList.add('hidden');
        if (pinInput) { pinInput.value = ''; pinInput.focus(); }
        if (pinError) pinError.classList.add('hidden');
    }

    function showControlScreen() {
        pinScreen.classList.add('hidden');
        controlScreen.classList.remove('hidden');
        loadAndRender();
        pollTimer = setInterval(pollConfig, 15000);
    }

    // ── Config loading ────────────────────────────────────────────────────────
    async function loadAndRender() {
        try {
            var res = await fetch('/api/live-config.php', { cache: 'no-store', credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            config = await res.json();
        } catch (err) {
            console.warn('Failed to load config:', err);
            return;
        }

        // Also fetch the full venue config (all tables, not just active)
        try {
            var res2 = await fetch('/api/operator-config.php', { cache: 'no-store', credentials: 'same-origin' });
            if (res2.ok) {
                var opConfig = await res2.json();
                if (opConfig && opConfig.tables) {
                    config.allTables = opConfig.tables;
                    config.activeTableId = opConfig.activeTableId;
                }
            }
        } catch (err) { /* use live-config fallback */ }

        activeTableId = config.activeTableId || (config.tables && config.tables[0] && config.tables[0].id) || null;

        if (venueNameEl) venueNameEl.textContent = config.venueName || '';
        renderTableCards();
        updateStatusBanner();
    }

    async function pollConfig() {
        try {
            var res = await fetch('/api/operator-config.php', { cache: 'no-store', credentials: 'same-origin' });
            if (!res.ok) return;
            var opConfig = await res.json();
            if (opConfig && opConfig.tables) {
                if (config) {
                    config.allTables = opConfig.tables;
                    config.activeTableId = opConfig.activeTableId;
                    activeTableId = opConfig.activeTableId;
                }
                renderTableCards();
                updateStatusBanner();
            }
        } catch (err) { /* ignore poll errors */ }
    }

    // ── Render table cards ────────────────────────────────────────────────────
    function renderTableCards() {
        if (!tableCards) return;
        var tables = (config && config.allTables) || (config && config.tables) || [];

        if (!tables.length) {
            tableCards.innerHTML = '';
            if (noTablesMsg) noTablesMsg.classList.remove('hidden');
            return;
        }
        if (noTablesMsg) noTablesMsg.classList.add('hidden');

        tableCards.innerHTML = tables.map(function (table) {
            var isActive = table.id === activeTableId;
            var hasYt = !!(table.youtubeUrl && table.youtubeUrl.trim());
            var sourceLabel = hasYt ? 'YouTube Live' : (table.rtspUrl ? 'Self-hosted Camera' : 'No source configured');
            var sourceIcon = hasYt
                ? '<svg class="table-card-source-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 0 0 1.46 6.42 29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 1.96C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.96A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02"/></svg>'
                : '<svg class="table-card-source-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>';

            return (
                '<div class="table-card' + (isActive ? ' is-active' : '') + '" data-table-id="' + escHtml(table.id) + '">' +
                    '<div class="table-card-header">' +
                        '<span class="table-card-name">' + escHtml(table.name) + '</span>' +
                        '<span class="table-card-badge' + (isActive ? '' : ' badge-offline') + '">' + (isActive ? 'On Air' : 'Off Air') + '</span>' +
                    '</div>' +
                    '<div class="table-card-source">' + sourceIcon + escHtml(sourceLabel) + '</div>' +
                    (isActive
                        ? '<button class="golive-btn golive-btn-stop" data-action="stop" data-table="' + escHtml(table.id) + '">&#9632; Stop Stream</button>'
                        : '<button class="golive-btn golive-btn-switch" data-action="switch" data-table="' + escHtml(table.id) + '">&#9654; Switch to This Table</button>'
                    ) +
                '</div>'
            );
        }).join('');

        // Attach button listeners
        tableCards.querySelectorAll('[data-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var action = btn.getAttribute('data-action');
                var tableId = btn.getAttribute('data-table');
                if (action === 'stop') {
                    handleStop(tableId, btn);
                } else if (action === 'switch') {
                    handleSwitch(tableId, btn);
                }
            });
        });
    }

    // ── Status banner ─────────────────────────────────────────────────────────
    function updateStatusBanner() {
        if (!statusBanner || !statusBannerText) return;
        var tables = (config && config.allTables) || (config && config.tables) || [];
        var activeTable = tables.find(function (t) { return t.id === activeTableId; });
        if (activeTable) {
            statusBanner.className = 'status-banner status-live';
            statusBannerText.textContent = 'Live — ' + activeTable.name;
        } else {
            statusBanner.className = 'status-banner status-offline';
            statusBannerText.textContent = 'Off Air';
        }
    }

    // ── Actions ───────────────────────────────────────────────────────────────
    async function handleSwitch(tableId, btn) {
        btn.disabled = true;
        btn.textContent = 'Switching…';
        try {
            var res = await fetch('/api/operator-switch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ tableId: tableId })
            });
            var data = await res.json();
            if (res.ok && data.ok) {
                activeTableId = tableId;
                if (config) config.activeTableId = tableId;
                renderTableCards();
                updateStatusBanner();
            } else {
                alert(data.error || 'Switch failed. Please try again.');
                btn.disabled = false;
                btn.textContent = '\u25B6 Switch to This Table';
            }
        } catch (err) {
            alert('Could not connect to server.');
            btn.disabled = false;
            btn.textContent = '\u25B6 Switch to This Table';
        }
    }

    async function handleStop(tableId, btn) {
        if (!confirm('Stop the live stream for this table?')) return;
        btn.disabled = true;
        btn.textContent = 'Stopping…';
        try {
            var res = await fetch('/api/operator-switch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ tableId: '__none__' })
            });
            var data = await res.json();
            if (res.ok && data.ok) {
                activeTableId = null;
                if (config) config.activeTableId = null;
                renderTableCards();
                updateStatusBanner();
            } else {
                alert(data.error || 'Stop failed. Please try again.');
                btn.disabled = false;
                btn.textContent = '\u25A0 Stop Stream';
            }
        } catch (err) {
            alert('Could not connect to server.');
            btn.disabled = false;
            btn.textContent = '\u25A0 Stop Stream';
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Init: check if already logged in ─────────────────────────────────────
    (async function init() {
        try {
            var res = await fetch('/api/operator-config.php', { cache: 'no-store', credentials: 'same-origin' });
            if (res.ok) {
                var data = await res.json();
                if (data && data.ok !== false) {
                    // Already have an operator session
                    showControlScreen();
                    return;
                }
            }
        } catch (err) { /* not logged in */ }
        showPinScreen();
    })();

})();
