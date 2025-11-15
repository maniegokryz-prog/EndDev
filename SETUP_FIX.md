# 🔧 Setup and Installation Fixes

## Issue 1: OpenCV Headless Problem

### Problem
Both `opencv-python` and `opencv-python-headless` are installed, causing the headless version to be used (which doesn't support GUI).

## Solution Steps

### Step 1: Uninstall ALL OpenCV packages
```powershell
uv pip uninstall opencv-python opencv-python-headless opencv-contrib-python opencv-contrib-python-headless -y
```

### Step 2: Clear UV cache (important!)
```powershell
uv cache clean opencv-python
```

### Step 3: Install ONLY opencv-python
```powershell
uv pip install opencv-python==4.12.0.88 --no-deps
```

### Step 4: Run uv sync to install other dependencies
```powershell
uv sync
```

### Step 5: Verify installation
```powershell
uv pip list | Select-String opencv
```

**Expected output (ONLY this, nothing else):**
```
opencv-python          4.12.0.88
```

### Step 6: Test OpenCV GUI support
```powershell
python -c "import cv2; print(f'OpenCV {cv2.__version__}'); cv2.namedWindow('test', cv2.WINDOW_NORMAL); cv2.destroyAllWindows(); print('GUI support: OK')"
```

**Expected output:**
```
OpenCV 4.12.0.88
GUI support: OK
```

### Step 7: Run the Kiosk
```powershell
python faceid/start_kiosk.py
```

---

## If Step 4 reinstalls opencv-python-headless

This happens because `insightface` has `opencv-python-headless` as a dependency.

**Solution:** The `pyproject.toml` has been updated to prevent this. But you need to:

1. **Pull the latest changes:**
   ```powershell
   git pull origin main
   ```

2. **Remove the lock file and start fresh:**
   ```powershell
   Remove-Item uv.lock -Force
   ```

3. **Repeat Steps 1-7 above**

---

## Alternative: Manual Override

If `uv sync` keeps installing headless, use this approach:

```powershell
# Install dependencies one by one
uv pip install cryptography>=46.0.3
uv pip install huggingface-hub>=1.0.1
uv pip install mysql-connector-python==9.5.0
uv pip install numpy>=2.2.6
uv pip install onnxruntime>=1.23.2
uv pip install pymysql>=1.1.2

# Install opencv-python BEFORE insightface
uv pip install opencv-python==4.12.0.88

# Install insightface WITHOUT dependencies, then install missing ones
uv pip install insightface --no-deps
uv pip install onnxruntime-gpu albumentations prettytable
```

---

## Why This Happens

1. The `insightface` package lists `opencv-python-headless` as a dependency
2. When you run `uv sync`, it installs both `opencv-python` AND `opencv-python-headless`
3. Python imports the headless version first (alphabetically or by install order)
4. The headless version doesn't have GUI support, causing the error

The fix ensures ONLY `opencv-python` (with GUI) is installed.

---

## Issue 2: Virtual Environment Python Reference Error

### Problem
When adding employees, face embedding generation fails with:
```
No Python at '"C:\Users\kryztian ben\AppData\Local\Programs\Python\Python310\python.exe'
```

This happens because the UV virtual environment references a Python installation that was deleted, moved, or doesn't exist on your machine.

### Solution: Recreate the Virtual Environment

```powershell
# Navigate to project root
cd C:\inetpub\wwwroot\EndDev

# Delete the broken venv
Remove-Item -Recurse -Force .venv

# Run uv sync (creates fresh venv and installs dependencies)
uv sync

# Verify it works
python check_venv.py
```

### Alternative: Check and Fix Manually

```powershell
# Check what's wrong
python check_venv.py

# The script will tell you exactly what to fix
```

### Test Face Embedding Generation

After fixing, test if it works:
```powershell
# Make sure you have the venv activated or use the full path
.venv\Scripts\python.exe staffmanagement\generate_face_embeddings.py TEST001 999 localhost root yourpassword yourdatabase
```

Should NOT show "No Python at..." error.

---

## Complete Fresh Setup (If All Else Fails)

If you're having multiple issues, start completely fresh:

```powershell
# 1. Pull latest code
git pull origin main

# 2. Delete old venv
Remove-Item -Recurse -Force .venv

# 3. Delete UV lock file
Remove-Item uv.lock -Force

# 4. Create new venv
uv venv

# 5. Install dependencies (this uses the updated pyproject.toml)
uv sync

# 6. Verify venv is working
python check_venv.py

# 7. Test OpenCV GUI
python faceid/test_opencv_gui.py

# 8. Run the kiosk
python faceid/start_kiosk.py
```

---

## Quick Diagnostic Commands

```powershell
# Check virtual environment
python check_venv.py

# Check OpenCV installation
python faceid/test_opencv_gui.py

# See what's installed
uv pip list | Select-String opencv

# Test face embedding script directly
.venv\Scripts\python.exe staffmanagement\generate_face_embeddings.py
```
