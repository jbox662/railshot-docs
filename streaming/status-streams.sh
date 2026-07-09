#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# RailShot TV — Stream Status Check
# Shows the status of all configured camera streams at a glance.
# Usage: bash status-streams.sh
# ═══════════════════════════════════════════════════════════════════════════

ENV_DIR="/etc/railshot/streams"

if [[ ! -d "$ENV_DIR" ]]; then
    echo "No streams configured yet. Run: sudo bash setup-streams.sh"
    exit 0
fi

echo ""
echo "═══════════════════════════════════════════════════════"
echo "  RailShot TV — Stream Status"
echo "═══════════════════════════════════════════════════════"
echo ""

COUNT=0
for env_file in "$ENV_DIR"/*.env; do
    [[ -f "$env_file" ]] || continue
    TABLE=$(basename "$env_file" .env)
    SERVICE="railshot-stream@$TABLE.service"
    STATUS=$(systemctl is-active "$SERVICE" 2>/dev/null)

    if [[ "$STATUS" == "active" ]]; then
        ICON="🟢"
        LABEL="RUNNING"
    elif [[ "$STATUS" == "activating" ]]; then
        ICON="🟡"
        LABEL="STARTING"
    else
        ICON="🔴"
        LABEL="STOPPED ($STATUS)"
    fi

    echo "  $ICON  $TABLE  —  $LABEL"
    COUNT=$((COUNT + 1))
done

if [[ $COUNT -eq 0 ]]; then
    echo "  No streams configured."
fi

echo ""
echo "  Commands:"
echo "    View logs:   sudo journalctl -u railshot-stream@TABLE -f"
echo "    Restart:     sudo systemctl restart railshot-stream@TABLE"
echo ""
