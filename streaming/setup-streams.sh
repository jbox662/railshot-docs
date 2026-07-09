#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# RailShot TV — Stream Setup Script
# Run once as root/sudo to install all camera streaming services.
# Usage: sudo bash setup-streams.sh
# ═══════════════════════════════════════════════════════════════════════════

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONF_FILE="$SCRIPT_DIR/cameras.conf"
SERVICE_FILE="$SCRIPT_DIR/railshot-stream@.service"
ENV_DIR="/etc/railshot/streams"
SYSTEMD_DIR="/etc/systemd/system"

# ── Checks ────────────────────────────────────────────────────────────────
if [[ $EUID -ne 0 ]]; then
    echo "ERROR: Please run this script with sudo."
    echo "       sudo bash setup-streams.sh"
    exit 1
fi

if ! command -v ffmpeg &>/dev/null; then
    echo "ERROR: FFmpeg is not installed. Install it with:"
    echo "       sudo apt install ffmpeg"
    exit 1
fi

if [[ ! -f "$CONF_FILE" ]]; then
    echo "ERROR: cameras.conf not found at $CONF_FILE"
    exit 1
fi

# ── Install service template ──────────────────────────────────────────────
echo "Installing systemd service template..."
cp "$SERVICE_FILE" "$SYSTEMD_DIR/railshot-stream@.service"
systemctl daemon-reload
echo "  ✓ Service template installed"

# ── Create env directory ──────────────────────────────────────────────────
mkdir -p "$ENV_DIR"
chmod 750 "$ENV_DIR"

# ── Process each camera line ──────────────────────────────────────────────
INSTALLED=0

while IFS= read -r line; do
    # Skip blank lines and comments
    [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue

    # Parse: TABLE_NAME | RTSP_URL | YOUTUBE_STREAM_KEY
    IFS='|' read -r TABLE RTSP_URL YOUTUBE_KEY <<< "$line"

    TABLE=$(echo "$TABLE" | xargs)
    RTSP_URL=$(echo "$RTSP_URL" | xargs)
    YOUTUBE_KEY=$(echo "$YOUTUBE_KEY" | xargs)

    if [[ -z "$TABLE" || -z "$RTSP_URL" || -z "$YOUTUBE_KEY" ]]; then
        echo "  SKIP: Invalid line (missing fields): $line"
        continue
    fi

    echo ""
    echo "Setting up: $TABLE"
    echo "  RTSP:    $RTSP_URL"
    echo "  YT Key:  ${YOUTUBE_KEY:0:8}..."

    # Write env file
    ENV_FILE="$ENV_DIR/$TABLE.env"
    cat > "$ENV_FILE" <<EOF
RTSP_URL=$RTSP_URL
YOUTUBE_KEY=$YOUTUBE_KEY
EOF
    chmod 640 "$ENV_FILE"
    echo "  ✓ Config saved to $ENV_FILE"

    # Enable and start the service
    systemctl enable "railshot-stream@$TABLE.service" 2>/dev/null || true
    systemctl restart "railshot-stream@$TABLE.service"
    echo "  ✓ Service started: railshot-stream@$TABLE"

    INSTALLED=$((INSTALLED + 1))

done < "$CONF_FILE"

echo ""
echo "═══════════════════════════════════════════════════════"
echo "  Done! $INSTALLED camera stream(s) configured."
echo "═══════════════════════════════════════════════════════"
echo ""
echo "Useful commands:"
echo "  Check status:   sudo systemctl status railshot-stream@table1"
echo "  View logs:      sudo journalctl -u railshot-stream@table1 -f"
echo "  Stop a stream:  sudo systemctl stop railshot-stream@table1"
echo "  Start a stream: sudo systemctl start railshot-stream@table1"
echo "  Stop all:       sudo bash stop-streams.sh"
echo ""
