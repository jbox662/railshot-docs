@echo off
cd /d "%~dp0"
REM MediaMTX runOnDemand — pulls camera via ffmpeg, publishes to local RTSP.
REM Env vars from MediaMTX: RTSP_PORT, MTX_PATH

where ffmpeg >nul 2>&1
if errorlevel 1 (
  echo ffmpeg not in PATH >&2
  exit /b 1
)

ffmpeg -hide_banner -loglevel warning -rtsp_transport tcp -i "rtsp://admin:decoder1@140.106.76.67:8554/h264Preview_01_main" -c copy -f rtsp -rtsp_transport tcp rtsp://127.0.0.1:%RTSP_PORT%/%MTX_PATH%
