@echo off
REM Runs as SYSTEM via RailShot-KillFFmpeg scheduled task (see install-kill-task.bat)
taskkill /F /T /IM ffmpeg.exe >nul 2>&1
for /f "tokens=2" %%p in ('tasklist /FI "IMAGENAME eq ffmpeg.exe" /NH 2^>nul') do (
    echo %%p | findstr /r "^[0-9][0-9]*$" >nul && taskkill /F /T /PID %%p >nul 2>&1
)
exit /b 0
