"""
OpenCV Installation Checker and Fixer

This script checks if the correct version of OpenCV is installed
and provides instructions to fix it if needed.

Usage:
    python check_opencv.py
"""

import sys
import subprocess

def check_opencv_installation():
    """Check which OpenCV package is installed."""
    print("="*70)
    print("OpenCV Installation Checker")
    print("="*70)
    print("\nChecking installed OpenCV packages...\n")
    
    try:
        # Try uv first, then fall back to pip
        result = subprocess.run(
            ["uv", "pip", "list"],
            capture_output=True,
            text=True,
            encoding='utf-8',
            errors='replace'
        )
        
        # If uv fails, try regular pip
        if result.returncode != 0:
            result = subprocess.run(
                [sys.executable, "-m", "pip", "list"],
                capture_output=True,
                text=True,
                encoding='utf-8',
                errors='replace'
            )
        
        opencv_packages = []
        for line in result.stdout.split('\n'):
            if 'opencv' in line.lower():
                opencv_packages.append(line.strip())
        
        # If no packages found via pip list, try direct import
        if not opencv_packages:
            print("No OpenCV found via package manager, testing direct import...")
            try:
                import cv2
                opencv_packages.append(f"opencv-python {cv2.__version__} (detected via import)")
                print(f"  • Found OpenCV {cv2.__version__} in Python environment")
            except ImportError:
                pass
        
        if not opencv_packages:
            print("❌ No OpenCV package found!")
            print("\n📋 Install opencv-python:")
            print("   uv pip install opencv-python>=4.12.0.88")
            return False
        
        print("Found OpenCV packages:")
        for pkg in opencv_packages:
            print(f"  • {pkg}")
        
        # Check for headless version
        has_headless = any('headless' in pkg.lower() for pkg in opencv_packages)
        has_regular = any('opencv-python' in pkg.lower() and 'headless' not in pkg.lower() for pkg in opencv_packages)
        
        if has_headless and not has_regular:
            print("\n❌ PROBLEM DETECTED!")
            print("   You have opencv-python-headless installed.")
            print("   This version does NOT support GUI windows!")
            print("\n📋 To fix this:")
            print("\n   Step 1 - Uninstall headless version:")
            print("   uv pip uninstall opencv-python-headless opencv-contrib-python-headless")
            print("\n   Step 2 - Install full version:")
            print("   uv sync --reinstall-package opencv-python")
            print("\n   Step 3 - Verify fix:")
            print("   python check_opencv.py")
            return False
        
        elif has_regular:
            print("\n✅ Correct OpenCV package installed!")
            
            # Test GUI support
            print("\nTesting GUI support...")
            try:
                import cv2
                test_window = "test"
                cv2.namedWindow(test_window, cv2.WINDOW_NORMAL)
                cv2.destroyWindow(test_window)
                cv2.waitKey(1)
                print("✅ GUI support working!")
                print("\n" + "="*70)
                print("All checks passed! You're ready to run the Kiosk system.")
                print("="*70)
                return True
            except Exception as e:
                print(f"❌ GUI test failed: {e}")
                print("\n📋 Try reinstalling:")
                print("   uv pip uninstall opencv-python")
                print("   uv sync --reinstall-package opencv-python")
                return False
        else:
            print("\n⚠️  Unknown OpenCV configuration")
            return False
            
    except Exception as e:
        print(f"❌ Error checking installation: {e}")
        return False

if __name__ == "__main__":
    success = check_opencv_installation()
    sys.exit(0 if success else 1)
