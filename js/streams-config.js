/**
 * RailShot TV — Live table configuration
 *
 * Edit this file to add or remove tables. No code changes required elsewhere.
 * Each `id` must match a path name in mediamtx/mediamtx.yml.
 *
 * After editing tables here, mirror the same paths in mediamtx.yml
 * so MediaMTX knows which RTSP sources to pull.
 */
window.RAILSHOT_LIVE_CONFIG = {
    /**
     * MediaMTX public base URLs (no trailing slash).
     * Replace YOUR_SERVER with your VPS hostname or public IP.
     * Use https:// when MediaMTX is behind TLS (recommended for production).
     */
    webrtcBaseUrl: "http://YOUR_SERVER:8889",
    hlsBaseUrl: "http://YOUR_SERVER:8888",

    /** Prefer WebRTC; set to "hls" to force HLS only. */
    preferredProtocol: "webrtc",

    /**
     * Tables list — add, remove, or rename freely.
     * - id: MediaMTX path name (must match mediamtx.yml)
     * - name: Label shown in the UI
     * - description: Optional short subtitle
     */
    tables: [
        {
            id: "table1",
            name: "Table 1",
            description: "Main floor"
        },
        {
            id: "table2",
            name: "Table 2",
            description: "Main floor"
        },
        {
            id: "table3",
            name: "Table 3",
            description: "Main floor"
        }
        // Add more tables as needed, for example:
        // { id: "table4", name: "Table 4", description: "Back room" },
    ]
};
