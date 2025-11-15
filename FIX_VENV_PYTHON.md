# Fix UV Virtual Environment Python Reference Issue

## Problem
When adding employees, face embedding generation fails with:
```
No Python at '"C:\Users\kryztian ben\AppData\Local\Programs\Python\Python310\python.exe'
```

Notice the **extra quotes** around the path! This error has TWO possible causes:

### Cause 1: Incorrect Command Quoting in PHP (FIXED)
The PHP script (`add_employee.php`) was incorrectly constructing the command with nested quotes:
```php
// OLD (BROKEN):
$quotedPythonExe = '"' . $pythonExe . '"';
$command = 'cmd /c "' . $quotedPythonExe . ' ...'
// Results in: cmd /c ""C:\path\python.exe" ..." (DOUBLE QUOTES!)
```

**This has been FIXED in the latest version.**

### Cause 2: Broken Virtual Environment Reference
The virtual environment's `pyvenv.cfg` references a Python installation that doesn't exist on your machine.

## Solution

### ✅ First: Pull Latest Code (PHP Fix Applied)

```powershell
# Get the latest version with the PHP command quoting fix
git pull origin main
```

The PHP file has been fixed to properly handle path quoting.

### Then: Recreate Virtual Environment (if still having issues)

```powershell
# Navigate to project root
cd C:\inetpub\wwwroot\EndDev

# Delete the old venv
Remove-Item -Recurse -Force .venv

# Delete lock file (optional, ensures clean state)
Remove-Item uv.lock -Force -ErrorAction SilentlyContinue

# Run uv sync (automatically creates venv and installs dependencies)
uv sync

# Verify it works (should show Python version without errors)
.venv\Scripts\python.exe --version
# Expected output: Python 3.10.x or higher
```

### Option 2: Fix the venv config file

```powershell
# Edit .venv\pyvenv.cfg
notepad .venv\pyvenv.cfg
```

Find the line:
```
home = C:\Users\kryztian ben\AppData\Local\Programs\Python\Python310
```

Change it to the actual Python location on your friend's machine. To find it:
```powershell
# Find where Python is installed
where python
# or
python -c "import sys; print(sys.executable)"
```

Then update `pyvenv.cfg` with the correct path.

### Option 3: Use System Python Directly (TEMPORARY FIX)

If you need it working immediately, modify the PHP script to use system Python instead of venv:

```php
// In add_employee.php, find the Python executable detection
// Change from:
$python_executable = $venv_python;

// To:
$python_executable = 'python';  // Use system Python
```

**WARNING:** This only works if the system Python has all required packages installed globally.

## After Fixing

Test the embedding generation:
```powershell
# First, verify Python works
.venv\Scripts\python.exe --version
# Should show: Python 3.10.x (or your version)

# Then test the script (will show usage help since no real data)
.venv\Scripts\python.exe staffmanagement\generate_face_embeddings.py

# Should NOT show: "No Python at..."
# Should show: "Usage: python generate_face_embeddings.py ..."
```

## Root Cause

UV virtual environments store a reference to the base Python interpreter in `.venv\pyvenv.cfg`. When that Python is moved/deleted, the venv breaks.

**What happened:**
1. Your friend ran `uv sync` which created `.venv` with a reference to their Python installation
2. The Python at that location was later deleted, moved, or is in a different location
3. When the venv tries to use that Python, it fails with "No Python at..."

The cleanest solution is **Option 1** - delete `.venv` and run `uv sync` again to recreate it.
