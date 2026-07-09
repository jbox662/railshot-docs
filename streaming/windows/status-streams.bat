@echo off
:: ═══════════════════════════════════════════════════════════════════════════
:: RailShot TV — Stream Status (Windows)
:: Shows which camera streams are currently running.
:: ═══════════════════════════════════════════════════════════════════════════

echo.
echo ═══════════════════════════════════════════════════════
echo   RailShot TV — Stream Status
echo ═══════════════════════════════════════════════════════
echo.

:: Check if FFmpeg is running at all
tasklist /fi "imagename eq ffmpeg.exe" 2>nul | find /i "ffmpeg.exe" >nul
if %errorLevel% equ 0 (
    echo   FFmpeg is RUNNING
) else (
    echo   FFmpeg is NOT running
)

echo.
echo   Scheduled Tasks:
echo   ─────────────────────────────────────────────────────
schtasks /query /fo TABLE /nh 2>nul | findstr /i "RailShot-"

echo.
echo   To view live logs, check:
echo   C:\RailShotStreams\
echo.
pause
