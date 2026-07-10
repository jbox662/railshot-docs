@echo off
REM Run once on the VPS as Administrator (right-click -> Run as administrator)
REM Installs a background worker that owns FFmpeg — the website only sends commands.

set "LOOP=%~dp0stream-worker-loop.cmd"
set "TASK=RailShot-StreamWorker"
set "PHPCFG=%~dp0stream-worker-php.txt"

echo Installing %TASK% (starts at boot, runs as SYSTEM)...
echo Loop script: %LOOP%

if not exist "%PHPCFG%" (
    echo.
    echo NOTE: stream-worker-php.txt not found.
    echo Open https://railshottv.com/streaming/php-path-setup.php while logged into admin FIRST,
    echo or run find-php.bat in this folder, then re-run this installer.
    echo.
)

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
echo Waiting for worker to start...
timeout /t 12 /nobreak >nul

set "HB=%~dp0..\App_Data\railshot\stream-worker\heartbeat.json"
set "LOOPLOG=%~dp0stream-worker-loop.log"
set "WORKLOG=%~dp0worker.log"

if exist "%HB%" (
    echo SUCCESS — worker heartbeat file found.
    type "%HB%"
) else (
    echo WARNING: heartbeat not found yet.
    if exist "%LOOPLOG%" (
        echo.
        echo stream-worker-loop.log ^(last lines^):
        powershell -Command "Get-Content '%LOOPLOG%' -Tail 10"
    ) else (
        echo No stream-worker-loop.log — scheduled task may not have started. Check Task Scheduler.
    )
    if exist "%WORKLOG%" (
        echo.
        echo worker.log ^(last lines^):
        powershell -Command "Get-Content '%WORKLOG%' -Tail 10"
    )
    echo.
    echo FIX: Open https://railshottv.com/streaming/php-path-setup.php ^(admin login^)
    echo      Then re-run this installer as Administrator.
)

echo.
echo Done.
pause
