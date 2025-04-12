@echo off
REM SocialApp Server Launcher
REM This batch file provides a simple way to start the server directly

cd %~dp0

REM Check if PHP is available
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo PHP not found in PATH. Please make sure PHP is installed and added to your PATH.
    goto :end
)

REM Check if the router.php file exists
if not exist router.php (
    echo Error: router.php not found.
    echo Current directory: %CD%
    goto :end
)

REM Create logs directory if it doesn't exist
if not exist logs mkdir logs

echo Starting SocialApp server on http://localhost:8000
echo Press Ctrl+C to stop the server

REM Run the PHP server with the router
php -S localhost:8000 router.php

:end
pause
