@echo off
REM Batch file to start the Kiosk system with Administrator privileges
REM This is needed to access files in C:\inetpub\wwwroot

echo ========================================
echo Kiosk Face ID System - Admin Launcher
echo ========================================
echo.
echo This script will request Administrator privileges
echo to access files in C:\inetpub\wwwroot
echo.
pause

REM Get the directory where this batch file is located
cd /d "%~dp0"

REM Run Python script with the virtual environment Python
echo Starting Kiosk system...
echo.
..\. venv\Scripts\python.exe start_kiosk.py

pause
