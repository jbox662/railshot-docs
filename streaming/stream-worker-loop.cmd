@echo off
REM Keeps stream-worker.php running (restarts after crash). Used by RailShot-StreamWorker task.
cd /d "%~dp0"

set "PHP=php"
where php >nul 2>&1
if errorlevel 1 (
    if exist "C:\Program Files (x86)\Plesk\Additional\Plesk PHP\8.3\php.exe" (
        set "PHP=C:\Program Files (x86)\Plesk\Additional\Plesk PHP\8.3\php.exe"
    ) else if exist "C:\Program Files (x86)\Plesk\Additional\Plesk PHP\8.2\php.exe" (
        set "PHP=C:\Program Files (x86)\Plesk\Additional\Plesk PHP\8.2\php.exe"
    ) else (
        echo ERROR: php.exe not found. Install Plesk PHP or add php to PATH.
        exit /b 1
    )
)

:loop
echo [%date% %time%] Starting RailShot stream worker...
"%PHP%" "%~dp0stream-worker.php"
echo [%date% %time%] Worker exited — restarting in 5 seconds...
timeout /t 5 /nobreak >nul
goto loop
