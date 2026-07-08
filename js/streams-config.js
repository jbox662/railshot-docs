/**
 * RailShot TV — Live table configuration
 *
 * Edit `tables` to add/remove cameras. Each `id` must match MediaMTX paths.
 */
(function () {
    var loc = typeof location !== 'undefined' ? location : null;
    var isLocal = !loc || loc.protocol === 'file:' ||
        loc.hostname === 'localhost' || loc.hostname === '127.0.0.1';

    var webrtcBaseUrl;
    var hlsBaseUrl;
    var preferredProtocol;

    if (isLocal) {
        // MediaMTX on this PC
        webrtcBaseUrl = 'http://127.0.0.1:8889';
        hlsBaseUrl = 'http://127.0.0.1:8888';
        preferredProtocol = 'webrtc';
    } else if (loc.protocol === 'https:') {
        webrtcBaseUrl = loc.origin + '/api/webrtc.php?path=';
        hlsBaseUrl = loc.origin + '/api/hls.php?path=';
        preferredProtocol = 'hls';
    } else {
        // HTTP site — direct to VPS MediaMTX
        webrtcBaseUrl = 'http://160.153.184.255:8889';
        hlsBaseUrl = 'http://160.153.184.255:8888';
        preferredProtocol = 'webrtc';
    }

    window.RAILSHOT_LIVE_CONFIG = {
        webrtcBaseUrl: webrtcBaseUrl,
        hlsBaseUrl: hlsBaseUrl,
        preferredProtocol: preferredProtocol,

        tables: [
            {
                id: 'table1',
                name: 'Test Camera',
                description: '192.168.68.89'
            }
        ]
    };
})();
