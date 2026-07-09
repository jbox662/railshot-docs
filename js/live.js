/**
 * RailShot TV — Live Tables player
 * Loads config from /api/live-config.php (admin-managed).
 * Streams via YouTube Live iframe embed only.
 */

(function () {
    const videoEl = document.getElementById('livePlayer');
    const tableListEl = document.getElementById('tableList');
    const tableCountEl = document.getElementById('tableCount');
    const titleEl = document.getElementById('currentTableTitle');
    const descEl = document.getElementById('currentTableDescription');
    const overlayEl = document.getElementById('loadingOverlay');
    const overlayMsgEl = document.getElementById('overlayMessage');
    const spinnerEl = overlayEl ? overlayEl.querySelector('.overlay-spinner') : null;
    const statusEl = document.getElementById('liveStatus');
    const statusTextEl = document.getElementById('liveStatusText');
    const protocolBadgeEl = document.getElementById('protocolBadge');
    const videoWrapperEl = document.querySelector('.video-wrapper');
    const videoFitFillBtn = document.getElementById('videoFitFill');
    const videoFitFullBtn = document.getElementById('videoFitFull');
    const liveLayoutEl = document.getElementById('liveLayout');
    const tablePanelEl = document.getElementById('tablePanel');
    const scoreboardOverlayEl = document.getElementById('scoreboardOverlay');

    const VIDEO_FIT_KEY = 'railshot_video_fit';
    const CONFIG_POLL_MS = 20000;

    let config = null;
    let currentTableId = null;
    let currentTable = null;

    function setStatus(state, text) {
        if (statusEl) {
            statusEl.classList.remove('is-live', 'is-connecting', 'is-error');
            if (state) statusEl.classList.add('is-' + state);
        }
        if (statusTextEl) statusTextEl.textContent = text;
    }

    function showOverlay(message, idle, showRetry) {
        if (!overlayEl) return;
        overlayEl.classList.remove('hidden');
        if (overlayMsgEl) overlayMsgEl.textContent = message;
        if (spinnerEl) spinnerEl.classList.toggle('is-idle', !!idle);

        var existingRetry = overlayEl.querySelector('.overlay-retry-btn');
        if (existingRetry) existingRetry.remove();

        if (showRetry && currentTable) {
            var retryBtn = document.createElement('button');
            retryBtn.type = 'button';
            retryBtn.className = 'overlay-retry-btn';
            retryBtn.textContent = 'Retry';
            retryBtn.addEventListener('click', function () {
                loadTable(currentTable);
            });
            overlayEl.appendChild(retryBtn);
        }
    }

    function hideOverlay() {
        if (overlayEl) overlayEl.classList.add('hidden');
    }

    function setVideoFit(mode) {
        const isFull = mode === 'full';
        if (videoWrapperEl) {
            videoWrapperEl.classList.toggle('is-fit-full', isFull);
        }
        if (videoFitFillBtn) {
            videoFitFillBtn.classList.toggle('active', !isFull);
            videoFitFillBtn.setAttribute('aria-pressed', isFull ? 'false' : 'true');
        }
        if (videoFitFullBtn) {
            videoFitFullBtn.classList.toggle('active', isFull);
            videoFitFullBtn.setAttribute('aria-pressed', isFull ? 'true' : 'false');
        }
        try {
            localStorage.setItem(VIDEO_FIT_KEY, isFull ? 'full' : 'fill');
        } catch (err) { /* ignore */ }
    }

    function initVideoFitControls() {
        let saved = 'fill';
        try {
            saved = localStorage.getItem(VIDEO_FIT_KEY) || 'fill';
        } catch (err) { /* ignore */ }
        setVideoFit(saved === 'full' ? 'full' : 'fill');

        if (videoFitFillBtn) {
            videoFitFillBtn.addEventListener('click', function () { setVideoFit('fill'); });
        }
        if (videoFitFullBtn) {
            videoFitFullBtn.addEventListener('click', function () { setVideoFit('full'); });
        }
    }

    function applyViewerLayout() {
        const locked = config && config.viewerLocked !== false;
        if (liveLayoutEl) {
            liveLayoutEl.classList.toggle('viewer-locked', locked);
        }
        if (tablePanelEl) {
            tablePanelEl.hidden = locked;
        }
    }

    function setProtocolBadge(label) {
        if (!protocolBadgeEl) return;
        if (!label) {
            protocolBadgeEl.classList.add('hidden');
            protocolBadgeEl.textContent = '';
            return;
        }
        protocolBadgeEl.textContent = label;
        protocolBadgeEl.classList.remove('hidden');
    }

    function cleanupPlayer() {
        var videoWrapper = videoEl ? videoEl.parentElement : null;
        if (videoWrapper) {
            var oldYt = videoWrapper.querySelector('.yt-live-iframe');
            if (oldYt) oldYt.remove();
            videoEl.style.display = '';
        }
        if (videoEl) {
            videoEl.removeAttribute('src');
            videoEl.load();
        }
    }

    // ── YouTube iframe player ────────────────────────────────────────────────
    function buildEmbedUrl(youtubeUrl) {
        var embedUrl = youtubeUrl;
        // Handle full watch URLs: https://www.youtube.com/watch?v=ID
        var watchMatch = youtubeUrl.match(/[?&]v=([^&]+)/);
        if (watchMatch) return 'https://www.youtube.com/embed/' + watchMatch[1] + '?autoplay=1&mute=1';
        // Handle youtu.be/ID short URLs
        var shortMatch = youtubeUrl.match(/youtu\.be\/([^?&]+)/);
        if (shortMatch) return 'https://www.youtube.com/embed/' + shortMatch[1] + '?autoplay=1&mute=1';
        // Handle youtube.com/live/ID share URLs
        var liveShareMatch = youtubeUrl.match(/youtube\.com\/live\/([^?&]+)/);
        if (liveShareMatch) return 'https://www.youtube.com/embed/' + liveShareMatch[1] + '?autoplay=1&mute=1';
        // Handle already-formed embed URLs
        if (youtubeUrl.indexOf('/embed/') !== -1) {
            return youtubeUrl.indexOf('autoplay') === -1
                ? youtubeUrl + (youtubeUrl.indexOf('?') !== -1 ? '&' : '?') + 'autoplay=1&mute=1'
                : youtubeUrl;
        }
        return embedUrl;
    }

    function insertYouTubeIframe(embedUrl, tableName) {
        var videoWrapper = videoEl ? videoEl.parentElement : null;
        if (!videoWrapper) return;
        videoEl.style.display = 'none';
        var existingYt = videoWrapper.querySelector('.yt-live-iframe');
        if (existingYt) existingYt.remove();
        var iframe = document.createElement('iframe');
        iframe.className = 'yt-live-iframe';
        iframe.src = embedUrl;
        iframe.setAttribute('frameborder', '0');
        iframe.setAttribute('allow', 'autoplay; fullscreen; encrypted-media; picture-in-picture');
        iframe.setAttribute('allowfullscreen', 'true');
        iframe.setAttribute('title', 'Live: ' + tableName);
        videoWrapper.insertBefore(iframe, videoWrapper.firstChild);
        hideOverlay();
        setStatus('live', 'Live \u00b7 ' + tableName);
        setProtocolBadge('YouTube');
    }

    function loadStreamYouTube(table) {
        var channelId = (table.youtubeChannelId || '').trim();
        var youtubeUrl = (table.youtubeUrl || '').trim();

        // ── Auto-detect via Channel ID (preferred) ────────────────────────
        if (channelId) {
            setStatus('connecting', 'Finding live stream\u2026');
            showOverlay('Finding live stream\u2026', false, false);
            fetch('/api/youtube-live.php?channelId=' + encodeURIComponent(channelId), { cache: 'no-store' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.ok && data.embedUrl) {
                        insertYouTubeIframe(data.embedUrl, table.name);
                    } else if (youtubeUrl) {
                        // Fall back to manual URL if channel has no live stream right now
                        insertYouTubeIframe(buildEmbedUrl(youtubeUrl), table.name);
                    } else {
                        setStatus('error', 'Not live yet');
                        showOverlay('No live stream found for this camera. The stream may not have started yet.', true, true);
                        setProtocolBadge('');
                    }
                })
                .catch(function () {
                    // Network error — fall back to manual URL if available
                    if (youtubeUrl) {
                        insertYouTubeIframe(buildEmbedUrl(youtubeUrl), table.name);
                    } else {
                        setStatus('error', 'Stream lookup failed');
                        showOverlay('Could not reach the stream detection service. Check back in a moment.', true, true);
                        setProtocolBadge('');
                    }
                });
            return;
        }

        // ── Manual URL fallback ───────────────────────────────────────────
        if (youtubeUrl) {
            insertYouTubeIframe(buildEmbedUrl(youtubeUrl), table.name);
            return;
        }

        setStatus('error', 'Stream not configured');
        showOverlay('No YouTube Channel ID or URL is set for this camera. Configure it in the admin panel.', true);
        setProtocolBadge('');
    }

    function loadTable(table) {
        if (!table) return;

        currentTable = table;
        currentTableId = table.id;
        titleEl.textContent = table.name;
        // Description is intentionally hidden from viewers (may contain camera IP)
        if (descEl) descEl.textContent = '';

        // Switch scoreboard overlay to this table's URL (falls back to venue-level URL)
        if (table.overlayUrl && table.overlayUrl.trim() !== '') {
            config.overlayUrl = table.overlayUrl.trim();
        }
        applyScoreboardOverlay();

        document.querySelectorAll('.table-item').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.stream === table.id);
            btn.setAttribute('aria-selected', btn.dataset.stream === table.id ? 'true' : 'false');
        });

        cleanupPlayer();

        // YouTube path: use iframe player if channel ID or URL is configured
        var hasChannelId = !!(table.youtubeChannelId && table.youtubeChannelId.trim());
        var hasUrl = !!(table.youtubeUrl && table.youtubeUrl.trim());
        if (hasChannelId || hasUrl) {
            loadStreamYouTube(table);
            return;
        }

        // No stream configured
        setStatus('error', 'Stream not configured');
        showOverlay('No YouTube Channel ID or URL is set for this camera. Configure it in the admin panel.', true);
        setProtocolBadge('');
    }

    function buildTableList() {
        const tables = (config.tables || []).filter(function (t) {
            return t && t.id && t.name;
        });

        applyViewerLayout();

        if (tableListEl) tableListEl.innerHTML = '';
        if (tableCountEl) {
            tableCountEl.textContent = tables.length === 1 ? '1 table' : tables.length + ' tables';
        }

        if (!tables.length) {
            // No active table = stream is stopped / off air
            cleanupPlayer();
            showOverlay('No stream is currently on air. Check back soon!', true);
            setStatus('error', 'Off Air');
            setProtocolBadge('');
            return;
        }

        if (config.viewerLocked !== false) {
            loadTable(tables[0]);
            return;
        }

        tables.forEach(function (table, index) {
            const li = document.createElement('li');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'table-item';
            btn.dataset.stream = table.id;
            btn.setAttribute('role', 'option');
            btn.setAttribute('aria-selected', 'false');
            btn.innerHTML = '<span class="table-item-name"></span>';
            btn.querySelector('.table-item-name').textContent = table.name;

            btn.addEventListener('click', function () { loadTable(table); });
            li.appendChild(btn);
            tableListEl.appendChild(li);

            if (index === 0) loadTable(table);
        });
    }

    async function refreshActiveStream() {
        const params = new URLSearchParams(window.location.search);
        const venueId = params.get('venue') || '';
        const configUrl = venueId
            ? '/api/live-config.php?venue=' + encodeURIComponent(venueId)
            : '/api/live-config.php';

        let nextConfig = null;
        try {
            const response = await fetch(configUrl, { cache: 'no-store' });
            if (response.ok) {
                nextConfig = await response.json();
            }
        } catch (err) {
            return;
        }
        if (!nextConfig || !Array.isArray(nextConfig.tables)) {
            return;
        }

        const nextTable = nextConfig.tables[0];
        const nextId = nextTable ? nextTable.id : null;

        config.tables = nextConfig.tables;
        config.activeTableId = nextConfig.activeTableId;
        config.overlayEnabled = nextConfig.overlayEnabled;
        config.overlayUrl = nextConfig.overlayUrl;
        applyScoreboardOverlay();

        if (nextId && nextId !== currentTableId) {
            loadTable(nextTable);
        } else if (!nextId && currentTableId) {
            // Admin stopped the stream
            currentTableId = null;
            cleanupPlayer();
            showOverlay('No stream is currently on air. Check back soon!', true);
            setStatus('error', 'Off Air');
            setProtocolBadge('');
        }
    }

    function startConfigPolling() {
        if (config.viewerLocked === false) {
            return;
        }
        window.setInterval(function () {
            refreshActiveStream().catch(function (err) {
                console.warn('Live config poll failed:', err);
            });
        }, CONFIG_POLL_MS);
    }

    async function loadConfig() {
        const params = new URLSearchParams(window.location.search);
        const venueId = params.get('venue') || '';
        const configUrl = venueId
            ? '/api/live-config.php?venue=' + encodeURIComponent(venueId)
            : '/api/live-config.php';

        try {
            const response = await fetch(configUrl, { cache: 'no-store' });
            if (response.ok) {
                return await response.json();
            }
        } catch (err) {
            console.warn('API config unavailable:', err);
        }
        return null;
    }

    function applyScoreboardOverlay() {
        if (!scoreboardOverlayEl) return;
        var enabled = !!(config.overlayEnabled);
        var url = (config.overlayUrl || '').trim();

        if (enabled && url) {
            var lastUrl = scoreboardOverlayEl.getAttribute('data-overlay-src') || '';
            if (lastUrl !== url) {
                scoreboardOverlayEl.setAttribute('data-overlay-src', url);
                scoreboardOverlayEl.src = url;
            }
            scoreboardOverlayEl.classList.remove('hidden');
            scoreboardOverlayEl.style.visibility = 'visible';
            scoreboardOverlayEl.style.opacity = '1';
        } else {
            scoreboardOverlayEl.classList.add('hidden');
            scoreboardOverlayEl.style.visibility = 'hidden';
            scoreboardOverlayEl.style.opacity = '0';
            scoreboardOverlayEl.removeAttribute('data-overlay-src');
            scoreboardOverlayEl.src = 'about:blank';
        }
    }

    function applyVenueHeader() {
        const titleEl = document.getElementById('venueTitle');
        const eyebrowEl = document.getElementById('venueEyebrow');
        if (titleEl && config.venueName) {
            titleEl.textContent = config.venueName;
        }
        if (eyebrowEl && config.venueName) {
            eyebrowEl.textContent = 'Live at ' + config.venueName;
        }
        if (!config.venueId && window.location.pathname.indexOf('watch.html') !== -1) {
            window.location.replace('live.html');
        }
    }

    // ── Admin Table Switcher ────────────────────────────────────────────────────
    async function initAdminSwitcher() {
        const switcherEl = document.getElementById('adminTableSwitcher');
        const btnsEl     = document.getElementById('adminTableBtns');
        const statusSpan = document.getElementById('adminSwitcherStatus');
        if (!switcherEl || !btnsEl) return;

        let adminData;
        try {
            const res = await fetch('/api/admin-live.php?venue=' + encodeURIComponent(config.venueId || ''));
            adminData = await res.json();
        } catch (e) {
            return; // not admin or network error — stay hidden
        }

        if (!adminData || !adminData.isAdmin) return;

        switcherEl.classList.remove('hidden');

        async function postAdminLive(tableId) {
            const r = await fetch('/api/admin-live.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ venue: config.venueId || '', tableId: tableId }),
            });
            return r.json();
        }

        function renderBtns(activeId) {
            btnsEl.innerHTML = '';

            // Per-table Go Live buttons
            (adminData.tables || []).forEach(function (t) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'admin-switcher-btn' + (t.id === activeId ? ' active' : '');
                btn.textContent = (t.id === activeId ? '\u25cf ' : '') + t.name;
                btn.dataset.tableId = t.id;
                btn.addEventListener('click', async function () {
                    if (btn.disabled) return;
                    btnsEl.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
                    if (statusSpan) statusSpan.textContent = 'Switching\u2026';
                    try {
                        const result = await postAdminLive(t.id);
                        if (result.ok) {
                            adminData.tables.forEach(function (tbl) {
                                if (tbl.id === t.id && tbl.overlayUrl) {
                                    config.overlayUrl = tbl.overlayUrl;
                                }
                            });
                            adminData.activeTableId = t.id;
                            renderBtns(t.id);
                            config = await loadConfig();
                            buildTableList();
                            applyScoreboardOverlay();
                            if (statusSpan) statusSpan.textContent = 'Now on air: ' + t.name;
                            setTimeout(function () { if (statusSpan) statusSpan.textContent = ''; }, 3000);
                        } else {
                            if (statusSpan) statusSpan.textContent = 'Error: ' + (result.error || 'failed');
                        }
                    } catch (e) {
                        if (statusSpan) statusSpan.textContent = 'Network error';
                    }
                    btnsEl.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
                });
                btnsEl.appendChild(btn);
            });

            // Stop Stream button — only shown when a table is active
            const stopBtn = document.createElement('button');
            stopBtn.type = 'button';
            stopBtn.className = 'admin-switcher-btn admin-switcher-stop' + (activeId ? '' : ' hidden');
            stopBtn.textContent = '\u25a0 Stop Stream';
            stopBtn.addEventListener('click', async function () {
                if (stopBtn.disabled) return;
                btnsEl.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
                if (statusSpan) statusSpan.textContent = 'Stopping\u2026';
                try {
                    const result = await postAdminLive('__none__');
                    if (result.ok) {
                        adminData.activeTableId = '';
                        renderBtns('');
                        config = await loadConfig();
                        // Show offline overlay to the admin too
                        cleanupPlayer();
                        showOverlay('Stream stopped. Viewers see an offline message.', true);
                        setStatus('error', 'Off Air');
                        setProtocolBadge('');
                        if (statusSpan) statusSpan.textContent = 'Stream stopped.';
                        setTimeout(function () { if (statusSpan) statusSpan.textContent = ''; }, 3000);
                    } else {
                        if (statusSpan) statusSpan.textContent = 'Error: ' + (result.error || 'failed');
                    }
                } catch (e) {
                    if (statusSpan) statusSpan.textContent = 'Network error';
                }
                btnsEl.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
            });
            btnsEl.appendChild(stopBtn);
        }

        renderBtns(adminData.activeTableId);
    }

    async function init() {
        config = await loadConfig();

        if (!config || !Array.isArray(config.tables)) {
            setStatus('error', 'Missing streams config');
            showOverlay('Configuration missing. Set up admin at /admin/', true);
            return;
        }

        applyVenueHeader();
        buildTableList();
        initVideoFitControls();
        applyScoreboardOverlay();
        initAdminSwitcher();
        startConfigPolling();
    }

    window.addEventListener('beforeunload', cleanupPlayer);
    init();
})();
