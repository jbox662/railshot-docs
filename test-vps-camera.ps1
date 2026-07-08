# Run on the VPS — finds mediamtx.yml automatically.
$ErrorActionPreference = "Continue"
$cameraHost = "140.106.76.67"
$cameraPort = 8554
$rtspUrl = "rtsp://admin:decoder1@${cameraHost}:${cameraPort}/h264Preview_01_sub"

$candidates = @(
    "C:\mediamtx\mediamtx.yml",
    "$env:USERPROFILE\Documents\mediamtx_v1.19.2_windows_amd64\mediamtx.yml",
    "$PSScriptRoot\mediamtx_v1.19.2_windows_amd64\mediamtx.yml",
    "$PSScriptRoot\mediamtx.yml"
)
$configPath = $candidates | Where-Object { Test-Path $_ } | Select-Object -First 1

Write-Host "`n=== 1. MediaMTX config ===" -ForegroundColor Cyan
if ($configPath) {
    Write-Host "Found: $configPath"
    Get-Content $configPath | Select-String -Pattern "table1|runOnDemand|source:|rtspTransport" -Context 0,1
} else {
    Write-Host "mediamtx.yml not found. Checked:" -ForegroundColor Red
    $candidates | ForEach-Object { Write-Host "  $_" }
}

Write-Host "`n=== 2. TCP to camera ===" -ForegroundColor Cyan
Test-NetConnection -ComputerName $cameraHost -Port $cameraPort -WarningAction SilentlyContinue |
    Select-Object ComputerName, RemotePort, TcpTestSucceeded

Write-Host "`n=== 3. ffmpeg ===" -ForegroundColor Cyan
$ffmpeg = Get-Command ffmpeg -ErrorAction SilentlyContinue
if ($ffmpeg) {
    Write-Host "OK: $($ffmpeg.Source)"
    & ffmpeg -hide_banner -loglevel error -rtsp_transport tcp -i $rtspUrl -t 5 -f null - 2>&1
    if ($LASTEXITCODE -eq 0) { Write-Host "ffmpeg RTSP probe: OK" -ForegroundColor Green }
    else { Write-Host "ffmpeg RTSP probe: FAILED (exit $LASTEXITCODE)" -ForegroundColor Red }
} else {
    Write-Host "NOT installed — run: winget install ffmpeg" -ForegroundColor Red
}

Write-Host "`n=== 4. MediaMTX process ===" -ForegroundColor Cyan
Get-Process mediamtx -ErrorAction SilentlyContinue | Select-Object Id, Path

Write-Host "`n=== 5. MediaMTX HLS (table1) ===" -ForegroundColor Cyan
try {
    $r = Invoke-WebRequest "http://127.0.0.1:8888/table1/index.m3u8" -UseBasicParsing -TimeoutSec 90
    Write-Host "HTTP $($r.StatusCode) —" $r.Content.Split("`n")[0] -ForegroundColor Green
} catch {
    Write-Host "FAILED:" $_.Exception.Message -ForegroundColor Red
    if ($_.ErrorDetails.Message) { Write-Host $_.ErrorDetails.Message }
}

Write-Host "`nDone.`n"
