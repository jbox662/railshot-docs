@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"
set "LOG=%~dp0stream-worker-loop.log"
set "PHP="

call :log Loop starting in %CD%

REM Plesk PHP (SYSTEM account often has no php in PATH — check known locations first)
for %%P in (
    "C:\Program Files (x86)\Plesk\Additional\Plesk PHP\8.4\php.exe"
    "C:\Program Files (x86)\Plesk\Additional\Plesk PHP\8.3\php.exe"
    "C:\Program Files (x86)\Plesk\Additional\Plesk PHP\8.2\php.exe"
    "C:\Program Files (x86)\Plesk\Additional\Plesk PHP\8.1\php.exe"
    "C:\Program Files\Plesk\Additional\Plesk PHP\8.4\php.exe"
    "C:\Program Files\Plesk\Additional\Plesk PHP\8.3\php.exe"
) do (
    if exist %%P (
        set "PHP=%%~P"
        goto :found_php
    )
)

if exist "C:\Program Files (x86)\Plesk\Additional\Plesk PHP\" (
    for /f "delims=" %%D in ('dir /b /ad "C:\Program Files (x86)\Plesk\Additional\Plesk PHP" 2^>nul') do (
        if exist "C:\Program Files (x86)\Plesk\Additional\Plesk PHP\%%D\php.exe" (
            set "PHP=C:\Program Files (x86)\Plesk\Additional\Plesk PHP\%%D\php.exe"
            goto :found_php
        )
    )
)

where php >nul 2>&1
if not errorlevel 1 set "PHP=php"

:found_php
if not defined PHP (
    call :log ERROR: php.exe not found for SYSTEM task. Install Plesk PHP or edit stream-worker-loop.cmd.
    exit /b 1
)

call :log Using PHP: %PHP%

:loop
call :log Starting stream-worker.php...
"%PHP%" "%~dp0stream-worker.php" >> "%LOG%" 2>&1
set "EXITCODE=!ERRORLEVEL!"
call :log stream-worker.php exited with code !EXITCODE! — restarting in 5 seconds...
timeout /t 5 /nobreak >nul
goto loop

:log
echo [%date% %time%] %~1
>>"%LOG%" echo [%date% %time%] %~1
exit /b 0
