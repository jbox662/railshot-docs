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

copy /Y "%SRC%" "%DEST%"
echo Config copied to %DEST%

taskkill /IM mediamtx.exe /F >nul 2>&1
timeout /t 2 /nobreak >nul
start "" /D "%DEST_DIR%" "%DEST_DIR%\mediamtx.exe"
echo MediaMTX restarted with rtspTransport: tcp for table1.
echo Test: http://127.0.0.1:8888/table1/index.m3u8
pause
