"""
Virtual Environment Validator and Fixer

Checks if the UV virtual environment is properly configured
and can execute Python scripts.

Usage:
    python check_venv.py
"""

import sys
import os
import subprocess

def check_venv():
    """Check if venv is working properly."""
    print("="*70)
    print("Virtual Environment Validator")
    print("="*70)
    
    # Get project root (parent of this script's directory if in faceid/)
    script_dir = os.path.dirname(os.path.abspath(__file__))
    if os.path.basename(script_dir) == 'faceid':
        project_root = os.path.dirname(script_dir)
    else:
        project_root = script_dir
    
    venv_path = os.path.join(project_root, ".venv")
    venv_python = os.path.join(venv_path, "Scripts", "python.exe")
    pyvenv_cfg = os.path.join(venv_path, "pyvenv.cfg")
    
    print(f"\nProject Root: {project_root}")
    print(f"Venv Path: {venv_path}")
    
    # Check 1: Does venv exist?
    print("\n[1/5] Checking if virtual environment exists...")
    if not os.path.exists(venv_path):
        print("  ❌ Virtual environment not found!")
        print("\n  Fix: Create a new venv")
        print("    cd", project_root)
        print("    uv venv")
        print("    uv sync")
        return False
    print("  ✅ Virtual environment folder exists")
    
    # Check 2: Does python.exe exist in venv?
    print("\n[2/5] Checking if Python executable exists in venv...")
    if not os.path.exists(venv_python):
        print(f"  ❌ Python not found at: {venv_python}")
        print("\n  Fix: Recreate the venv")
        print("    Remove-Item -Recurse -Force .venv")
        print("    uv venv")
        print("    uv sync")
        return False
    print(f"  ✅ Python found: {venv_python}")
    
    # Check 3: Can we run python --version?
    print("\n[3/5] Testing Python execution...")
    try:
        result = subprocess.run(
            [venv_python, "--version"],
            capture_output=True,
            text=True,
            timeout=5
        )
        if result.returncode == 0:
            version = result.stdout.strip()
            print(f"  ✅ Python executes: {version}")
        else:
            print(f"  ❌ Python execution failed")
            print(f"     Error: {result.stderr}")
            return False
    except Exception as e:
        print(f"  ❌ Cannot execute Python: {e}")
        print("\n  This usually means the base Python interpreter is missing.")
        return False
    
    # Check 4: Check pyvenv.cfg for broken references
    print("\n[4/5] Checking pyvenv.cfg configuration...")
    if os.path.exists(pyvenv_cfg):
        try:
            with open(pyvenv_cfg, 'r') as f:
                config = f.read()
            
            print("  Configuration:")
            for line in config.split('\n'):
                if line.strip():
                    print(f"    {line}")
            
            # Extract home path
            for line in config.split('\n'):
                if line.startswith('home'):
                    home_path = line.split('=')[1].strip()
                    python_home = os.path.join(home_path, "python.exe")
                    
                    if os.path.exists(python_home):
                        print(f"\n  ✅ Base Python found: {python_home}")
                    else:
                        print(f"\n  ❌ Base Python NOT found: {python_home}")
                        print("\n  The venv references a Python that doesn't exist!")
                        print("  Fix: Recreate the virtual environment")
                        print("    Remove-Item -Recurse -Force .venv")
                        print("    uv venv")
                        print("    uv sync")
                        return False
        except Exception as e:
            print(f"  ⚠️  Could not read pyvenv.cfg: {e}")
    else:
        print("  ⚠️  pyvenv.cfg not found")
    
    # Check 5: Test importing cv2 (main issue package)
    print("\n[5/5] Testing OpenCV import in venv...")
    try:
        result = subprocess.run(
            [venv_python, "-c", "import cv2; print(f'OpenCV {cv2.__version__}')"],
            capture_output=True,
            text=True,
            timeout=10
        )
        if result.returncode == 0:
            print(f"  ✅ {result.stdout.strip()}")
        else:
            print(f"  ❌ OpenCV import failed")
            print(f"     Error: {result.stderr}")
            print("\n  Fix: Reinstall dependencies")
            print("    uv sync")
            return False
    except Exception as e:
        print(f"  ❌ Cannot test OpenCV: {e}")
        return False
    
    # All checks passed
    print("\n" + "="*70)
    print("✅ ALL CHECKS PASSED!")
    print("="*70)
    print("\nYour virtual environment is working correctly.")
    print("Face embedding generation should work.")
    print("="*70 + "\n")
    return True


if __name__ == "__main__":
    success = check_venv()
    sys.exit(0 if success else 1)
