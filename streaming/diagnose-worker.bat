@echo off
REM Test the PowerShell stream worker on the VPS.
cd /d "%~dp0"

echo === RailShot stream worker diagnostic ===
echo.

set "HB=..\App_Data\railshot\stream-worker\heartbeat.json"

echo [1] Scheduled task:
schtasks /query /tn "RailShot-StreamWorker" /fo LIST /v 2>nul | findstr /i "TaskName Status Last Run Next"
if errorlevel 1 echo     NOT FOUND — run install-stream-worker.bat as Administrator.
echo.

echo [2] Start worker manually for 6 seconds:
start /b powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0stream-worker.ps1"
timeout /t 6 /nobreak >nul
echo.

echo [3] Heartbeat:
if exist "%HB%" ( type "%HB%" ) else ( echo     NOT FOUND: %HB% )
echo.

echo [4] worker.log:
if exist worker.log (powershell -Command "Get-Content worker.log -Tail 15") else echo     (none)
echo.

echo [5] stream-worker-loop.log:
if exist stream-worker-loop.log (powershell -Command "Get-Content stream-worker-loop.log -Tail 15") else echo     (none)
echo.
pause
