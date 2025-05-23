#!/bin/bash
# WebSocket Server Stop Script for HelpDesk

# Get the directory where this script is located
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Change to the HelpDesk directory
cd "$DIR"

# Check for PID file
if [ -f temp/ws-server.pid ]; then
    PID=$(cat temp/ws-server.pid)
    
    # Check if process is running
    if ps -p $PID > /dev/null ; then
        echo "Stopping WebSocket server (PID: $PID)..."
        kill $PID
        
        # Wait for process to stop
        sleep 2
        
        # Force kill if still running
        if ps -p $PID > /dev/null ; then
            echo "Force stopping WebSocket server..."
            kill -9 $PID
        fi
        
        rm -f temp/ws-server.pid
        echo "WebSocket server stopped"
    else
        echo "WebSocket server is not running (stale PID file)"
        rm -f temp/ws-server.pid
    fi
else
    # Try to find process by port
    PID=$(lsof -Pi :8080 -sTCP:LISTEN -t)
    
    if [ ! -z "$PID" ]; then
        echo "Found WebSocket server on port 8080 (PID: $PID)"
        echo "Stopping server..."
        kill $PID
        
        sleep 2
        
        if lsof -Pi :8080 -sTCP:LISTEN -t >/dev/null ; then
            echo "Force stopping server..."
            kill -9 $PID
        fi
        
        echo "WebSocket server stopped"
    else
        echo "WebSocket server is not running"
    fi
fi

# Clean up any stale files
rm -f temp/ws-server-starting.flag
rm -f temp/ws_autostart.lock