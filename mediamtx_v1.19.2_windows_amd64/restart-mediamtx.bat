@echo off
REM Run from this folder on the VPS (double-click or cmd).
cd /d "%~dp0"

where ffmpeg >nul 2>&1
if errorlevel 1 (
  echo ffmpeg not found. Install: winget install ffmpeg
  echo Then open a NEW window and run this again.
  pause
  exit /b 1
)

echo Using: %CD%
echo Config: %CD%\mediamtx.yml

taskkill /IM mediamtx.exe /F >nul 2>&1
timeout /t 2 /nobreak >nul
start "" /D "%CD%" "%CD%\mediamtx.exe"
echo MediaMTX started from %CD%
echo Test: Invoke-WebRequest http://127.0.0.1:8888/table1/index.m3u8 -UseBasicParsing
pause
