@echo off
echo ========================================
echo  Auto-Sync Database Script
echo ========================================
echo.
echo Starting auto-sync to IONOS cloud...
echo Press Ctrl+C to stop
echo.

cd /d "%~dp0"
.venv\Scripts\python.exe auto_sync.py

pause
