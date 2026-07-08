/**
 * RailShot TV — Live table configuration
 *
 * Edit `tables` to add/remove cameras.
 * Each table `id` must match a path name in MediaMTX (mediamtx.yml).
 *
 * mediamtxHost:
 *   - Local PC test (file://): use "" so it falls back to 127.0.0.1
 *   - Production (site on Plesk / viewers anywhere): VPS public IP below
 */
(function () {
    // Windows Plesk VPS — MediaMTX on this host (ports 8888 / 8889)
    // For local-only testing with MediaMTX on your PC, temporarily set to "" or "127.0.0.1"
    var mediamtxHost = "160.153.184.255";

    if (!mediamtxHost) {
        if (typeof location !== "undefined" && location.protocol !== "file:") {
            var h = location.hostname;
            if (h && h !== "localhost" && h !== "127.0.0.1") {
                mediamtxHost = h;
            } else {
                mediamtxHost = "127.0.0.1";
            }
        } else {
            mediamtxHost = "127.0.0.1";
        }
    }

    window.RAILSHOT_LIVE_CONFIG = {
        webrtcBaseUrl: "http://" + mediamtxHost + ":8889",
        hlsBaseUrl: "http://" + mediamtxHost + ":8888",

        preferredProtocol: "webrtc",

        tables: [
            {
                id: "table1",
                name: "Test Camera",
                description: "192.168.68.89"
            }
        ]
    };
})();
