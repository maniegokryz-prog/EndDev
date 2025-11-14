"""
Profile Picture Synchronization Script

This script copies employee profile pictures from the web assets folder
to the kiosk user_profile folder for display during face verification.

Features:
1. Syncs profile pictures from assets/profile_pic to faceid/database/user_profile
2. Can run over local network using UNC paths or local paths
3. Only copies new or updated images
4. Maintains filename structure (employee_id based naming)
5. Auto-elevates to Administrator on Windows if needed

Usage:
    python sync_profile_pictures.py [--mode once|continuous]
    
    --mode once: Run once and exit (default)
    --mode continuous: Run continuously, checking every 60 seconds
"""

import sys
import os
import shutil
import time
from datetime import datetime
from pathlib import Path
import ctypes

# Fix Windows console encoding
if sys.platform == 'win32':
    try:
        import codecs
        sys.stdout = codecs.getwriter('utf-8')(sys.stdout.buffer, 'strict')
        sys.stderr = codecs.getwriter('utf-8')(sys.stderr.buffer, 'strict')
    except Exception:
        pass


def is_admin():
    """Check if running with administrator privileges on Windows."""
    try:
        if sys.platform == 'win32':
            return ctypes.windll.shell32.IsUserAnAdmin()
        else:
            # On Unix-like systems, check if running as root
            return os.geteuid() == 0
    except:
        return False


def run_as_admin():
    """Re-launch the script with administrator privileges on Windows."""
    if sys.platform != 'win32':
        print("Administrator elevation is only supported on Windows")
        return False
    
    try:
        # Get the current script path and arguments
        script = os.path.abspath(sys.argv[0])
        params = ' '.join([f'"{arg}"' if ' ' in arg else arg for arg in sys.argv[1:]])
        
        # Use ShellExecute to run with elevated privileges
        ret = ctypes.windll.shell32.ShellExecuteW(
            None,           # parent window handle
            "runas",        # operation (runas = run as administrator)
            sys.executable, # executable (python.exe)
            f'"{script}" {params}',  # parameters
            None,           # working directory
            1               # show command (1 = SW_SHOWNORMAL)
        )
        
        # If ShellExecute returns > 32, it was successful
        if ret > 32:
            # Exit the current non-admin instance
            sys.exit(0)
        else:
            print("Failed to elevate privileges. Please run manually as Administrator.")
            return False
            
    except Exception as e:
        print(f"Error requesting administrator privileges: {e}")
        return False

# Configuration
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.dirname(SCRIPT_DIR)

# Source: Web assets profile pictures folder
SOURCE_DIR = os.path.join(PROJECT_ROOT, "assets", "profile_pic")

# Destination: Kiosk user profile folder
DEST_DIR = os.path.join(SCRIPT_DIR, "database", "user_profile")

# For network access, you can override these with UNC paths:
# Example: SOURCE_DIR = r"\\SERVER\Share\EndDev\assets\profile_pic"
# Example: DEST_DIR = r"\\KIOSK-PC\Share\faceid\database\user_profile"

# Supported image extensions
IMAGE_EXTENSIONS = {'.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp'}


def ensure_directory_exists(directory):
    """Create directory if it doesn't exist."""
    try:
        Path(directory).mkdir(parents=True, exist_ok=True)
        return True
    except Exception as e:
        print(f"❌ Error creating directory {directory}: {e}")
        return False


def is_image_file(filename):
    """Check if file is an image based on extension."""
    return Path(filename).suffix.lower() in IMAGE_EXTENSIONS


def should_copy_file(source_path, dest_path):
    """
    Determine if file should be copied.
    Copies if:
    - Destination doesn't exist
    - Source is newer than destination
    - File sizes differ
    """
    if not os.path.exists(dest_path):
        return True
    
    # Compare modification times
    source_mtime = os.path.getmtime(source_path)
    dest_mtime = os.path.getmtime(dest_path)
    
    if source_mtime > dest_mtime:
        return True
    
    # Compare file sizes
    source_size = os.path.getsize(source_path)
    dest_size = os.path.getsize(dest_path)
    
    if source_size != dest_size:
        return True
    
    return False


