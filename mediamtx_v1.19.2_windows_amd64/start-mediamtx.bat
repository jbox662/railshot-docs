@echo off
cd /d "%~dp0"
echo Starting MediaMTX (RailShot TV test camera)...
echo Camera: 192.168.68.89  ^|  HLS: http://127.0.0.1:8888/table1/index.m3u8
echo WebRTC: http://127.0.0.1:8889/table1/
echo.
mediamtx.exe
pause
