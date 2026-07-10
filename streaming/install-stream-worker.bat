@echo off
REM Run once on the VPS as Administrator (right-click -> Run as administrator)
REM Installs a background worker that owns FFmpeg — the website only sends commands.

set "LOOP=%~dp0stream-worker-loop.cmd"
set "TASK=RailShot-StreamWorker"

echo Installing %TASK% (starts at boot, runs as SYSTEM)...
echo Loop script: %LOOP%

schtasks /delete /tn "%TASK%" /f >nul 2>&1
schtasks /create /tn "%TASK%" /tr "\"%LOOP%\"" /sc ONSTART /ru SYSTEM /rl HIGHEST /f
if errorlevel 1 (
    echo FAILED to create task. Run this window as Administrator.
    pause
    exit /b 1
)

echo.
echo Removing legacy tasks that fight Go Live...
for /f "tokens=1" %%T in ('schtasks /query /fo TABLE /nh 2^>nul ^| findstr /i "RailShot-table RailShot-KillFFmpeg"') do (
    echo Deleting %%T
    schtasks /delete /tn "%%T" /f >nul 2>&1
)

echo.
echo Starting worker now...
schtasks /run /tn "%TASK%"
timeout /t 4 /nobreak >nul

set "HB=%~dp0..\App_Data\railshot\stream-worker\heartbeat.json"
if exist "%HB%" (
    echo SUCCESS — worker heartbeat file found.
    type "%HB%"
) else (
    echo WARNING: heartbeat not found yet. Wait 10 seconds and open admin/stream-status.php
)

echo.
echo Done. Go Live / table switching now uses the worker instead of IIS killing FFmpeg.
pause
