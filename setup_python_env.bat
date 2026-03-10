@echo off
echo ===================================================
echo Setting up Python Virtual Environment...
echo This may take a few minutes as it downloads AI Models
echo and dependencies. Please do not close this window!
echo ===================================================

cd /d "%~dp0"

:: 1. Check if python is accessible via global path, otherwise fallback to the hardcoded C:\Program Files
set PYTHON_CMD=python
"C:\Program Files\Python310\python.exe" --version >nul 2>&1
if %errorlevel% equ 0 (
    set PYTHON_CMD="C:\Program Files\Python310\python.exe"
)

:: 2. Create the virtual environment
echo [1/3] Creating virtual environment (.venv)...
%PYTHON_CMD% -m venv .venv

:: 3. Activate and update pip
echo [2/3] Updating pip...
call .venv\Scripts\activate.bat
python -m pip install --upgrade pip

:: 4. Install requirements. Using standard pip avoids all the IIS "Access Denied" hardlink issues caused by uv!
echo [3/3] Installing Face ID dependencies (OpenCV, InsightFace, etc.)...
pip install -r requirements.txt

echo ===================================================
echo Python Environment Setup Complete!
echo ===================================================
exit /b 0
