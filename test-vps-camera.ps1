# Run on the VPS in PowerShell to diagnose table1 / camera reachability.
$ErrorActionPreference = "Continue"
$cameraHost = "140.106.76.67"
$cameraPort = 8554
$rtspUrl = "rtsp://admin:decoder1@${cameraHost}:${cameraPort}/h264Preview_01_sub"
$configPath = "C:\mediamtx\mediamtx.yml"

Write-Host "`n=== 1. MediaMTX config (table1 block) ===" -ForegroundColor Cyan
if (Test-Path $configPath) {
    Get-Content $configPath | Select-String -Pattern "table1|runOnDemand|source:|rtspTransport" -Context 0,1
} else {
    Write-Host "MISSING: $configPath" -ForegroundColor Red
}

Write-Host "`n=== 2. TCP to camera port forward ===" -ForegroundColor Cyan
Test-NetConnection -ComputerName $cameraHost -Port $cameraPort -WarningAction SilentlyContinue |
    Select-Object ComputerName, RemotePort, TcpTestSucceeded

Write-Host "`n=== 3. ffmpeg installed? ===" -ForegroundColor Cyan
$ffmpeg = Get-Command ffmpeg -ErrorAction SilentlyContinue
if ($ffmpeg) {
    Write-Host "OK: $($ffmpeg.Source)"
    Write-Host "Probing RTSP for 5 seconds..."
    & ffmpeg -hide_banner -loglevel error -rtsp_transport tcp -i $rtspUrl -t 5 -f null - 2>&1
    if ($LASTEXITCODE -eq 0) { Write-Host "ffmpeg RTSP probe: OK" -ForegroundColor Green }
    else { Write-Host "ffmpeg RTSP probe: FAILED (exit $LASTEXITCODE)" -ForegroundColor Red }
} else {
    Write-Host "ffmpeg NOT in PATH. Install: winget install ffmpeg" -ForegroundColor Red
    Write-Host "Then close/reopen PowerShell and re-run this script."
}

Write-Host "`n=== 4. MediaMTX HLS (table1) ===" -ForegroundColor Cyan
try {
    $r = Invoke-WebRequest "http://127.0.0.1:8888/table1/index.m3u8" -UseBasicParsing -TimeoutSec 90
    Write-Host "HTTP $($r.StatusCode) — first line:" $r.Content.Split("`n")[0] -ForegroundColor Green
} catch {
    Write-Host "FAILED:" $_.Exception.Message -ForegroundColor Red
    if ($_.ErrorDetails.Message) { Write-Host $_.ErrorDetails.Message }
}

Write-Host "`nDone.`n"
