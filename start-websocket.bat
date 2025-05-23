@echo off
cd /d "C:\caminho\para\helpdesk"

:: Check if already running
netstat -an | find "8080" | find "LISTENING" >nul
if %errorlevel% == 0 (
    echo WebSocket server is already running
    exit /b 0
)

:: Create directories if needed
if not exist temp mkdir temp
if not exist logs mkdir logs

:: Start server
echo Starting WebSocket server...
start /B "HelpDesk WebSocket" php ws-server.php > logs\websocket_%date:~-4,4%-%date:~-10,2%-%date:~-7,2%.log 2>&1

echo WebSocket server started