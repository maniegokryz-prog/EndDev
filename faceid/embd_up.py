"""
Face Embeddings Database Monitor and NPY File Generator

This script continuously monitors the MySQL database for face embeddings
and automatically generates/updates the authorized_embedding.npy file
used by the Kiosk Face ID system.

Features:
- Monitors face_embeddings table for changes every minute
- Loads all employee face embeddings from database
- Saves embeddings to NPY file for real-time verification
- Automatically runs before Kiosk_faceid.py starts
- Logs all activities for debugging

The generated NPY file structure:
{
    'embeddings': numpy array of shape (N, 512),  # All face embeddings
    'employee_ids': list of employee database IDs,
    'last_update': timestamp of last database query
}
"""

import mysql.connector
import numpy as np
import os
import time
from datetime import datetime
import sys

# Configuration
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': 'Confirmp@ssword123',
    'database': 'database_records'
}

# Get the directory where this script is located
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
# Store embeddings and log files in the database folder
DATABASE_DIR = os.path.join(SCRIPT_DIR, "database")
EMBEDDINGS_FILE = os.path.join(DATABASE_DIR, "authorized_embeddings.npy")
LOG_FILE = os.path.join(DATABASE_DIR, "embedding_monitor.log")

# Check interval (seconds)
CHECK_INTERVAL = 60  # 1 minute


def log_message(message, level="INFO"):
    """
    Log a message to both console and log file.
    
    Args:
        message: Message to log
        level: Log level (INFO, WARNING, ERROR)
    """
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    log_entry = f"[{timestamp}] [{level}] {message}"
    
    # Print to console
    print(log_entry)
    
    # Append to log file
    try:
        with open(LOG_FILE, 'a') as f:
            f.write(log_entry + "\n")
    except Exception as e:
        print(f"Warning: Could not write to log file: {e}")


def connect_to_database():
    """
    Establish connection to MySQL database.
    
    Returns:
        mysql.connector.connection.MySQLConnection or None
    """
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        log_message("Successfully connected to database")
        return conn
    except Exception as e:
        log_message(f"Failed to connect to database: {e}", "ERROR")
        return None


def fetch_all_embeddings():
    """
    Fetch all face embeddings from the database.
    
    Returns:
        tuple: (embeddings_array, employee_ids, embedding_count) or (None, None, 0)
    """
    conn = connect_to_database()
    if not conn:
        return None, None, 0
    
    try:
        cursor = conn.cursor()
        
        # Query to get all embeddings with their employee IDs
        query = """
            SELECT 
                fe.employee_id, 
                fe.embedding_data,
                e.employee_id as employee_code,
                e.first_name,
                e.last_name
            FROM face_embeddings fe
            JOIN employees e ON fe.employee_id = e.id
            ORDER BY fe.employee_id, fe.embedding_id
        """
        
        cursor.execute(query)
        results = cursor.fetchall()
        
        if not results:
            log_message("No face embeddings found in database", "WARNING")
            cursor.close()
            conn.close()
            return None, None, 0
        
        # Parse the results
        embeddings_list = []
        employee_ids = []
        employee_info = []
        
        for row in results:
            db_employee_id, embedding_blob, employee_code, first_name, last_name = row
            
            # Convert binary blob back to numpy array
            embedding = np.frombuffer(embedding_blob, dtype=np.float32)
            
            # Verify embedding shape (should be 512 dimensions)
            if embedding.shape[0] != 512:
                log_message(f"Warning: Invalid embedding shape {embedding.shape} for employee {employee_code}", "WARNING")
                continue
            
            embeddings_list.append(embedding)
            employee_ids.append(db_employee_id)
            employee_info.append({
                'db_id': db_employee_id,
                'employee_code': employee_code,
                'name': f"{first_name} {last_name}"
            })
        
        cursor.close()
        conn.close()
        
        if not embeddings_list:
            log_message("No valid embeddings found after parsing", "WARNING")
            return None, None, 0
        
        # Convert to numpy array
        embeddings_array = np.array(embeddings_list)
        
        log_message(f"Fetched {len(embeddings_list)} embeddings from database")
        log_message(f"Unique employees: {len(set(employee_ids))}")
        
        # Log employee details
        for info in employee_info[:5]:  # Show first 5
            log_message(f"  - {info['name']} ({info['employee_code']}): DB ID {info['db_id']}")
        if len(employee_info) > 5:
            log_message(f"  ... and {len(employee_info) - 5} more")
        
        return embeddings_array, employee_ids, employee_info
        
    except Exception as e:
        log_message(f"Error fetching embeddings: {e}", "ERROR")
        if conn:
            conn.close()
        return None, None, 0


