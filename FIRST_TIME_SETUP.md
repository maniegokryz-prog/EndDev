# 🚀 First Time Setup Guide

## Prerequisites

- Windows 10/11
- Python 3.10 or higher installed
- UV package manager installed (`pip install uv`)
- Git installed
- MySQL/MariaDB running

---

## Setup Steps

### 1. Clone the Repository

```powershell
cd C:\inetpub\wwwroot
git clone https://github.com/maniegokryz-prog/EndDev.git
cd EndDev
```

### 2. Install Dependencies (Creates Virtual Environment)

```powershell
# This single command creates .venv and installs all dependencies
uv sync

# Verify venv was created
dir .venv
```

**Note:** `uv sync` automatically creates the virtual environment if it doesn't exist, then installs all packages from `pyproject.toml`.

### 3. Verify Installation

```powershell
# Check virtual environment is working
python check_venv.py

# Check OpenCV has GUI support
python faceid\test_opencv_gui.py
```

**Expected output:**
```
✅ ALL CHECKS PASSED!
✅ GUI support is working!
```

### 4. Configure Database

Edit your database connection settings in:
- `db_connection.php`
- Update credentials as needed

### 5. Test the Kiosk System

```powershell
# Start the kiosk (will request admin privileges if needed)
python faceid\start_kiosk.py
```

---

## Common Issues During Setup

### ❌ Issue: "opencv-python-headless" is installed

**Fix:**
```powershell
# Remove headless version
uv pip uninstall opencv-python-headless -y

# Reinstall from pyproject.toml (which excludes headless)
uv sync
```

See: `SETUP_FIX.md` Issue 1

---

### ❌ Issue: "No Python at 'C:\Users\...\Python310\python.exe'"

**Fix:**
```powershell
# Recreate the virtual environment
Remove-Item -Recurse -Force .venv
uv venv
uv sync
```

See: `SETUP_FIX.md` Issue 2 or `FIX_VENV_PYTHON.md`

---

### ❌ Issue: Face embedding generation fails

**Fix:**
```powershell
# Check venv configuration
python check_venv.py

# If broken, recreate
Remove-Item -Recurse -Force .venv
uv venv
uv sync
```

---

### ❌ Issue: Kiosk won't start - GUI error

**Fix:**
```powershell
# Test OpenCV GUI support
python faceid\test_opencv_gui.py

# If it fails, you have opencv-python-headless
# Follow the fix in SETUP_FIX.md Issue 1
```

---

## File Structure

```
EndDev/
├── faceid/                    # Kiosk system
│   ├── start_kiosk.py        # Main launcher
│   ├── Kiosk_faceid.py       # Face recognition
│   ├── test_opencv_gui.py    # Test OpenCV
│   └── check_opencv.py       # Diagnostic tool
├── staffmanagement/           # Employee management
│   └── generate_face_embeddings.py
├── dashboard/                 # Web dashboard
├── login/                     # Authentication
├── check_venv.py             # Venv diagnostic
├── pyproject.toml            # Dependencies
├── SETUP_FIX.md              # Troubleshooting
└── FIX_VENV_PYTHON.md        # Venv issues
```

---

## Diagnostic Tools

### Check Virtual Environment
```powershell
python check_venv.py
```
Verifies:
- ✅ Venv exists and is configured correctly
- ✅ Python can execute
- ✅ Base Python installation is accessible
- ✅ Required packages (OpenCV) can import

### Check OpenCV Installation
```powershell
python faceid\test_opencv_gui.py
```
Verifies:
- ✅ OpenCV can import
- ✅ Correct package (not headless) is installed
- ✅ GUI support is working

### List Installed Packages
```powershell
uv pip list
```

### Check Specific Package
```powershell
uv pip list | Select-String opencv
uv pip list | Select-String insightface
```

---

## Running the System

### Kiosk Mode (Face Recognition)
```powershell
python faceid\start_kiosk.py
```

### Web Interface
1. Start Apache/IIS
2. Navigate to `http://localhost/EndDev/`
3. Login with credentials

### Add Employee
1. Go to Staff Management
2. Click "Add Employee"
3. Upload face photos (up to 20 different angles)
4. System automatically generates embeddings

---

## Development Workflow

### After Pulling New Code
```powershell
# Pull latest changes
git pull origin main

# Update dependencies if pyproject.toml changed
uv sync

# Verify everything still works
python check_venv.py
```

### Adding New Python Dependencies
```powershell
# Edit pyproject.toml and add the package
# Then run:
uv sync
```

### If Things Break After Update
```powershell
# Nuclear option - recreate everything
Remove-Item -Recurse -Force .venv
Remove-Item uv.lock -Force
uv venv
uv sync
python check_venv.py
```

---

## Support

If you encounter issues:

1. **Run diagnostics:**
   - `python check_venv.py`
   - `python faceid/test_opencv_gui.py`

2. **Check documentation:**
   - `SETUP_FIX.md` - Common installation issues
   - `FIX_VENV_PYTHON.md` - Virtual environment problems

3. **Fresh install:**
   - Delete `.venv` folder
   - Run `uv venv` and `uv sync`
   - Re-run diagnostics

---

## Success Checklist

Before running the system, verify:

- ✅ `python check_venv.py` passes all checks
- ✅ `python faceid/test_opencv_gui.py` shows GUI support working
- ✅ `uv pip list | Select-String opencv` shows ONLY `opencv-python` (NOT headless)
- ✅ Database connection configured in `db_connection.php`
- ✅ MySQL/MariaDB is running

If all checks pass, you're ready to use the system! 🎉
