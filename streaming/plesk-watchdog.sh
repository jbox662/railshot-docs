#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# RailShot TV — Plesk Stream Watchdog
#
# Checks if each camera's FFmpeg process is running.
# If not, starts it. Designed to be run every minute via Plesk Scheduled Tasks.
#
# SETUP:
#   1. Edit cameras.conf with your real RTSP URLs and YouTube stream keys
#   2. In Plesk: Tools & Settings → Scheduled Tasks → Add Task
#      Command : bash /var/www/vhosts/railshottv.com/httpdocs/streaming/plesk-watchdog.sh
#      Schedule: Every 1 minute  (*/1 * * * *)
#   3. Make this script executable once via SSH:
#      chmod +x /var/www/vhosts/railshottv.com/httpdocs/streaming/plesk-watchdog.sh
#
# LOGS:  /tmp/railshot-stream-<table>.log  (last 500 lines kept per camera)
# ═══════════════════════════════════════════════════════════════════════════

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONF_FILE="$SCRIPT_DIR/cameras.conf"
LOG_DIR="/tmp"
FFMPEG=$(command -v ffmpeg 2>/dev/null || echo "/usr/bin/ffmpeg")

if [[ ! -f "$CONF_FILE" ]]; then
    echo "ERROR: cameras.conf not found at $CONF_FILE" >&2
    exit 1
fi

if [[ ! -x "$FFMPEG" ]]; then
    echo "ERROR: ffmpeg not found or not executable at $FFMPEG" >&2
    exit 1
fi

while IFS= read -r line; do
    # Skip blank lines and comments
    [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue

    # Parse: TABLE_NAME | RTSP_URL | YOUTUBE_STREAM_KEY
    IFS='|' read -r TABLE RTSP_URL YOUTUBE_KEY <<< "$line"
    TABLE=$(echo "$TABLE" | xargs)
    RTSP_URL=$(echo "$RTSP_URL" | xargs)
    YOUTUBE_KEY=$(echo "$YOUTUBE_KEY" | xargs)

    if [[ -z "$TABLE" || -z "$RTSP_URL" || -z "$YOUTUBE_KEY" ]]; then
        continue
    fi

    LOG_FILE="$LOG_DIR/railshot-stream-$TABLE.log"
    PID_FILE="$LOG_DIR/railshot-stream-$TABLE.pid"

    # Check if a process with the stored PID is still alive
    RUNNING=0
    if [[ -f "$PID_FILE" ]]; then
        OLD_PID=$(cat "$PID_FILE")
        if kill -0 "$OLD_PID" 2>/dev/null; then
            RUNNING=1
        fi
    fi

    if [[ "$RUNNING" -eq 1 ]]; then
        # Stream is already running — nothing to do
        continue
    fi

    # Stream is not running — start it
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting stream for $TABLE..." >> "$LOG_FILE"

    nohup "$FFMPEG" \
        -loglevel warning \
        -rtsp_transport tcp \
        -timeout 10000000 \
        -i "$RTSP_URL" \
        -c:v copy \
        -c:a aac \
        -b:a 128k \
        -ar 44100 \
        -f flv \
        -flvflags no_duration_filesize \
        "rtmp://a.rtmp.youtube.com/live2/$YOUTUBE_KEY" \
        >> "$LOG_FILE" 2>&1 &

    NEW_PID=$!
    echo "$NEW_PID" > "$PID_FILE"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] FFmpeg started (PID $NEW_PID)" >> "$LOG_FILE"

    # Keep log to last 500 lines to avoid disk fill
    tail -n 500 "$LOG_FILE" > "${LOG_FILE}.tmp" && mv "${LOG_FILE}.tmp" "$LOG_FILE"

done < "$CONF_FILE"
