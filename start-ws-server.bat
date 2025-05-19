@echo off
echo Starting HelpDesk WebSocket Server...
cd /D "C:\xampp\htdocs\infoexe\HelpDesk"
start "HelpDesk WebSocket Server" /MIN php ws-server.php
echo WebSocket server running in background. Do not close this window.
pause