def sync_profile_pictures():
    """
    Synchronize profile pictures from source to destination.
    
    Returns:
        tuple: (success: bool, copied_count: int, skipped_count: int, error_count: int)
    """
    print(f"\n{'=' * 70}")
    print(f"Profile Picture Sync - {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"{'=' * 70}")
    
    # Verify source directory exists
    if not os.path.exists(SOURCE_DIR):
        print(f"Source directory not found: {SOURCE_DIR}")
        print(f"Make sure the path is correct or accessible over network")
        return False, 0, 0, 1
    
    # Create destination directory if needed
    if not ensure_directory_exists(DEST_DIR):
        return False, 0, 0, 1
    
    print(f"Source: {SOURCE_DIR}")
    print(f"Destination: {DEST_DIR}")
    print()
    
    copied_count = 0
    skipped_count = 0
    error_count = 0
    
    try:
        # Get all files in source directory
        source_files = [f for f in os.listdir(SOURCE_DIR) if os.path.isfile(os.path.join(SOURCE_DIR, f))]
        
        # Filter for image files only
        image_files = [f for f in source_files if is_image_file(f)]
        
        if not image_files:
            print("No image files found in source directory")
            return True, 0, 0, 0
        
        print(f"Found {len(image_files)} image file(s) in source directory")
        print()
        
        for filename in image_files:
            source_path = os.path.join(SOURCE_DIR, filename)
            dest_path = os.path.join(DEST_DIR, filename)
            
            try:
                # Check if we need to copy this file
                if should_copy_file(source_path, dest_path):
                    # Copy the file
                    shutil.copy2(source_path, dest_path)  # copy2 preserves metadata
                    print(f"Copied: {filename}")
                    copied_count += 1
                else:
                    print(f"Skipped: {filename} (already up-to-date)")
                    skipped_count += 1
                    
            except Exception as e:
                print(f"Error copying {filename}: {e}")
                error_count += 1
        
        print()
        print(f"{'=' * 70}")
        print(f"Sync completed: {copied_count} copied, {skipped_count} skipped, {error_count} errors")
        print(f"{'=' * 70}")
        
        return True, copied_count, skipped_count, error_count
        
    except Exception as e:
        print(f"Error during sync: {e}")
        import traceback
        traceback.print_exc()
        return False, copied_count, skipped_count, error_count + 1


def run_continuous_sync(interval=60):
    """
    Run sync continuously at specified interval.
    
    Args:
        interval: Seconds between sync runs (default: 60)
    """
    print(f"Starting continuous sync mode (every {interval} seconds)")
    print(f"Press Ctrl+C to stop")
    print()
    
    try:
        while True:
            sync_profile_pictures()
            print(f"\nWaiting {interval} seconds until next sync...")
            print(f"Next sync at: {datetime.now().strftime('%H:%M:%S')}")
            time.sleep(interval)
            
    except KeyboardInterrupt:
        print("\n\nContinuous sync stopped by user")
        return True


def main():
    """Main entry point for the script."""
    # Check for administrator privileges on Windows when accessing inetpub
    if sys.platform == 'win32' and 'inetpub' in SOURCE_DIR.lower():
        if not is_admin():
            print("=" * 70)
            print("Administrator Privileges Required")
            print("=" * 70)
            print("This script needs administrator access to read from:")
            print(f"  {SOURCE_DIR}")
            print("\nAttempting to elevate privileges...")
            print("Please click 'Yes' on the UAC prompt if it appears.")
            print("=" * 70)
            time.sleep(2)
            run_as_admin()
            return
        else:
            print("Running with Administrator privileges")
    
    # Parse command line arguments
    mode = "once"  # Default mode
    
    if len(sys.argv) > 1:
        if sys.argv[1] in ["once", "continuous"]:
            mode = sys.argv[1]
        elif sys.argv[1] in ["--mode"] and len(sys.argv) > 2:
            mode = sys.argv[2]
    
    if mode == "continuous":
        run_continuous_sync(interval=60)
    else:
        # Run once
        success, copied, skipped, errors = sync_profile_pictures()
        
        if not success or errors > 0:
            sys.exit(1)


if __name__ == "__main__":
    main()
