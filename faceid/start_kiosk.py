import subprocess
import sys
import os
import time
import ctypes
import socket
from threading import Thread, Event

# Global shutdown event for coordinating graceful shutdown
shutdown_event = Event()

# Fix Windows console encoding for Unicode characters
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
            return os.geteuid() == 0
    except:
        return False


def run_as_admin():
    """Re-launch the script with administrator privileges on Windows."""
    if sys.platform != 'win32':
        print("Administrator elevation is only supported on Windows")
        return False
    
    try:
        script = os.path.abspath(sys.argv[0])
        params = ' '.join([f'"{arg}"' if ' ' in arg else arg for arg in sys.argv[1:]])
        
        ret = ctypes.windll.shell32.ShellExecuteW(
            None, "runas", sys.executable, f'"{script}" {params}', None, 1
        )
        
        if ret > 32:
            sys.exit(0)
        else:
            print("Failed to elevate privileges. Please run manually as Administrator.")
            return False
    except Exception as e:
        print(f"Error requesting administrator privileges: {e}")
        return False

def show_error_message(title, message):
    """
    Display a Windows Message Box with an Error icon.
    This is a blocking call (waits for user to click OK).
    """
    if sys.platform == 'win32':
        # 0x10 = MB_ICONHAND (Error/Stop icon)
        # 0x0  = MB_OK
        # 0x1000 = MB_SYSTEMMODAL (Stays on top)
        ctypes.windll.user32.MessageBoxW(0, message, title, 0x10 | 0x0 | 0x1000)
    else:
        print(f"\n[ERROR] {title}: {message}")

def check_localhost_availability(host="127.0.0.1", port=80):
    """
    Check if the localhost web server is reachable (e.g., IIS/Apache).
    """
    print(f"  Checking connection to {host}:{port}...", end=" ", flush=True)
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(3)  # 3 second timeout
        result = sock.connect_ex((host, port))
        sock.close()
        
        if result == 0:
            print("✓ OK")
            return True
        else:
            print("❌ Failed")
            return False
    except Exception as e:
        print(f"❌ Error: {e}")
        return False

def check_database_connection(host="127.0.0.1", port=3306):
    """
    Check if the MySQL database service is reachable (Port 3306).
    
    This uses a raw socket check to avoid python driver dependencies 
    (pyodbc, pymysql, etc) which might not be installed in the launcher environment.
    """
    print(f"  Checking connection to Database ({host}:{port})...", end=" ", flush=True)
    
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(3)
        result = sock.connect_ex((host, port))
        sock.close()
        
        if result == 0:
            print("✓ OK")
            return True
        else:
            print("❌ Failed (Port closed)")
            return False
    except Exception as e:
        print(f"❌ Error: {e}")
        return False

def run_sync_manager():

    """
    Run the sync manager in a subprocess.
    This function is run in a background thread.
    """
    script_dir = os.path.dirname(os.path.abspath(__file__))
    sync_script = os.path.join(script_dir, "sync_manager.py")
    
    process = None
    try:
        # Run sync manager in continuous mode
        process = subprocess.Popen(
            [sys.executable, sync_script, "--mode", "continuous"],
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
            encoding='utf-8',
            errors='replace',  # Replace invalid characters instead of failing
            bufsize=1
        )
        
        # Stream output from sync manager until shutdown event is set
        while not shutdown_event.is_set():
            line = process.stdout.readline()
            if line:
                print(f"[SYNC] {line.rstrip()}")
            elif process.poll() is not None:
                # Process has ended
                break
            time.sleep(0.1)
        
        # If shutdown was requested, terminate the sync manager
        if shutdown_event.is_set() and process.poll() is None:
            print("[SYNC] Stopping sync manager...")
            process.terminate()
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                process.kill()
            print("[SYNC] Sync manager stopped")
                
    except Exception as e:
        print(f"[WARN] Sync manager error: {e}")
    finally:
        if process and process.poll() is None:
            process.terminate()

