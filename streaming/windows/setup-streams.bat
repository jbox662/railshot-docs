@echo off
:: ═══════════════════════════════════════════════════════════════════════════
:: RailShot TV — Stream Setup (Windows)
:: Run this as Administrator to install all camera streams as scheduled tasks.
:: They will auto-start on boot and auto-restart if FFmpeg crashes.
:: ═══════════════════════════════════════════════════════════════════════════

net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: Please right-click this file and choose "Run as Administrator"
    pause
    exit /b 1
)

set "FFMPEG=C:\Users\funbucket\AppData\Local\Microsoft\WinGet\Packages\Gyan.FFmpeg_Microsoft.Winget.Source_8wekyb3d8bbwe\ffmpeg-8.1.2-full_build\bin\ffmpeg.exe"
set "CONF=%~dp0cameras.conf"
set "SCRIPTS_DIR=C:\RailShotStreams"

if not exist "%FFMPEG%" (
    echo ERROR: FFmpeg not found at:
    echo   %FFMPEG%
    echo Please update the FFMPEG path in this script.
    pause
    exit /b 1
)

if not exist "%CONF%" (
    echo ERROR: cameras.conf not found at %CONF%
    pause
    exit /b 1
)

:: Create scripts directory
if not exist "%SCRIPTS_DIR%" mkdir "%SCRIPTS_DIR%"

set INSTALLED=0

for /f "usebackq tokens=1,2,3 delims=|" %%A in ("%CONF%") do (
    :: Skip comment lines
    set "LINE=%%A"
    if not "!LINE:~0,1!"=="#" (
        set "TABLE=%%A"
        set "RTSP=%%B"
        set "YTKEY=%%C"

        :: Trim whitespace
        for /f "tokens=* delims= " %%X in ("!TABLE!") do set "TABLE=%%X"
        for /f "tokens=* delims= " %%X in ("!RTSP!") do set "RTSP=%%X"
        for /f "tokens=* delims= " %%X in ("!YTKEY!") do set "YTKEY=%%X"

        if not "!TABLE!"=="" if not "!RTSP!"=="" if not "!YTKEY!"=="" (
            echo.
            echo Setting up: !TABLE!

            :: Write the per-table loop script
            set "SCRIPT=%SCRIPTS_DIR%\stream-!TABLE!.bat"
            (
                echo @echo off
                echo :loop
                echo echo [%%date%% %%time%%] Starting stream for !TABLE!...
                echo "!FFMPEG!" -loglevel warning -rtsp_transport tcp -stimeout 10000000 -i "!RTSP!" -c:v copy -c:a aac -b:a 128k -ar 44100 -f flv -flvflags no_duration_filesize "rtmp://a.rtmp.youtube.com/live2/!YTKEY!"
                echo echo [%%date%% %%time%%] Stream stopped. Restarting in 10 seconds...
                echo timeout /t 10 /nobreak ^>nul
                echo goto loop
            ) > "!SCRIPT!"

            :: Remove existing task if present
            schtasks /delete /tn "RailShot-!TABLE!" /f >nul 2>&1

            :: Create scheduled task: runs at system startup, hidden, as SYSTEM
            schtasks /create /tn "RailShot-!TABLE!" /tr "cmd /c \"!SCRIPT!\"" /sc onstart /ru SYSTEM /rl HIGHEST /f >nul

            :: Start it now without waiting for reboot
            schtasks /run /tn "RailShot-!TABLE!" >nul

            echo   Script : !SCRIPT!
            echo   Task   : RailShot-!TABLE! (starts on boot)
            echo   Status : Started

            set /a INSTALLED+=1
        )
    )
)

echo.
echo ═══════════════════════════════════════════════════════
echo   Done! %INSTALLED% stream(s) configured.
echo ═══════════════════════════════════════════════════════
echo.
echo Useful commands:
echo   Check status : status-streams.bat
echo   Stop all     : stop-streams.bat
echo   Restart one  : schtasks /run /tn "RailShot-table1"
echo.
pause
