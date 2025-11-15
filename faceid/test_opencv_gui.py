"""
Quick OpenCV GUI Test

This script tests if OpenCV has GUI support.
Run this AFTER fixing the installation.

Usage:
    python test_opencv_gui.py
"""

import sys

print("="*70)
print("Testing OpenCV GUI Support")
print("="*70)

# Step 1: Check if cv2 can be imported
print("\n[1/3] Testing cv2 import...")
try:
    import cv2
    print(f"  ✅ OpenCV {cv2.__version__} imported successfully")
except ImportError as e:
    print(f"  ❌ Failed to import cv2: {e}")
    print("\n  Fix: Run 'uv pip install opencv-python==4.12.0.88'")
    sys.exit(1)

# Step 2: Check which OpenCV package is installed
print("\n[2/3] Checking installed OpenCV packages...")
import subprocess
try:
    result = subprocess.run(
        ["uv", "pip", "list"],
        capture_output=True,
        text=True,
        encoding='utf-8',
        errors='replace'
    )
    
    opencv_packages = [line for line in result.stdout.split('\n') if 'opencv' in line.lower()]
    
    if opencv_packages:
        for pkg in opencv_packages:
            if 'headless' in pkg.lower():
                print(f"  ❌ {pkg.strip()} (REMOVE THIS!)")
            else:
                print(f"  ✅ {pkg.strip()}")
    
    # Check for headless
    has_headless = any('headless' in pkg.lower() for pkg in opencv_packages)
    if has_headless:
        print("\n  ⚠️  WARNING: opencv-python-headless is installed!")
        print("     This will cause GUI errors. Uninstall it:")
        print("     uv pip uninstall opencv-python-headless -y")
except Exception as e:
    print(f"  ⚠️  Could not check packages: {e}")

# Step 3: Test GUI support
print("\n[3/3] Testing GUI window creation...")
try:
    test_window = "OpenCV GUI Test"
    cv2.namedWindow(test_window, cv2.WINDOW_NORMAL)
    cv2.destroyWindow(test_window)
    cv2.waitKey(1)
    print("  ✅ GUI support is working!")
    
    print("\n" + "="*70)
    print("✅ ALL TESTS PASSED!")
    print("="*70)
    print("\nYour OpenCV installation is correct.")
    print("You can now run: python faceid/start_kiosk.py")
    print("="*70 + "\n")
    sys.exit(0)
    
except Exception as e:
    print(f"  ❌ GUI test failed: {e}")
    print("\n" + "="*70)
    print("❌ OpenCV GUI Not Working")
    print("="*70)
    print("\nYour OpenCV installation does NOT have GUI support.")
    print("\nFix this by following the steps in SETUP_FIX.md:")
    print("  1. uv pip uninstall opencv-python opencv-python-headless -y")
    print("  2. uv cache clean opencv-python")
    print("  3. uv pip install opencv-python==4.12.0.88 --no-deps")
    print("  4. uv sync")
    print("="*70 + "\n")
    sys.exit(1)
