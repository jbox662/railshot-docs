@echo off
REM Run on the VPS (RDP) after pulling from GitHub.
REM Copies the MediaMTX config into C:\mediamtx\ and restarts the service.
setlocal

set SRC=%~dp0mediamtx\VPS-mediamtx.yml
set DEST_DIR=C:\mediamtx
set DEST=%DEST_DIR%\mediamtx.yml

if not exist "%SRC%" (
  echo ERROR: VPS-mediamtx.yml not found next to this script.
  pause
  exit /b 1
)

if not exist "%DEST_DIR%\mediamtx.exe" (
  echo ERROR: mediamtx.exe not found in %DEST_DIR%
  echo Download MediaMTX Windows release and place mediamtx.exe there first.
  pause
  exit /b 1
)

copy /Y "%SRC%" "%DEST%"
echo Config copied to %DEST%

taskkill /IM mediamtx.exe /F >nul 2>&1
timeout /t 2 /nobreak >nul
start "" /D "%DEST_DIR%" "%DEST_DIR%\mediamtx.exe"
echo MediaMTX restarted.
echo Test: http://127.0.0.1:8888/table1/index.m3u8
pause
