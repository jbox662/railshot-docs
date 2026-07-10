@echo off
REM Run on the VPS to test the stream worker (administrator not required).
cd /d "%~dp0"

echo === RailShot stream worker diagnostic ===
echo Site folder: %CD%
echo.

set "HB=..\App_Data\railshot\stream-worker\heartbeat.json"
set "WORKER_LOG=worker.log"
set "LOOP_LOG=stream-worker-loop.log"

echo [1] Scheduled task:
schtasks /query /tn "RailShot-StreamWorker" /fo LIST /v 2>nul | findstr /i "TaskName Status Last Run Next"
if errorlevel 1 echo     RailShot-StreamWorker NOT found — run install-stream-worker.bat as Administrator.
echo.

echo [2] PHP paths:
set "PHP="
for %%P in (
    "C:\Program Files (x86)\Plesk\Additional\Plesk PHP\8.4\php.exe"
    "C:\Program Files (x86)\Plesk\Additional\Plesk PHP\8.3\php.exe"
    "C:\Program Files (x86)\Plesk\Additional\Plesk PHP\8.2\php.exe"
) do if exist %%P echo     found %%P
where php >nul 2>&1 && echo     found php in PATH
echo.

echo [3] Start worker manually for 6 seconds:
for %%P in (
    "C:\Program Files (x86)\Plesk\Additional\Plesk PHP\8.4\php.exe"
    "C:\Program Files (x86)\Plesk\Additional\Plesk PHP\8.3\php.exe"
    "C:\Program Files (x86)\Plesk\Additional\Plesk PHP\8.2\php.exe"
) do if exist %%P set "PHP=%%~P"
if not defined PHP where php >nul 2>&1 && set "PHP=php"
if not defined PHP (
    echo     ERROR: php.exe not found
    goto :logs
)
echo     Using %PHP%
start /b "" "%PHP%" "%~dp0stream-worker.php"
timeout /t 6 /nobreak >nul
echo.

echo [4] Heartbeat:
if exist "%HB%" (
    echo     FOUND %HB%
    type "%HB%"
) else (
    echo     NOT FOUND %HB%
)
echo.

:logs
echo [5] worker.log:
if exist "%WORKER_LOG%" (powershell -Command "Get-Content '%WORKER_LOG%' -Tail 15") else echo     (none yet)
echo.
echo [6] stream-worker-loop.log:
if exist "%LOOP_LOG%" (powershell -Command "Get-Content '%LOOP_LOG%' -Tail 15") else echo     (none yet)
echo.
pause
