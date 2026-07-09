# RailShot TV — Camera Streaming Setup

This folder contains everything needed to set up automatic FFmpeg streaming from your Reolink cameras to YouTube Live on your VPS.

## How It Works

Each camera runs as a **systemd service** on your VPS. The service:
- Pulls the RTSP stream from your Reolink camera
- Pushes it to YouTube Live via RTMP
- **Auto-restarts** if the camera disconnects or FFmpeg crashes
- **Auto-starts** when your VPS reboots

---

## Step 1 — Get Your YouTube Stream Keys

You need one YouTube stream key **per camera/table**.

1. Go to [studio.youtube.com](https://studio.youtube.com)
2. Click the camera icon (top right) → **Go Live**
3. Choose **Stream** (not Webcam or Mobile)
4. Under **Stream settings** → copy your **Stream key**
5. Repeat for each table (create a new stream for each one)

> **Tip:** Set each stream to **"Unlisted"** so only people with the link can find it on YouTube.

---

## Step 2 — Edit cameras.conf

Open `cameras.conf` and fill in your camera details:

```
table1 | rtsp://admin:YOUR_PASSWORD@192.168.68.89:554/h264Preview_01_main | your-youtube-stream-key
table2 | rtsp://admin:YOUR_PASSWORD@192.168.68.90:554/h264Preview_01_main | your-second-stream-key
```

**Reolink RTSP URL format:**
```
rtsp://USERNAME:PASSWORD@CAMERA_IP:554/h264Preview_01_main
```
- Default username: `admin`
- Default port: `554`
- Main stream (high quality): `/h264Preview_01_main`
- Sub stream (lower bandwidth): `/h264Preview_01_sub`

---

## Step 3 — Run the Setup Script

SSH into your VPS and run:

```bash
cd /var/www/html/streaming   # or wherever your site files are
sudo bash setup-streams.sh
```

That's it. All streams will start automatically.

---

## Step 4 — Add YouTube Embed URLs to RailShot TV Admin

For each table, get the YouTube embed URL and paste it into the admin panel:

1. Go to your YouTube channel → Live tab → find your stream
2. The embed URL format is:
   ```
   https://www.youtube.com/embed/LIVE_VIDEO_ID?autoplay=1
   ```
   Or use the channel live URL:
   ```
   https://www.youtube.com/embed/live_stream?channel=YOUR_CHANNEL_ID
   ```
3. In RailShot TV Admin → Venues → Camera card → **YouTube Live URL** field → paste it → Save

---

## Management Commands

| Task | Command |
|---|---|
| Check all stream statuses | `bash status-streams.sh` |
| Stop all streams | `sudo bash stop-streams.sh` |
| View live logs for table1 | `sudo journalctl -u railshot-stream@table1 -f` |
| Restart table1 stream | `sudo systemctl restart railshot-stream@table1` |
| Stop table1 stream | `sudo systemctl stop railshot-stream@table1` |
| Start table1 stream | `sudo systemctl start railshot-stream@table1` |
| Add a new camera later | Edit `cameras.conf`, run `sudo bash setup-streams.sh` again |

---

## Troubleshooting

**Stream not connecting to camera:**
```bash
sudo journalctl -u railshot-stream@table1 -n 50
```
Look for `Connection refused` (wrong IP/port) or `401 Unauthorized` (wrong password).

**YouTube rejecting the stream:**
- Double-check your stream key in `/etc/railshot/streams/table1.env`
- Make sure the YouTube live stream is set to **"Start automatically"** or is already scheduled

**High CPU usage:**
- The `-c:v copy` flag means FFmpeg is NOT re-encoding video (just passing it through), so CPU usage should be very low — typically under 5% per stream.
