@echo off
REM Run once on the VPS as Administrator (right-click -> Run as administrator)
REM Lets the website stop FFmpeg started by Task Scheduler / SYSTEM when switching tables.

set "SCRIPT=%~dp0kill-ffmpeg.cmd"
set "TASK=RailShot-KillFFmpeg"

echo Installing %TASK% scheduled task...
echo Script: %SCRIPT%

schtasks /delete /tn "%TASK%" /f >nul 2>&1
schtasks /create /tn "%TASK%" /tr "\"%SCRIPT%\"" /sc ONCE /sd 01/01/2099 /st 00:00 /ru SYSTEM /rl HIGHEST /f
if errorlevel 1 (
    echo FAILED to create task. Run this window as Administrator.
    pause
    exit /b 1
)

echo.
echo SUCCESS. Testing kill task...
schtasks /run /tn "%TASK%"
timeout /t 2 /nobreak >nul

echo.
echo Also remove old auto-start stream tasks (they fight Go Live):
schtasks /query /fo TABLE /nh 2>nul | findstr /i "RailShot-table"
if not errorlevel 1 (
    echo Deleting RailShot-table* tasks...
    for /f "tokens=1" %%T in ('schtasks /query /fo TABLE /nh 2^>nul ^| findstr /i "RailShot-table"') do schtasks /delete /tn "%%T" /f
)

echo.
echo Done. You can switch tables from the website without opening Task Manager.
pause
