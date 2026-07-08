@echo off
REM Run on the VPS (RDP) after pulling from GitHub.
REM Copies the FULL MediaMTX config into C:\mediamtx\ and restarts.
setlocal

set REPO=%~dp0
set SRC=%REPO%mediamtx_v1.19.2_windows_amd64\mediamtx.yml
set DEST_DIR=C:\mediamtx
set DEST=%DEST_DIR%\mediamtx.yml

if not exist "%SRC%" (
  echo ERROR: Full mediamtx.yml not found at:
  echo %SRC%
  pause
  exit /b 1
)

if not exist "%DEST_DIR%\mediamtx.exe" (
  echo ERROR: mediamtx.exe not found in %DEST_DIR%
  echo Download MediaMTX Windows release and place mediamtx.exe there first.
  pause
  exit /b 1
)

where ffmpeg >nul 2>&1
if errorlevel 1 (
  echo WARNING: ffmpeg is not in PATH. table1 uses ffmpeg to pull the camera.
  echo Install with: winget install ffmpeg
  echo Then open a NEW command window and run this script again.
  pause
)

copy /Y "%SRC%" "%DEST%"
echo Config copied to %DEST%

taskkill /IM mediamtx.exe /F >nul 2>&1
timeout /t 2 /nobreak >nul
start "" /D "%DEST_DIR%" "%DEST_DIR%\mediamtx.exe"
echo MediaMTX restarted. table1 uses ffmpeg runOnDemand + TCP.
echo Test: powershell -File "%REPO%test-vps-camera.ps1"
echo   or: Invoke-WebRequest http://127.0.0.1:8888/table1/index.m3u8 -UseBasicParsing
pause
