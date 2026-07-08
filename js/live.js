/**
 * RailShot TV — Live Tables player
 * Reads window.RAILSHOT_LIVE_CONFIG and builds a dynamic table list.
 * Tries WebRTC via MediaMTX, falls back to HLS.
 */

(function () {
    const config = window.RAILSHOT_LIVE_CONFIG;

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

    let currentReader = null;
    let currentHls = null;
    let currentTableId = null;
    let usingFallback = false;

    if (!config || !Array.isArray(config.tables)) {
        setStatus('error', 'Missing streams config');
        showOverlay('Configuration missing. Create js/streams-config.js', true);
        return;
    }

    function stripTrailingSlash(url) {
        return String(url || '').replace(/\/+$/, '');
    }

    function setStatus(state, text) {
        if (statusEl) {
            statusEl.classList.remove('is-live', 'is-connecting', 'is-error');
            if (state) statusEl.classList.add(`is-${state}`);
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
            try {
                currentReader.close();
            } catch (err) {
                console.warn('WebRTC close error:', err);
            }
            currentReader = null;
        }

        if (currentHls) {
            try {
                currentHls.destroy();
            } catch (err) {
                console.warn('HLS destroy error:', err);
            }
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
        const hlsUrl = `${base}/${table.id}/index.m3u8`;

        usingFallback = true;
        setProtocolBadge('HLS');
        setStatus('connecting', `Connecting to ${table.name}…`);
        showOverlay(`Connecting to ${table.name}…`);

        if (window.Hls && Hls.isSupported()) {
            currentHls = new Hls({
                enableWorker: true,
                lowLatencyMode: true
            });
            currentHls.loadSource(hlsUrl);
            currentHls.attachMedia(videoEl);
            currentHls.on(Hls.Events.MANIFEST_PARSED, function () {
                videoEl.play().catch(function () {});
                hideOverlay();
                setStatus('live', `Live · ${table.name}`);
            });
            currentHls.on(Hls.Events.ERROR, function (_event, data) {
                if (data.fatal) {
                    setStatus('error', 'Stream unavailable');
                    showOverlay('Unable to load stream. Check MediaMTX / HLS URL.', true);
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
                setStatus('live', `Live · ${table.name}`);
            });
            videoEl.addEventListener('error', function onErr() {
                videoEl.removeEventListener('error', onErr);
                setStatus('error', 'Stream unavailable');
                showOverlay('Unable to load stream. Check MediaMTX / HLS URL.', true);
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
        const whepUrl = `${base}/${table.id}/whep`;

        usingFallback = false;
        setProtocolBadge('WebRTC');
        setStatus('connecting', `Connecting to ${table.name}…`);
        showOverlay(`Connecting to ${table.name}…`);

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
                    setStatus('live', `Live · ${table.name}`);
                    setProtocolBadge('WebRTC');
                }
            }
        });
    }

    function loadTable(table) {
        if (!table) return;

        currentTableId = table.id;
        titleEl.textContent = table.name;
        descEl.textContent = table.description || '';

        document.querySelectorAll('.table-item').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.stream === table.id);
            btn.setAttribute('aria-selected', btn.dataset.stream === table.id ? 'true' : 'false');
        });

        cleanupPlayer();

        if (
            isPlaceholderHost(config.webrtcBaseUrl) &&
            isPlaceholderHost(config.hlsBaseUrl)
        ) {
            setStatus('error', 'Server not configured');
            showOverlay('Set webrtcBaseUrl / hlsBaseUrl in js/streams-config.js', true);
            setProtocolBadge('');
            return;
        }

        const preferHls = String(config.preferredProtocol || '').toLowerCase() === 'hls';
        if (preferHls) {
            loadStreamHls(table);
        } else {
            loadStreamWebRtc(table);
        }
    }

    function buildTableList() {
        const tables = config.tables.filter(function (t) {
            return t && t.id && t.name;
        });

        tableListEl.innerHTML = '';
        tableCountEl.textContent = tables.length === 1
            ? '1 table'
            : `${tables.length} tables`;

        if (!tables.length) {
            showOverlay('No tables configured yet.', true);
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
                `<span class="table-item-name"></span>` +
                (table.description
                    ? `<span class="table-item-desc"></span>`
                    : '');
            btn.querySelector('.table-item-name').textContent = table.name;
            const descNode = btn.querySelector('.table-item-desc');
            if (descNode) descNode.textContent = table.description;

            btn.addEventListener('click', function () {
                loadTable(table);
            });

            li.appendChild(btn);
            tableListEl.appendChild(li);

            if (index === 0) {
                loadTable(table);
            }
        });
    }

    window.addEventListener('beforeunload', cleanupPlayer);
    buildTableList();
})();
