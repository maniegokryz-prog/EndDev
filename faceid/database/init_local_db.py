"""
SQLite Local Database Initialization Script

This script creates and initializes the local SQLite database for the face recognition kiosk.
The local database acts as a cache and temporary storage when the MySQL server is unavailable.

Tables created:
1. employees - Stores employee information for local verification
2. schedules - Schedule definitions
3. schedule_periods - Specific time periods within schedules
4. employee_schedules - Links employees to their assigned schedules
5. attendance_logs - Stores all attendance log entries (time in/out)
6. sync_status - Tracks synchronization state with MySQL server
"""

import sqlite3
import os
import sys
from datetime import datetime

# Fix Windows console encoding for Unicode characters
if sys.platform == 'win32':
    try:
        import codecs
        sys.stdout = codecs.getwriter('utf-8')(sys.stdout.buffer, 'strict')
        sys.stderr = codecs.getwriter('utf-8')(sys.stderr.buffer, 'strict')
    except Exception:
        pass

# Get the database directory path
DB_DIR = os.path.dirname(os.path.abspath(__file__))
DB_PATH = os.path.join(DB_DIR, "kiosk_local.db")

def create_database():
    """
    Creates the SQLite database and all necessary tables.
    If the database already exists, this will not overwrite it.
    """
    print(f"Initializing local database at: {DB_PATH}")
    
    # Connect to SQLite database (creates file if it doesn't exist)
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    # Enable foreign key constraints (SQLite has them disabled by default)
    cursor.execute("PRAGMA foreign_keys = ON")
    
    # ========================================================================
    # Table 1: employees
    # Stores minimal employee data needed for face recognition and logging
    # ========================================================================
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS employees (
            id INTEGER PRIMARY KEY,
            employee_id TEXT NOT NULL UNIQUE,
            first_name TEXT NOT NULL,
            middle_name TEXT,
            last_name TEXT NOT NULL,
            email TEXT,
            phone TEXT,
            department TEXT,
            position TEXT,
            status TEXT DEFAULT 'active',
            profile_photo TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            last_synced TEXT DEFAULT CURRENT_TIMESTAMP
        )
    """)
    print("✓ Created table: employees")
    
    # ========================================================================
    # Table 2: schedules
    # Stores schedule definitions
    # ========================================================================
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS schedules (
            id INTEGER PRIMARY KEY,
            schedule_name TEXT NOT NULL UNIQUE,
            description TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            last_synced TEXT DEFAULT CURRENT_TIMESTAMP
        )
    """)
    print("✓ Created table: schedules")
    
    # ========================================================================
    # Table 3: schedule_periods
    # Stores specific time periods within each schedule
    # ========================================================================
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS schedule_periods (
            id INTEGER PRIMARY KEY,
            schedule_id INTEGER NOT NULL,
            day_of_week INTEGER NOT NULL,
            period_name TEXT,
            start_time TEXT NOT NULL,
            end_time TEXT NOT NULL,
            is_active INTEGER DEFAULT 1,
            last_synced TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE
        )
    """)
    print("✓ Created table: schedule_periods")
    
    # ========================================================================
    # Table 4: employee_schedules
    # Links employees to their assigned schedules
    # ========================================================================
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS employee_schedules (
            id INTEGER PRIMARY KEY,
            employee_id INTEGER NOT NULL,
            schedule_id INTEGER NOT NULL,
            effective_date TEXT NOT NULL,
            end_date TEXT,
            is_active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            last_synced TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE
        )
    """)
    print("✓ Created table: employee_schedules")
    
    # ========================================================================
    # Table 5: attendance_logs
    # Stores all attendance log entries (time in/time out)
    # This is the primary table for attendance tracking
    # ========================================================================
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS attendance_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            log_date TEXT NOT NULL,
            log_type TEXT NOT NULL,
            log_time TEXT NOT NULL,
            source TEXT DEFAULT 'kiosk',
            notes TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            synced INTEGER DEFAULT 0,
            synced_at TEXT,
            mysql_id INTEGER,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        )
    """)
    print("✓ Created table: attendance_logs")
    
    # ========================================================================
    # Table 6: daily_attendance
    # Stores daily attendance summary (calculated from attendance_logs)
    # time_in and time_out store TIME only (HH:MM:SS format)
    # ========================================================================
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS daily_attendance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            attendance_date TEXT NOT NULL,
            time_in TEXT,
            time_out TEXT,
            scheduled_hours REAL,
            actual_hours REAL,
            late_minutes INTEGER DEFAULT 0,
            early_departure_minutes INTEGER DEFAULT 0,
            overtime_minutes INTEGER DEFAULT 0,
            break_time_minutes INTEGER DEFAULT 0,
            status TEXT DEFAULT 'incomplete',
            notes TEXT,
            calculated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            last_synced TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            UNIQUE(employee_id, attendance_date)
        )
    """)
    print("✓ Created table: daily_attendance")
    
    # ========================================================================
    # Table 7: sync_status
    # Tracks synchronization state with the MySQL server
    # ========================================================================
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS sync_status (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            table_name TEXT NOT NULL,
            last_pull_time TEXT,
            last_push_time TEXT,
            last_pull_success INTEGER DEFAULT 1,
            last_push_success INTEGER DEFAULT 1,
            pull_error_message TEXT,
            push_error_message TEXT,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    """)
    print("✓ Created table: sync_status")
    
    # Initialize sync_status with default entries for each table
    tables_to_track = ['employees', 'schedules', 'schedule_periods', 'employee_schedules', 'attendance_logs', 'daily_attendance']
    for table in tables_to_track:
        cursor.execute("""
            INSERT OR IGNORE INTO sync_status (table_name, last_pull_time, last_push_time)
            VALUES (?, datetime('now'), datetime('now'))
        """, (table,))
    
    # ========================================================================
    # Create Indexes for Better Performance
    # ========================================================================
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_employee_id ON employees(employee_id)")
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance_logs(log_date)")
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_attendance_synced ON attendance_logs(synced)")
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_employee_attendance ON attendance_logs(employee_id, log_date)")
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_schedule_periods_schedule ON schedule_periods(schedule_id)")
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_employee_schedules_employee ON employee_schedules(employee_id)")
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_daily_attendance_date ON daily_attendance(employee_id, attendance_date)")
    print("✓ Created indexes for performance optimization")
    
    # Commit all changes
    conn.commit()
    print(f"\n✅ Database initialized successfully at: {DB_PATH}")
    print(f"Database size: {os.path.getsize(DB_PATH)} bytes")
    
    # Display table count
    cursor.execute("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
    tables = cursor.fetchall()
    print(f"Total tables created: {len(tables)}")
    for table in tables:
        print(f"  - {table[0]}")
    
    conn.close()
    return DB_PATH

def get_db_connection():
    """
    Returns a connection to the local SQLite database.
    Use this function in other scripts to access the database.
    
    Returns:
        sqlite3.Connection: Database connection object
    """
    if not os.path.exists(DB_PATH):
        print("Database not found. Initializing...")
        create_database()
    
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row  # Enable column access by name
    conn.execute("PRAGMA foreign_keys = ON")  # Enable foreign key constraints
    return conn

def verify_database():
    """
    Verifies that the database is properly initialized.
    Returns True if all tables exist, False otherwise.
    """
    if not os.path.exists(DB_PATH):
        return False
    
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    required_tables = ['employees', 'schedules', 'schedule_periods', 
                      'employee_schedules', 'attendance_logs', 'daily_attendance', 'sync_status']
    
    for table in required_tables:
        cursor.execute(f"SELECT name FROM sqlite_master WHERE type='table' AND name=?", (table,))
        if cursor.fetchone() is None:
            conn.close()
            return False
    
    conn.close()
    return True

if __name__ == "__main__":
    """
    Run this script directly to initialize the database.
    """
    print("=" * 70)
    print("SQLite Local Database Initialization")
    print("=" * 70)
    
    if os.path.exists(DB_PATH):
        print(f"\n⚠️  Database already exists at: {DB_PATH}")
        response = input("Do you want to reinitialize? (This will NOT delete existing data) [y/N]: ")
        if response.lower() != 'y':
            print("Initialization cancelled.")
            exit(0)
    
    try:
        db_path = create_database()
        
        # Verify the database
        if verify_database():
            print("\n✅ Database verification passed!")
        else:
            print("\n❌ Database verification failed!")
            exit(1)
            
    except Exception as e:
        print(f"\n❌ Error during initialization: {e}")
        import traceback
        traceback.print_exc()
        exit(1)
    
    print("\n" + "=" * 70)
    print("Initialization complete. You can now use the kiosk system.")
    print("=" * 70)