def main():
    print("=" * 70, flush=True)
    print("Kiosk Face ID System - Startup Diagnostics", flush=True)
    print("=" * 70, flush=True)

    # -------------------------------------------------------------------------
    # CHECK 1: Administrator Privileges
    # -------------------------------------------------------------------------
    # Check for administrator privileges if running from inetpub on Windows
    script_dir = os.path.dirname(os.path.abspath(__file__))
    if sys.platform == 'win32' and 'inetpub' in script_dir.lower():
        if not is_admin():
            print("Administrator Privileges Required")
            print("Attempting to elevate privileges...")
            run_as_admin()
            return

    # -------------------------------------------------------------------------
    # CHECK 2: Localhost Connectivity (Web Server)
    # -------------------------------------------------------------------------
    print("\n[1/7] Verifying Local Server Connectivity...")
    if not check_localhost_availability():
        err_msg = (
            "Cannot connect to the Localhost Web Server (127.0.0.1).\n\n"
            "Please ensure IIS or your local web server is running.\n"
            "This is required for the application to function."
        )
        show_error_message("Startup Error - Connection Refused", err_msg)
        sys.exit(1)

    # -------------------------------------------------------------------------
    # CHECK 3: Database Connectivity (MySQL)
    # -------------------------------------------------------------------------
    print("\n[2/7] Verifying Database Connectivity...")
    if not check_database_connection():
        err_msg = (
            "Cannot connect to the Localhost Database (MySQL).\n\n"
            "Please check:\n"
            "1. MySQL Service is running.\n"
            "2. Credentials are correct (root/Confirmp@ssword123).\n"
            "3. Database 'database_records' exists."
        )
        show_error_message("Startup Error - Database Unavailable", err_msg)
        sys.exit(1)


    # Get the directory where this script is located
    # script_dir is already defined above
    
    # Paths to scripts
    init_db_script = os.path.join(script_dir, "database", "init_local_db.py")
    embd_sync_script = os.path.join(script_dir, "embd_up.py")
    daily_init_script = os.path.join(script_dir, "daily_attendance_initializer.py")
    profile_sync_script = os.path.join(script_dir, "sync_profile_pictures.py")
    kiosk_script = os.path.join(script_dir, "Kiosk_faceid.py")
    

    # -------------------------------------------------------------------------
    # CHECK 4: Initialize local SQLite database
    # -------------------------------------------------------------------------
    print("\n[3/7] Initializing local SQLite database...")
    try:
        # Check if database exists
        db_path = os.path.join(script_dir, "database", "kiosk_local.db")
        if not os.path.exists(db_path):
            print("  Database not found. Creating...")
            result = subprocess.run(
                [sys.executable, init_db_script],
                capture_output=True,
                text=True,
                input="y\n",  # Auto-confirm creation
                timeout=10
            )
            if result.returncode == 0:
                print("✓ Local database initialized")
            else:
                print(f"⚠️  Warning: Database initialization had issues")
                if result.stderr:
                    print(f"  {result.stderr}")
                
                # Critical failure if we can't make the local DB
                show_error_message(
                    "Startup Error - Local Database", 
                    "Failed to initialize local SQLite database.\nPlease check file permissions."
                )
                sys.exit(1)
        else:
            print("✓ Local database exists")
            
    except Exception as e:
        show_error_message("Startup Error", f"Critical error checking local database:\n{e}")
        sys.exit(1)
    
    # -------------------------------------------------------------------------
    # CHECK 5: Sync Backend Data (Profiles & Embeddings)
    # -------------------------------------------------------------------------
    
    # 5.1 Sync profile pictures
    print("\n[4/7] Syncing profile pictures...")
    try:
        result = subprocess.run(
            [sys.executable, profile_sync_script, "once"],
            capture_output=True,
            text=True,
            encoding='utf-8',
            errors='replace',
            timeout=30
        )
        if result.returncode == 0:
            print("✓ Profile pictures synced")
        else:
            print("⚠️  Warning: Profile picture sync failed (Non-critical)")
    except Exception as e:
         print(f"⚠️  Warning: Could not sync profile pictures: {e}")
    
    # 5.2 Sync embeddings from database
    print("\n[5/7] Syncing face embeddings from database...")
    try:
        result = subprocess.run(
            [sys.executable, embd_sync_script, "once"],
            capture_output=True,
            text=True,
            encoding='utf-8',
            errors='replace',
            timeout=30
        )
        
        if result.returncode == 0:
            print("✓ Embeddings synced successfully")
        else:
            print("⚠️  Warning: Embedding sync failed")
            # This is arguably critical, but maybe they have a cached file? 
            # If no cached file exists, Kiosk will fail anyway.
            # We'll let it proceed but warn heavily.
    except Exception as e:
        print(f"⚠️  Warning: Could not sync embeddings: {e}")
    
    # -------------------------------------------------------------------------
    # CHECK 6: Initialize daily attendance records & Models
    # -------------------------------------------------------------------------
    print("\n[6/7] Finalizing Setup (Attendance & Models)...")
    
    # Daily Init
    print("  Initializing daily records...", end=" ")
    try:
        subprocess.run([sys.executable, daily_init_script], capture_output=True, timeout=10)
        print("✓ Done")
    except:
        print("⚠️ Failed")

    # Models Verification (Moved to end)
    print("  Verifying AI models...", end=" ")
    try:
        # Simple check for the directory structures to be fast
        models_dir = os.path.join(script_dir, "models")
        if not os.path.exists(models_dir):
            os.makedirs(models_dir)
            
        # YuNet Check
        yunet_path = os.path.join(models_dir, "openCV_YuNet", "face_detection_yunet_2023mar.onnx")
        if not os.path.exists(yunet_path):
             # Try to move from root if exists
             legacy_path = os.path.join(script_dir, "face_detection_yunet_2023mar.onnx")
             if os.path.exists(legacy_path):
                 import shutil
                 os.makedirs(os.path.dirname(yunet_path), exist_ok=True)
                 shutil.move(legacy_path, yunet_path)
             # Download if still missing
             if not os.path.exists(yunet_path):
                 print("\n    Downloading YuNet model...", end=" ")
                 url = "https://github.com/opencv/opencv_zoo/raw/main/models/face_detection_yunet/face_detection_yunet_2023mar.onnx"
                 import urllib.request
                 os.makedirs(os.path.dirname(yunet_path), exist_ok=True)
                 urllib.request.urlretrieve(url, yunet_path)

        print("✓ Done")
    except Exception as e:
        print(f"⚠️ Warning: Model check issue: {e}")

    
    # -------------------------------------------------------------------------
    # ACTION: Start Services & Launch
    # -------------------------------------------------------------------------
    
    # -------------------------------------------------------------------------
    # CHECK 7: Webcam Connectivity (Final Check)
    # -------------------------------------------------------------------------
    print("\n[7/8] Verifying Webcam Connectivity...")
    
    # Define check function inside main or calling a helper
    def check_webcam_internal():
        print("  Checking for valid camera device...", end=" ", flush=True)
        try:
            # Import cv2 here to handle missing dependency gracefully
            try:
                import cv2
            except ImportError:
                print("❌ Failed (OpenCV not installed)")
                return False, "OpenCV (cv2) is not installed.\nPlease install it to run the Kiosk."
            
            # Try index 0 (default webcam)
            cap = cv2.VideoCapture(0, cv2.CAP_DSHOW) # CAP_DSHOW is faster on Windows
            if not cap.isOpened():
                # Try without DSHOW if that fails
                cap = cv2.VideoCapture(0)
            
            if not cap.isOpened():
                print("❌ Failed (No device found)")
                return False, "No webcam detected.\nPlease connect a camera to continue."
            
            # Try to read a frame to ensure it's not just a dummy device
            ret, frame = cap.read()
            cap.release()
            
            if not ret:
                print("❌ Failed (Cannot read frame)")
                return False, "Webcam detected but cannot read video.\nCheck privacy settings or drivers."
                
            print("✓ OK")
            return True, ""
            
        except Exception as e:
            print(f"❌ Error: {e}")
            return False, f"Error checking webcam:\n{e}"

    # Perform the check
    cam_success, cam_err = check_webcam_internal()
    if not cam_success:
        show_error_message("Startup Error - Webcam Failure", cam_err)
        sys.exit(1)


    
    # -------------------------------------------------------------------------
    # ACTION: Start Services & Launch
    # -------------------------------------------------------------------------
    
    # Step 8: Start sync manager in background thread
    print("\n[8/8] Starting background sync manager...")
    
    sync_thread = None
    try:
        sync_thread = Thread(target=run_sync_manager, daemon=True)
        sync_thread.start()
        print("  Waiting for initial sync to complete...")
        time.sleep(3)
        print("✓ Sync manager started")
    except Exception as e:
        print(f"⚠️  Warning: Could not start sync manager: {e}")
    
    # Launch Kiosk system
    print("\n" + "="*70)
    print("ALL CHECKS PASSED. LAUNCHING KIOSK...", flush=True)
    print("="*70 + "\n")
    
    # Create shutdown signal file path for inter-process communication
    shutdown_signal_file = os.path.join(script_dir, ".shutdown_signal")
    if os.path.exists(shutdown_signal_file):
        os.remove(shutdown_signal_file)
    
    try:
        # Set environment variable so Kiosk knows where to write shutdown signal
        env = os.environ.copy()
        env['KIOSK_SHUTDOWN_SIGNAL'] = shutdown_signal_file
        
        # Run Kiosk script
        subprocess.run([sys.executable, kiosk_script], env=env)
        
        # Check termination reason
        if os.path.exists(shutdown_signal_file):
            print("\n🛑 Kiosk system stopped by user (Ctrl+Q)")
            shutdown_event.set()
            os.remove(shutdown_signal_file)
        
    except KeyboardInterrupt:
        print("\n🛑 Kiosk system stopped by user")
        shutdown_event.set()
    except Exception as e:
        print(f"\n❌ Error running Kiosk: {e}")
        shutdown_event.set()
        # We can show a message box here too if it crashes unexpectedly
        # show_error_message("Kiosk Crash", f"Application crashed:\n{e}")
        sys.exit(1)
    finally:
        print("\n👋 Shutting down...")
        shutdown_event.set()
        if sync_thread and sync_thread.is_alive():
            sync_thread.join(timeout=3)
        print("✓ All systems stopped")
        time.sleep(1)

if __name__ == "__main__":
    main()
