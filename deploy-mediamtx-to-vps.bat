@echo off
REM Run on the VPS after updating site files from GitHub.
REM Finds mediamtx.exe in C:\mediamtx OR Documents\mediamtx_v1.19.2_windows_amd64
setlocal

set REPO=%~dp0
set SRC=%REPO%mediamtx_v1.19.2_windows_amd64\mediamtx.yml
set DEST_DIR=

if exist "C:\mediamtx\mediamtx.exe" set DEST_DIR=C:\mediamtx
if "%DEST_DIR%"=="" if exist "%USERPROFILE%\Documents\mediamtx_v1.19.2_windows_amd64\mediamtx.exe" (
  set "DEST_DIR=%USERPROFILE%\Documents\mediamtx_v1.19.2_windows_amd64"
)
if "%DEST_DIR%"=="" if exist "%REPO%mediamtx_v1.19.2_windows_amd64\mediamtx.exe" (
  set "DEST_DIR=%REPO%mediamtx_v1.19.2_windows_amd64"
)

if "%DEST_DIR%"=="" (
  echo ERROR: mediamtx.exe not found. Expected one of:
  echo   C:\mediamtx\mediamtx.exe
  echo   %USERPROFILE%\Documents\mediamtx_v1.19.2_windows_amd64\mediamtx.exe
  pause
  exit /b 1
)

if not exist "%SRC%" (
  echo ERROR: Source config not found:
  echo %SRC%
  echo Copy mediamtx.yml from GitHub into mediamtx_v1.19.2_windows_amd64\
  pause
  exit /b 1
)

where ffmpeg >nul 2>&1
if errorlevel 1 (
  echo WARNING: ffmpeg not in PATH. table1 needs it: winget install ffmpeg
  pause
)

copy /Y "%SRC%" "%DEST_DIR%\mediamtx.yml"
echo Config copied to %DEST_DIR%\mediamtx.yml

if exist "%REPO%test-vps-camera.ps1" copy /Y "%REPO%test-vps-camera.ps1" "%DEST_DIR%\test-vps-camera.ps1"

taskkill /IM mediamtx.exe /F >nul 2>&1
timeout /t 2 /nobreak >nul
start "" /D "%DEST_DIR%" "%DEST_DIR%\mediamtx.exe"
echo MediaMTX restarted from %DEST_DIR%
echo Test: powershell -File "%DEST_DIR%\test-vps-camera.ps1"
pause
