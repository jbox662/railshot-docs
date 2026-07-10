@echo off
REM Search the VPS for php.exe and save the first match to stream-worker-php.txt
cd /d "%~dp0"
set "OUT=%~dp0stream-worker-php.txt"

echo Searching for php.exe on this server...
echo.

powershell -NoProfile -Command ^
  "$roots = @('C:\Program Files (x86)\Plesk', 'C:\Program Files\Plesk', 'C:\php', 'C:\tools');" ^
  "$found = @();" ^
  "foreach ($root in $roots) { if (Test-Path $root) { $found += Get-ChildItem -Path $root -Filter php.exe -Recurse -ErrorAction SilentlyContinue | Select-Object -ExpandProperty FullName } };" ^
  "if ($found.Count -eq 0) { $found += Get-ChildItem -Path 'C:\Program Files (x86)' -Filter php.exe -Recurse -Depth 5 -ErrorAction SilentlyContinue | Select-Object -ExpandProperty FullName };" ^
  "$found | Select-Object -First 10 | ForEach-Object { Write-Host $_ };" ^
  "if ($found.Count -gt 0) { Set-Content -Path '%OUT%' -Value $found[0] -NoNewline; Add-Content -Path '%OUT%' -Value '' }"

if exist "%OUT%" (
    echo.
    echo Saved to stream-worker-php.txt:
    type "%OUT%"
    echo.
    echo Now re-run install-stream-worker.bat as Administrator.
) else (
    echo.
    echo No php.exe found. In Plesk: Tools ^& Settings -^> PHP Settings - note the path, then paste it into stream-worker-php.txt
)

pause
