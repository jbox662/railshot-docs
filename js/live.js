/**
 * RailShot TV — Live Tables player
 * Loads config from /api/live-config.php (admin-managed), with JS fallback.
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

    const VIDEO_FIT_KEY = 'railshot_video_fit';
    const CONFIG_POLL_MS = 20000;
    const STREAM_TIMEOUT_MS = 12000; // Show offline message after 12 seconds

    let config = null;
    let currentTableId = null;
    let currentReader = null;
    let currentHls = null;
    let usingFallback = false;
    let streamTimeoutId = null;
    let currentTable = null;

    function stripTrailingSlash(url) {
        return String(url || '').replace(/\/+$/, '');
    }

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

        // Remove any existing retry button first
        var existingRetry = overlayEl.querySelector('.overlay-retry-btn');
        if (existingRetry) existingRetry.remove();

        if (showRetry && currentTable) {
            var retryBtn = document.createElement('button');
            retryBtn.type = 'button';
            retryBtn.className = 'overlay-retry-btn';
            retryBtn.textContent = 'Retry';
            retryBtn.addEventListener('click', function () {
                clearStreamTimeout();
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

    function clearStreamTimeout() {
        if (streamTimeoutId) {
            clearTimeout(streamTimeoutId);
            streamTimeoutId = null;
        }
    }

    function startStreamTimeout(table) {
        clearStreamTimeout();
        streamTimeoutId = setTimeout(function () {
            // Only fire if we are still in a connecting state
            if (statusEl && statusEl.classList.contains('is-connecting')) {
                setStatus('error', 'Stream offline');
                showOverlay('The stream is currently offline. Please check back later.', true, true);
                setProtocolBadge('');
            }
        }, STREAM_TIMEOUT_MS);
    }

    function cleanupPlayer() {
        if (currentReader) {
            try { currentReader.close(); } catch (err) { console.warn('WebRTC close error:', err); }
            currentReader = null;
        }
        if (currentHls) {
            try { currentHls.destroy(); } catch (err) { console.warn('HLS destroy error:', err); }
            currentHls = null;
        }
        if (videoEl) {
            videoEl.removeAttribute('src');
            videoEl.srcObject = null;
            videoEl.load();
        }
    }

    function isPlaceholderHost(url) {
        return !url || /YOUR_SERVER/i.test(url);
    }

    function loadStreamHls(table) {
        const base = stripTrailingSlash(config.hlsBaseUrl);
        const hlsUrl = base.indexOf('?path=') !== -1
            ? base + table.id + '/index.m3u8'
            : base + '/' + table.id + '/index.m3u8';

        usingFallback = true;
        setProtocolBadge('HLS');
        setStatus('connecting', 'Connecting to ' + table.name + '…');
        showOverlay('Connecting to ' + table.name + '…');
        startStreamTimeout(table);

        if (window.Hls && Hls.isSupported()) {
            currentHls = new Hls({ enableWorker: true, lowLatencyMode: true });
            currentHls.loadSource(hlsUrl);
            currentHls.attachMedia(videoEl);
            currentHls.on(Hls.Events.MANIFEST_PARSED, function () {
                clearStreamTimeout();
                videoEl.play().catch(function () {});
                hideOverlay();
                setStatus('live', 'Live · ' + table.name);
            });
            currentHls.on(Hls.Events.ERROR, function (_event, data) {
                if (data.fatal) {
                    clearStreamTimeout();
                    setStatus('error', 'Stream offline');
                    showOverlay('The stream is currently offline. Please check back later.', true, true);
                    setProtocolBadge('');
                }
            });
            return;
        }

        if (videoEl.canPlayType('application/vnd.apple.mpegurl')) {
            videoEl.src = hlsUrl;
            videoEl.addEventListener('loadedmetadata', function onMeta() {
                videoEl.removeEventListener('loadedmetadata', onMeta);
                clearStreamTimeout();
                videoEl.play().catch(function () {});
                hideOverlay();
                setStatus('live', 'Live · ' + table.name);
            });
            return;
        }

        clearStreamTimeout();
        setStatus('error', 'Playback not supported');
        showOverlay('This browser cannot play the live stream.', true);
    }

    function loadStreamWebRtc(table) {
        if (typeof MediaMTXWebRTCReader === 'undefined') {
            loadStreamHls(table);
            return;
        }

        const base = stripTrailingSlash(config.webrtcBaseUrl);
        const whepUrl = base.indexOf('?path=') !== -1
            ? base + table.id + '/whep'
            : base + '/' + table.id + '/whep';

        usingFallback = false;
        setProtocolBadge('WebRTC');
        setStatus('connecting', 'Connecting to ' + table.name + '…');
        showOverlay('Connecting to ' + table.name + '…');
        startStreamTimeout(table);

        currentReader = new MediaMTXWebRTCReader({
            url: whepUrl,
            onError: function (err) {
                console.warn('WebRTC error, falling back to HLS:', err);
                if (!usingFallback) {
                    clearStreamTimeout();
                    cleanupPlayer();
                    loadStreamHls(table);
                }
            },
            onTrack: function (evt) {
                if (evt.streams && evt.streams[0]) {
                    clearStreamTimeout();
                    videoEl.srcObject = evt.streams[0];
                    videoEl.play().catch(function () {});
                    hideOverlay();
                    setStatus('live', 'Live · ' + table.name);
                    setProtocolBadge('WebRTC');
                }
            }
        });
    }

    function loadTable(table) {
        if (!table) return;

        currentTable = table;
        currentTableId = table.id;
        titleEl.textContent = table.name;
        descEl.textContent = table.description || '';

        document.querySelectorAll('.table-item').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.stream === table.id);
            btn.setAttribute('aria-selected', btn.dataset.stream === table.id ? 'true' : 'false');
        });

        cleanupPlayer();

        if (isPlaceholderHost(config.webrtcBaseUrl) && isPlaceholderHost(config.hlsBaseUrl)) {
            setStatus('error', 'Server not configured');
            showOverlay('Configure cameras in the RailShot admin panel.', true);
            setProtocolBadge('');
            return;
        }

        if (String(config.preferredProtocol || '').toLowerCase() === 'hls') {
            loadStreamHls(table);
        } else {
            loadStreamWebRtc(table);
        }
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
            showOverlay('No live stream on air. Choose a table in the admin panel.', true);
            setStatus('error', 'Off air');
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
            btn.innerHTML =
                '<span class="table-item-name"></span>' +
                (table.description ? '<span class="table-item-desc"></span>' : '');
            btn.querySelector('.table-item-name').textContent = table.name;
            const descNode = btn.querySelector('.table-item-desc');
            if (descNode) descNode.textContent = table.description;

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

        config.webrtcBaseUrl = nextConfig.webrtcBaseUrl;
        config.hlsBaseUrl = nextConfig.hlsBaseUrl;
        config.preferredProtocol = nextConfig.preferredProtocol;
        config.tables = nextConfig.tables;
        config.activeTableId = nextConfig.activeTableId;

        if (nextId && nextId !== currentTableId) {
            loadTable(nextTable);
        } else if (!nextId && currentTableId) {
            currentTableId = null;
            cleanupPlayer();
            showOverlay('No live stream on air. Choose a table in the admin panel.', true);
            setStatus('error', 'Off air');
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
            console.warn('API config unavailable, using fallback:', err);
        }
        return window.RAILSHOT_LIVE_CONFIG || null;
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
        startConfigPolling();
    }

    window.addEventListener('beforeunload', cleanupPlayer);
    init();
})();
