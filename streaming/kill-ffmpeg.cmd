@echo off
REM Runs as SYSTEM via RailShot-KillFFmpeg scheduled task (see install-kill-task.bat)
setlocal EnableDelayedExpansion
set "FLAG=%~dp0kill-done.flag"
del /f /q "%FLAG%" 2>nul

set /a TRIES=0
:loop
taskkill /F /T /IM ffmpeg.exe >nul 2>&1
for /f "tokens=2" %%p in ('tasklist /FI "IMAGENAME eq ffmpeg.exe" /NH 2^>nul ^| findstr /i ffmpeg.exe') do (
    taskkill /F /T /PID %%p >nul 2>&1
)
tasklist /FI "IMAGENAME eq ffmpeg.exe" /NH 2>nul | findstr /i ffmpeg.exe >nul
if errorlevel 1 goto done
set /a TRIES+=1
if !TRIES! GEQ 25 goto done
timeout /t 1 /nobreak >nul
goto loop

:done
echo ok> "%FLAG%"
exit /b 0
