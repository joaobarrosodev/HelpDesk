#!/bin/bash
# WebSocket Server Status Script for HelpDesk

# Get the directory where this script is located
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Change to the HelpDesk directory
cd "$DIR"

echo "=== WebSocket Server Status ==="
echo ""

# Check if server is running on port
if lsof -Pi :8080 -sTCP:LISTEN -t >/dev/null ; then
    PID=$(lsof -Pi :8080 -sTCP:LISTEN -t)
    echo "✅ WebSocket server is RUNNING"
    echo "   PID: $PID"
    echo "   Port: 8080"
    
    # Check log file
    LOG_FILE="logs/websocket_$(date +%Y-%m-%d).log"
    if [ -f "$LOG_FILE" ]; then
        echo "   Log: $LOG_FILE"
        echo ""
        echo "=== Last 10 log entries ==="
        tail -10 "$LOG_FILE"
    fi
else
    echo "❌ WebSocket server is NOT RUNNING"
    
    # Check for PID file
    if [ -f temp/ws-server.pid ]; then
        echo "   ⚠️  Stale PID file found"
    fi
fi

echo ""
echo "=== Temp Files ==="

# Count sync files
SYNC_COUNT=$(find temp -name "sync_*.txt" 2>/dev/null | wc -l)
echo "   Sync files: $SYNC_COUNT"

# Count message files
MSG_COUNT=$(find temp -name "ws_message_*.json" 2>/dev/null | wc -l)
echo "   Message files: $MSG_COUNT"

# Check for lock files
if [ -f temp/ws_autostart.lock ]; then
    echo "   ⚠️  Auto-start lock file exists"
fi

if [ -f temp/ws-server-starting.flag ]; then
    echo "   ⚠️  Server starting flag exists"
fi