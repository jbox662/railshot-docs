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

    const VIDEO_FIT_KEY = 'railshot_video_fit';

    let config = null;
    let currentReader = null;
    let currentHls = null;
    let usingFallback = false;

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

    function showOverlay(message, idle) {
        if (!overlayEl) return;
        overlayEl.classList.remove('hidden');
        if (overlayMsgEl) overlayMsgEl.textContent = message;
        if (spinnerEl) spinnerEl.classList.toggle('is-idle', !!idle);
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

        if (window.Hls && Hls.isSupported()) {
            currentHls = new Hls({ enableWorker: true, lowLatencyMode: true });
            currentHls.loadSource(hlsUrl);
            currentHls.attachMedia(videoEl);
            currentHls.on(Hls.Events.MANIFEST_PARSED, function () {
                videoEl.play().catch(function () {});
                hideOverlay();
                setStatus('live', 'Live · ' + table.name);
            });
            currentHls.on(Hls.Events.ERROR, function (_event, data) {
                if (data.fatal) {
                    setStatus('error', 'Stream unavailable');
                    showOverlay('Unable to load stream. Check MediaMTX / camera path.', true);
                    setProtocolBadge('');
                }
            });
            return;
        }

        if (videoEl.canPlayType('application/vnd.apple.mpegurl')) {
            videoEl.src = hlsUrl;
            videoEl.addEventListener('loadedmetadata', function onMeta() {
                videoEl.removeEventListener('loadedmetadata', onMeta);
                videoEl.play().catch(function () {});
                hideOverlay();
                setStatus('live', 'Live · ' + table.name);
            });
            return;
        }

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

        currentReader = new MediaMTXWebRTCReader({
            url: whepUrl,
            onError: function (err) {
                console.warn('WebRTC error, falling back to HLS:', err);
                if (!usingFallback) {
                    cleanupPlayer();
                    loadStreamHls(table);
                }
            },
            onTrack: function (evt) {
                if (evt.streams && evt.streams[0]) {
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

        tableListEl.innerHTML = '';
        tableCountEl.textContent = tables.length === 1 ? '1 table' : tables.length + ' tables';

        if (!tables.length) {
            showOverlay('No tables configured yet. Use the admin panel.', true);
            setStatus('error', 'No tables');
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

    async function loadConfig() {
        try {
            const response = await fetch('/api/live-config.php', { cache: 'no-store' });
            if (response.ok) {
                return await response.json();
            }
        } catch (err) {
            console.warn('API config unavailable, using fallback:', err);
        }
        return window.RAILSHOT_LIVE_CONFIG || null;
    }

    async function init() {
        config = await loadConfig();

        if (!config || !Array.isArray(config.tables)) {
            setStatus('error', 'Missing streams config');
            showOverlay('Configuration missing. Set up admin at /admin/', true);
            return;
        }

        buildTableList();
        initVideoFitControls();
    }

    window.addEventListener('beforeunload', cleanupPlayer);
    init();
})();
