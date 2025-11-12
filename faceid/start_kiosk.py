"""
Kiosk Face ID Launcher with Automatic Embedding Sync and Database Synchronization

This script ensures face embeddings are up-to-date before starting the Kiosk system,
and runs background synchronization with the MySQL server.

Workflow:
1. Initialize local SQLite database (if needed)
2. Run embd_up.py to sync embeddings from database
3. Start background sync manager (SQLite <-> MySQL)
4. Launch Kiosk_faceid.py for face verification
5. If sync fails, show warning but still launch Kiosk

Usage:
    python start_kiosk.py
"""

import subprocess
import sys
import os
import time
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
    # Get the directory where this script is located
    script_dir = os.path.dirname(os.path.abspath(__file__))
    
    # Paths to scripts
    init_db_script = os.path.join(script_dir, "database", "init_local_db.py")
    embd_sync_script = os.path.join(script_dir, "embd_up.py")
    daily_init_script = os.path.join(script_dir, "daily_attendance_initializer.py")
    kiosk_script = os.path.join(script_dir, "Kiosk_faceid.py")
    
    print("=" * 70, flush=True)
    print("Kiosk Face ID System - Starting...", flush=True)
    print("=" * 70, flush=True)
    
    # Step 1: Initialize local database
    print("\n[1/5] Initializing local SQLite database...")
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
                print(f"  {result.stderr}")
        else:
            print("✓ Local database exists")
    except Exception as e:
        print(f"⚠️  Warning: Could not initialize database: {e}")
        print("  Continuing anyway...")
    
    # Step 2: Sync embeddings from database
    print("\n[2/5] Syncing face embeddings from database...")
    try:
        result = subprocess.run(
            [sys.executable, embd_sync_script, "once"],
            capture_output=True,
            text=True,
            timeout=30  # 30 second timeout
        )
        
        if result.returncode == 0:
            print("✓ Embeddings synced successfully")
            # Show last few lines of output
            output_lines = result.stdout.strip().split('\n')
            for line in output_lines[-3:]:
                if line.strip():
                    print(f"  {line}")
        else:
            print("⚠️  Warning: Embedding sync failed")
            print(f"  Error: {result.stderr}")
            print("  Continuing anyway...")
    except subprocess.TimeoutExpired:
        print("⚠️  Warning: Embedding sync timed out")
        print("  Continuing anyway...")
    except Exception as e:
        print(f"⚠️  Warning: Could not sync embeddings: {e}")
        print("  Continuing anyway...")
    
    # Step 3: Initialize daily attendance records
    print("\n[3/5] Initializing daily attendance records...")
    try:
        result = subprocess.run(
            [sys.executable, daily_init_script],
            capture_output=True,
            text=True,
            encoding='utf-8',
            errors='replace',  # Replace invalid characters instead of failing
            timeout=10  # 10 second timeout
        )
        
        if result.returncode == 0:
            print("✓ Daily attendance initialized")
            # Show output
            if result.stdout:
                output_lines = result.stdout.strip().split('\n')
                for line in output_lines:
                    if line.strip() and not line.startswith('='):
                        print(f"  {line}")
        else:
            print("⚠️  Warning: Daily attendance initialization failed")
            if result.stderr:
                print(f"  Error: {result.stderr}")
            print("  Continuing anyway...")
    except subprocess.TimeoutExpired:
        print("⚠️  Warning: Daily attendance initialization timed out")
        print("  Continuing anyway...")
    except Exception as e:
        print(f"⚠️  Warning: Could not initialize daily attendance: {e}")
        print("  Continuing anyway...")
    
    # Step 4: Start sync manager in background thread
    print("\n[4/5] Starting background sync manager...")
    print("  - Push: Attendance logs to MySQL (every 5 seconds)")
    print("  - Pull: Employee/schedule updates from MySQL (every 60 seconds)")
    
    sync_thread = None
    try:
        sync_thread = Thread(target=run_sync_manager, daemon=True)
        sync_thread.start()
        print("  Waiting for initial sync to complete...")
        time.sleep(5)  # Give sync manager time to complete initial pull
        print("✓ Sync manager started in background")
    except Exception as e:
        print(f"⚠️  Warning: Could not start sync manager: {e}")
        print("  Attendance logs will be stored locally but not synced")
    
    # Step 5: Launch Kiosk system
    print("\n[5/5] Starting Kiosk Face Verification System...", flush=True)
    print("=" * 70, flush=True)
    print("\n💡 TIP: Attendance is logged automatically when faces are verified", flush=True)
    print("💡 TIP: Sync runs in background - logs are sent to MySQL automatically", flush=True)
    print("💡 TIP: Press Ctrl+Q in the face recognition window to exit\n", flush=True)
    print("=" * 70, flush=True)
    print(flush=True)
    
    # Create shutdown signal file path for inter-process communication
    shutdown_signal_file = os.path.join(script_dir, ".shutdown_signal")
    # Remove old signal file if it exists
    if os.path.exists(shutdown_signal_file):
        os.remove(shutdown_signal_file)
    
    try:
        # Set environment variable so Kiosk knows where to write shutdown signal
        env = os.environ.copy()
        env['KIOSK_SHUTDOWN_SIGNAL'] = shutdown_signal_file
        
        # Run Kiosk script (this will block until Kiosk exits)
        subprocess.run([sys.executable, kiosk_script], env=env)
        
        # Check if shutdown was triggered by Ctrl+Q
        if os.path.exists(shutdown_signal_file):
            print("\n\n🛑 Kiosk system stopped by user (Ctrl+Q)")
            shutdown_event.set()  # Signal sync manager to stop
            os.remove(shutdown_signal_file)  # Clean up
        
    except KeyboardInterrupt:
        print("\n\n🛑 Kiosk system stopped by user")
        shutdown_event.set()
    except Exception as e:
        print(f"\n\n❌ Error running Kiosk: {e}")
        shutdown_event.set()
        sys.exit(1)
    finally:
        # Cleanup message
        print("\n👋 Shutting down...")
        shutdown_event.set()  # Ensure sync manager stops
        print("   Waiting for sync manager to stop...")
        if sync_thread and sync_thread.is_alive():
            sync_thread.join(timeout=3)  # Wait up to 3 seconds
        print("   ✓ All systems stopped")
        time.sleep(1)

if __name__ == "__main__":
    main()