def save_embeddings_to_file(embeddings, employee_ids, employee_info):
    """
    Save embeddings to NPY file for Kiosk system.
    
    Args:
        embeddings: Numpy array of face embeddings
        employee_ids: List of employee database IDs
        employee_info: List of employee information dictionaries
        
    Returns:
        bool: True if successful, False otherwise
    """
    try:
        # Create data structure to save
        data = {
            'embeddings': embeddings,
            'employee_ids': employee_ids,
            'employee_info': employee_info,
            'last_update': datetime.now().isoformat(),
            'total_embeddings': len(embeddings),
            'unique_employees': len(set(employee_ids))
        }
        
        # Save to NPY file
        np.save(EMBEDDINGS_FILE, data, allow_pickle=True)
        
        log_message(f"Successfully saved {len(embeddings)} embeddings to {EMBEDDINGS_FILE}")
        log_message(f"File size: {os.path.getsize(EMBEDDINGS_FILE) / 1024:.2f} KB")
        
        return True
        
    except Exception as e:
        log_message(f"Error saving embeddings to file: {e}", "ERROR")
        return False


def get_database_last_modified():
    """
    Get the timestamp of the most recent face embedding in the database.
    
    Returns:
        datetime or None
    """
    conn = connect_to_database()
    if not conn:
        return None
    
    try:
        cursor = conn.cursor()
        cursor.execute("SELECT MAX(created_at) FROM face_embeddings")
        result = cursor.fetchone()
        cursor.close()
        conn.close()
        
        if result and result[0]:
            return result[0]
        return None
        
    except Exception as e:
        log_message(f"Error checking database timestamp: {e}", "ERROR")
        if conn:
            conn.close()
        return None


def get_file_last_update():
    """
    Get the last update timestamp from the NPY file.
    
    Returns:
        datetime or None
    """
    if not os.path.exists(EMBEDDINGS_FILE):
        return None
    
    try:
        data = np.load(EMBEDDINGS_FILE, allow_pickle=True).item()
        last_update_str = data.get('last_update')
        if last_update_str:
            return datetime.fromisoformat(last_update_str)
        return None
    except Exception as e:
        log_message(f"Error reading file timestamp: {e}", "WARNING")
        return None


def update_embeddings():
    """
    Check database and update embeddings file if needed.
    
    Returns:
        bool: True if update was performed, False if no update needed
    """
    log_message("Checking for database updates...")
    
    # Fetch embeddings from database
    embeddings, employee_ids, employee_info = fetch_all_embeddings()
    
    if embeddings is None:
        log_message("No embeddings to save", "WARNING")
        return False
    
    # Save to file
    success = save_embeddings_to_file(embeddings, employee_ids, employee_info)
    
    return success


def run_once_and_exit():
    """
    Run the update once and exit. Used when called before Kiosk starts.
    """
    log_message("=" * 60)
    log_message("Face Embeddings Database Sync - Single Run Mode")
    log_message("=" * 60)
    
    success = update_embeddings()
    
    if success:
        log_message("Embeddings file updated successfully")
        sys.exit(0)
    else:
        log_message("Failed to update embeddings file", "ERROR")
        sys.exit(1)


def run_continuous_monitor():
    """
    Continuously monitor database and update embeddings file.
    Runs indefinitely with CHECK_INTERVAL between checks.
    """
    log_message("=" * 60)
    log_message("Face Embeddings Database Monitor - Continuous Mode")
    log_message(f"Check interval: {CHECK_INTERVAL} seconds")
    log_message("=" * 60)
    
    # Initial update
    log_message("Performing initial embeddings sync...")
    update_embeddings()
    
    # Continuous monitoring loop
    try:
        while True:
            log_message(f"Waiting {CHECK_INTERVAL} seconds before next check...")
            time.sleep(CHECK_INTERVAL)
            
            # Check for updates
            update_embeddings()
            
    except KeyboardInterrupt:
        log_message("Monitor stopped by user (Ctrl+C)")
        sys.exit(0)
    except Exception as e:
        log_message(f"Unexpected error in monitor loop: {e}", "ERROR")
        sys.exit(1)


def print_usage():
    """Print usage information."""
    print("""
Face Embeddings Database Monitor

Usage:
    python embd_up.py [mode]

Modes:
    once      - Run once and exit (default, used before Kiosk starts)
    monitor   - Continuously monitor database for changes
    
Examples:
    python embd_up.py           # Run once and exit
    python embd_up.py once      # Same as above
    python embd_up.py monitor   # Continuous monitoring

The script will:
1. Connect to MySQL database
2. Fetch all face embeddings
3. Save to authorized_embeddings.npy
4. Exit (once mode) or continue monitoring (monitor mode)
""")


def main():
    """Main entry point."""
    # Check command line arguments
    mode = "once"  # Default mode
    
    if len(sys.argv) > 1:
        mode = sys.argv[1].lower()
        
        if mode not in ['once', 'monitor', 'help', '--help', '-h']:
            print(f"Error: Unknown mode '{mode}'")
            print_usage()
            sys.exit(1)
        
        if mode in ['help', '--help', '-h']:
            print_usage()
            sys.exit(0)
    
    # Run in selected mode
    if mode == "once":
        run_once_and_exit()
    elif mode == "monitor":
        run_continuous_monitor()


if __name__ == "__main__":
    main()
