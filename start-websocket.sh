#!/bin/bash
# WebSocket Server Startup Script for HelpDesk

# Get the directory where this script is located
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Change to the HelpDesk directory
cd "$DIR"

# Check if server is already running
if lsof -Pi :8080 -sTCP:LISTEN -t >/dev/null ; then
    echo "WebSocket server is already running on port 8080"
    exit 0
fi

# Create necessary directories
mkdir -p temp logs

# Start the WebSocket server
echo "Starting WebSocket server..."
nohup php ws-server.php > logs/websocket_$(date +%Y-%m-%d).log 2>&1 &

# Get the PID
PID=$!
echo $PID > temp/ws-server.pid

# Wait a moment and check if it started successfully
sleep 2

if ps -p $PID > /dev/null ; then
    echo "WebSocket server started successfully (PID: $PID)"
    echo "Log file: logs/websocket_$(date +%Y-%m-%d).log"
else
    echo "Failed to start WebSocket server"
    exit 1
fi