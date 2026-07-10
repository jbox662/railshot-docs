@echo off
REM Keeps stream-worker.ps1 running (restarts after crash). No PHP required.
cd /d "%~dp0"
set "LOG=%~dp0stream-worker-loop.log"

>>"%LOG%" echo [%date% %time%] Starting PowerShell stream worker...

:loop
>>"%LOG%" echo [%date% %time%] Launching stream-worker.ps1...
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0stream-worker.ps1" >> "%LOG%" 2>&1
>>"%LOG%" echo [%date% %time%] Worker exited — restarting in 5 seconds...
timeout /t 5 /nobreak >nul
goto loop
