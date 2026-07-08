# Run on the VPS in PowerShell — updates table1 in mediamtx.yml next to this script.
$ErrorActionPreference = "Stop"
$dir = $PSScriptRoot
if (-not $dir) { $dir = "C:\Users\funbucket\Documents\mediamtx_v1.19.2_windows_amd64" }
$config = Join-Path $dir "mediamtx.yml"

if (-not (Test-Path $config)) {
    Write-Host "mediamtx.yml not found at $config" -ForegroundColor Red
    exit 1
}

Copy-Item $config "$config.bak-$(Get-Date -Format yyyyMMdd-HHmmss)"

$content = Get-Content $config -Raw
$newTable1 = @"
  table1:
    runOnDemand: ffmpeg -hide_banner -loglevel warning -rtsp_transport tcp -stimeout 30000000 -i rtsp://admin:decoder1@140.106.76.67:8554/h264Preview_01_sub -c copy -f rtsp -rtsp_transport tcp rtsp://127.0.0.1:`$RTSP_PORT/`$MTX_PATH
    runOnDemandRestart: yes
    runOnDemandStartTimeout: 60s
    runOnDemandCloseAfter: 30s
"@

if ($content -match '(?ms)^  table1:.*?(?=^\S|\z)') {
    $content = $content -replace '(?ms)^  table1:.*?(?=^  \S|\z)', ($newTable1.TrimEnd() + "`r`n`r`n")
} else {
    Write-Host "Could not find table1: block in mediamtx.yml" -ForegroundColor Red
    exit 1
}

Set-Content -Path $config -Value $content.TrimEnd() -Encoding UTF8 -NoNewline
Add-Content -Path $config -Value "`r`n"

Write-Host "Updated table1 in $config" -ForegroundColor Green
Write-Host "Backup saved alongside mediamtx.yml"
Get-Content $config | Select-String "table1|runOnDemand" -Context 0,1

Write-Host "`nRestarting MediaMTX..."
Get-Process mediamtx -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Sleep -Seconds 2
Start-Process -FilePath (Join-Path $dir "mediamtx.exe") -WorkingDirectory $dir
Write-Host "Done. Test: Invoke-WebRequest http://127.0.0.1:8888/table1/index.m3u8 -UseBasicParsing -TimeoutSec 90"
