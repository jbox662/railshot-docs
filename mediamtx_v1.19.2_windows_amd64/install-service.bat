@echo off
REM Run MediaMTX as a Windows service using NSSM (recommended for VPS).
REM 1. Download NSSM: https://nssm.cc/download
REM 2. Unzip and put nssm.exe in this folder OR on PATH
REM 3. Right-click this file → Run as administrator

cd /d "%~dp0"
set SERVICE_NAME=RailShotMediaMTX
set EXE=%~dp0mediamtx.exe
set APPDIR=%~dp0

where nssm >nul 2>&1
if errorlevel 1 (
  if exist "%~dp0nssm.exe" (
    set NSSM=%~dp0nssm.exe
  ) else (
    echo nssm.exe not found. Download from https://nssm.cc/download
    echo Put nssm.exe in this folder, then re-run as Administrator.
    pause
    exit /b 1
  )
) else (
  set NSSM=nssm
)

echo Installing %SERVICE_NAME% ...
"%NSSM%" stop %SERVICE_NAME% >nul 2>&1
"%NSSM%" remove %SERVICE_NAME% confirm >nul 2>&1
"%NSSM%" install %SERVICE_NAME% "%EXE%"
"%NSSM%" set %SERVICE_NAME% AppDirectory "%APPDIR%"
"%NSSM%" set %SERVICE_NAME% DisplayName "RailShot TV MediaMTX"
"%NSSM%" set %SERVICE_NAME% Description "RailShot TV live table streaming (MediaMTX)"
"%NSSM%" set %SERVICE_NAME% Start SERVICE_AUTO_START
"%NSSM%" set %SERVICE_NAME% AppStdout "%APPDIR%mediamtx-service.log"
"%NSSM%" set %SERVICE_NAME% AppStderr "%APPDIR%mediamtx-service.err.log"
"%NSSM%" set %SERVICE_NAME% AppRotateFiles 1
"%NSSM%" start %SERVICE_NAME%

echo.
echo Done. Check status with: sc query %SERVICE_NAME%
echo Open firewall ports 8888 and 8889 TCP (and WebRTC UDP if needed).
pause
