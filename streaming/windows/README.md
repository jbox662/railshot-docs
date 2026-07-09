# RailShot TV — Windows Camera Streaming Setup

This folder contains batch scripts to run your Reolink cameras as **automatic background streams** to YouTube Live on your Windows VPS. No extra software needed — uses Windows Task Scheduler built into every Windows machine.

---

## Step 1 — Get Your YouTube Stream Keys

You need one stream key **per camera/table**.

1. Go to [studio.youtube.com](https://studio.youtube.com)
2. Click the camera icon (top right) → **Go Live** → **Stream**
3. Copy your **Stream Key** (looks like `xxxx-xxxx-xxxx-xxxx-xxxx`)
4. Repeat for each table — each needs its own stream key

> Set each stream to **Unlisted** so only your website viewers can find it.

---

## Step 2 — Edit cameras.conf

Open `cameras.conf` in Notepad and fill in your cameras:

```
table1 | rtsp://admin:YOUR_PASSWORD@192.168.68.89:554/h264Preview_01_main | your-yt-stream-key
table2 | rtsp://admin:YOUR_PASSWORD@192.168.68.90:554/h264Preview_01_main | second-stream-key
```

**Reolink RTSP URL format:**
```
rtsp://admin:PASSWORD@CAMERA_IP:554/h264Preview_01_main
```

---

## Step 3 — Run setup-streams.bat as Administrator

1. Right-click `setup-streams.bat`
2. Choose **"Run as Administrator"**
3. Done — streams start immediately and will auto-start on every reboot

---

## Step 4 — Add YouTube Embed URLs to RailShot TV Admin

For each table, get the YouTube embed URL and paste it into the admin panel under each camera's **YouTube Live URL** field.

The embed URL format is:
```
https://www.youtube.com/embed/live_stream?channel=YOUR_CHANNEL_ID
```

---

## Management

| Task | How |
|---|---|
| Check stream status | Double-click `status-streams.bat` |
| Stop all streams | Double-click `stop-streams.bat` as Administrator |
| Restart one stream | Open Task Scheduler → find `RailShot-table1` → right-click → Run |
| View logs | Open `C:\RailShotStreams\` — each table has its own `.bat` file |

---

## Troubleshooting

**Stream not connecting to camera:**
Check the RTSP URL and password in `cameras.conf`. Test it by pasting the RTSP URL into VLC Media Player → Open Network Stream.

**YouTube rejecting the stream:**
Double-check your stream key. Make sure the YouTube live event is set to **"Start automatically"**.

**FFmpeg not found error:**
The FFmpeg path is already set to your installed location. If you ever update FFmpeg, edit the `FFMPEG=` line at the top of `setup-streams.bat`.
