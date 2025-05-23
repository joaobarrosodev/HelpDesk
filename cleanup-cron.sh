#!/bin/bash
# Cleanup script for HelpDesk WebSocket temp files

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$DIR"

# Clean sync files older than 5 minutes
find temp -name "sync_*.txt" -mmin +5 -delete 2>/dev/null

# Clean message files older than 10 minutes
find temp -name "ws_message_*.json" -mmin +10 -delete 2>/dev/null
find temp -name "ws_send_*.json" -mmin +10 -delete 2>/dev/null

# Clean lock files older than 1 hour
find temp -name "*.lock" -mmin +60 -delete 2>/dev/null
find temp -name "*.flag" -mmin +60 -delete 2>/dev/null

# Rotate logs older than 7 days
find logs -name "*.log" -mtime +7 -delete 2>/dev/null

# Log cleanup
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Cleanup completed" >> logs/cleanup.log
