#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# RailShot TV — Stop All Streams
# Stops all railshot-stream services without disabling auto-restart on reboot.
# Usage: sudo bash stop-streams.sh
# ═══════════════════════════════════════════════════════════════════════════

if [[ $EUID -ne 0 ]]; then
    echo "ERROR: Please run with sudo."
    exit 1
fi

STOPPED=0
while IFS= read -r svc; do
    NAME=$(basename "$svc")
    echo "Stopping $NAME..."
    systemctl stop "$NAME" && STOPPED=$((STOPPED + 1))
done < <(find /etc/systemd/system -name "railshot-stream@*.service" 2>/dev/null)

# Also stop any running instances found via systemctl
while IFS= read -r unit; do
    [[ -z "$unit" ]] && continue
    echo "Stopping $unit..."
    systemctl stop "$unit" 2>/dev/null && STOPPED=$((STOPPED + 1))
done < <(systemctl list-units --type=service --state=running --no-legend 2>/dev/null | grep "railshot-stream@" | awk '{print $1}')

echo ""
echo "Stopped $STOPPED stream service(s)."
