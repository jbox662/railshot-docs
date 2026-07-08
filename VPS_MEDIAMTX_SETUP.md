# Deploy MediaMTX on Windows Plesk VPS

Your website stays in Plesk `httpdocs`. MediaMTX runs as a **Windows service** on the same VPS (or another Windows box). Browsers never talk RTSP directly to the camera.

## Your VPS

- Plesk: https://160.153.184.255:8443/
- Public IP: **160.153.184.255**
- `js/streams-config.js` already uses `mediamtxHost = "160.153.184.255"`

## What already works locally

- Camera `192.168.68.89` → MediaMTX on your PC (`192.168.68.91`) → `/live.html`
- Keep using `start-mediamtx.bat` on the PC until the VPS path is ready

## Goal

```
Camera / NVR (hall LAN)
        ↓  VPN or RTSP reachability
MediaMTX on Windows VPS 160.153.184.255  (:8888 HLS, :8889 WebRTC)
        ↓
https://your-domain/live.html  (Plesk)
```

## A. Install MediaMTX on the Windows VPS

1. RDP into the VPS (same box as Plesk at `160.153.184.255`).
2. Copy the whole folder  
   `mediamtx_v1.19.2_windows_amd64`  
   to e.g. `C:\mediamtx\`
3. Confirm `C:\mediamtx\mediamtx.yml` includes:
   - `webrtcAdditionalHosts: [..., 160.153.184.255]`
   - camera `paths` whose `id`s match `js/streams-config.js`
   - a camera URL reachable **from the VPS** (see section B)
4. Right‑click **`open-firewall.bat`** → Run as administrator.
5. Test once in a console:

```bat
cd C:\mediamtx
mediamtx.exe
```

6. When it works, Run as administrator: **`install-service.bat`**  
   (needs [NSSM](https://nssm.cc/download) — put `nssm.exe` in `C:\mediamtx\` first)

7. Also open **8888 / 8889** (and UDP **8189**) in your VPS provider’s firewall / security group if they have one outside Windows.

## B. Let the VPS reach the camera (required)

The VPS cannot use `192.168.68.89` until you create a path:

### Option 1 — VPN (recommended)

- Install WireGuard (or OpenVPN) on the VPS **and** on the hall router or a tiny always-on device at the hall.
- Once the VPS can `ping 192.168.68.89`, leave the RTSP URL as:

```yaml
source: rtsp://admin:PASSWORD@192.168.68.89:554/h264Preview_01_main
```

### Option 2 — Port forward RTSP (simpler, less secure)

On the hall router, forward a public port (e.g. **8554**) → `192.168.68.89:554`.  
On the VPS `mediamtx.yml` use:

```yaml
source: rtsp://admin:PASSWORD@HALL_PUBLIC_IP:8554/h264Preview_01_main
```

Use a strong camera password. Prefer IP allowlisting the VPS only, if your router supports it.

**Until B works, MediaMTX on the VPS will start but Live will fail when pulling the camera.**

## C. Point the website at MediaMTX

Edit `js/streams-config.js` on the site (Plesk upload):

```js
var mediamtxHost = "YOUR.VPS.PUBLIC.IP";  // or your streaming hostname
```

Leave `tables` as they are (or add more). Upload `live.html`, `css/`, `js/` to `httpdocs`.

Open: `https://yoursite.com/live.html`

### HTTPS note

If the site is **https://** and MediaMTX is **http://IP:8889**, some browsers may block the stream (mixed content). Fixes later:
- reverse-proxy MediaMTX behind HTTPS on the VPS (IIS / Caddy / Nginx), or
- temporarily test with HLS/`http` carefully

## D. Add more tables later

1. Add to `js/streams-config.js` → `tables`
2. Add matching `paths:` block in `mediamtx.yml`
3. Restart the Windows service:

```bat
nssm restart RailShotMediaMTX
```

## Quick checks

| Check | URL / command |
|--------|----------------|
| HLS | `http://VPS_IP:8888/table1/index.m3u8` |
| Service | `sc query RailShotMediaMTX` |
| Camera from VPS | `ping 192.168.68.89` (only after VPN / route exists) |

## Do not commit camera passwords

Before pushing to GitHub, replace real passwords in `mediamtx.yml` with placeholders.
