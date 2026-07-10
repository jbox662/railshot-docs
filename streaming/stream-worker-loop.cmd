@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"
set "LOG=%~dp0stream-worker-loop.log"
set "PHPCFG=%~dp0stream-worker-php.txt"
set "PHP="

>>"%LOG%" echo [%date% %time%] Loop starting in %CD%

REM 1) Path saved by streaming/php-path-setup.php or find-php.bat
if exist "%PHPCFG%" (
    set /p PHP=<"%PHPCFG%"
    set "PHP=!PHP:"=!"
    if defined PHP if exist "!PHP!" (
        >>"%LOG%" echo [%date% %time%] Using PHP from stream-worker-php.txt: !PHP!
        goto :run_loop
    )
    >>"%LOG%" echo [%date% %time%] stream-worker-php.txt exists but path not found: !PHP!
    set "PHP="
)

REM 2) Known Plesk locations
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

if exist "C:\Program Files\Plesk\Additional\Plesk PHP\" (
    for /f "delims=" %%D in ('dir /b /ad "C:\Program Files\Plesk\Additional\Plesk PHP" 2^>nul') do (
        if exist "C:\Program Files\Plesk\Additional\Plesk PHP\%%D\php.exe" (
            set "PHP=C:\Program Files\Plesk\Additional\Plesk PHP\%%D\php.exe"
            goto :found_php
        )
    )
)

where php >nul 2>&1
if not errorlevel 1 set "PHP=php"

:found_php
if not defined PHP (
    >>"%LOG%" echo [%date% %time%] ERROR: php.exe not found. Open https://railshottv.com/streaming/php-path-setup.php while logged into admin, then re-run install-stream-worker.bat.
    exit /b 1
)
>>"%LOG%" echo [%date% %time%] Using PHP: %PHP%

:run_loop
:loop
>>"%LOG%" echo [%date% %time%] Starting stream-worker.php...
"%PHP%" "%~dp0stream-worker.php" >> "%LOG%" 2>&1
set "EXITCODE=!ERRORLEVEL!"
>>"%LOG%" echo [%date% %time%] stream-worker.php exited with code !EXITCODE! — restarting in 5 seconds...
timeout /t 5 /nobreak >nul
goto loop
