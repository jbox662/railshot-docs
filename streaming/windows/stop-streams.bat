@echo off
:: ═══════════════════════════════════════════════════════════════════════════
:: RailShot TV — Stop All Streams (Windows)
:: Stops all running RailShot camera streams and kills FFmpeg processes.
:: Run as Administrator.
:: ═══════════════════════════════════════════════════════════════════════════

net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: Please right-click and choose "Run as Administrator"
    pause
    exit /b 1
)

echo Stopping all RailShot streams...
echo.

:: Stop all RailShot scheduled tasks
for /f "tokens=1" %%T in ('schtasks /query /fo LIST 2^>nul ^| findstr /i "RailShot-"') do (
    echo Stopping task: %%T
    schtasks /end /tn "%%T" >nul 2>&1
)

:: Kill any remaining ffmpeg processes
taskkill /f /im ffmpeg.exe >nul 2>&1
echo Killed any remaining FFmpeg processes.

echo.
echo All streams stopped.
pause
