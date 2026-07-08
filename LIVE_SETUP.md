# Live Tables setup (RailShot TV)

## What was added

- **Live** link on the main site nav + footer (homepage content unchanged)
- `/live.html` — player + dynamic table list
- `js/streams-config.js` — **edit this** to add/remove tables and set MediaMTX URLs
- `js/live.js` — WebRTC with HLS fallback
- `mediamtx/` — Docker + config templates for the stream server

## Add or remove tables

1. Edit `js/streams-config.js` — add/remove objects in `tables` (`id` + `name`).
2. Edit `mediamtx/mediamtx.yml` — add/remove matching `paths` entries with the same path `id`.
3. Set `webrtcBaseUrl` / `hlsBaseUrl` to your MediaMTX host (replace `YOUR_SERVER`).

Example table entry:

```js
{ id: "table4", name: "Table 4", description: "Back room" }
```

## MediaMTX on the VPS

```bash
cd mediamtx
# edit mediamtx.yml (NVR IP, password, paths)
# edit docker-compose.yml (MTX_WEBRTCADDITIONALHOSTS)
docker compose up -d
```

WebRTC: port **8889** · HLS: port **8888**

The website host must be able to reach MediaMTX; the MediaMTX host must reach the NVR on the LAN (or VPN).
