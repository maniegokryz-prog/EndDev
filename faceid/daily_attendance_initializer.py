"""
Daily Attendance Initializer

This module handles the initialization of daily_attendance records for employees
scheduled for the current day when the kiosk starts up.

Features:
1. Creates daily_attendance records for all scheduled employees at startup
2. Marks previous day's incomplete records as 'absent'
3. Ensures records exist before employees time in/out
"""

import sys
import os
from datetime import datetime, timedelta

# Fix Windows console encoding
if sys.platform == 'win32':
    try:
        import codecs
        sys.stdout = codecs.getwriter('utf-8')(sys.stdout.buffer, 'strict')
        sys.stderr = codecs.getwriter('utf-8')(sys.stderr.buffer, 'strict')
    except Exception:
        pass

# Add the database directory to path
DB_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "database")
sys.path.insert(0, DB_DIR)

from init_local_db import get_db_connection

def get_mysql_connection():
    """
    Get connection to MySQL database for checking leave records.
    
    Returns:
        mysql.connector.connection or None
    """
    try:
        import mysql.connector
        
        # MySQL connection parameters (adjust as needed)
        conn = mysql.connector.connect(
            host='localhost',
            user='root',
            password='',
            database='employee_management'
        )
        return conn
    except Exception as e:
        print(f"  ⚠️  Could not connect to MySQL: {e}")
        return None

def is_on_approved_leave(employee_id, date_str, mysql_conn=None):
    """
    Check if employee has an approved leave for the given date.
    
    Args:
        employee_id (int): SQLite employee ID
        date_str (str): Date in YYYY-MM-DD format
        mysql_conn: MySQL connection (optional)
    
    Returns:
        tuple: (is_on_leave: bool, leave_type: str or None)
    """
    if mysql_conn is None:
        return False, None
    
    try:
        cursor = mysql_conn.cursor()
        
        # Query MySQL for approved leave on this date
        cursor.execute("""
            SELECT lt.type_name
            FROM employee_leaves el
            JOIN leave_types lt ON el.leave_type_id = lt.id
            WHERE el.employee_id = ?
              AND el.status = 'approved'
              AND el.start_date <= ?
              AND el.end_date >= ?
        """, (employee_id, date_str, date_str))
        
        result = cursor.fetchone()
        cursor.close()
        
        if result:
            return True, result[0]
        return False, None
        
    except Exception as e:
        print(f"    ⚠️  Error checking leave status: {e}")
        return False, None

def mark_previous_day_absences():
    """
    Mark all incomplete daily_attendance records from previous days as 'absent' or 'leave'.
    
    An incomplete record is one where:
    - time_in is NULL (employee never showed up)
    - status is not already 'absent' or 'leave'
    
    Before marking absent, checks MySQL for approved leaves.
    If employee has approved leave, marks as 'leave' instead of 'absent'.
    
    This should run at the start of each day to finalize previous day's attendance.
    """
    try:
        # Connect to SQLite (local attendance)
        sqlite_conn = get_db_connection()
        sqlite_cursor = sqlite_conn.cursor()
        
        # Connect to MySQL (leave management)
        mysql_conn = get_mysql_connection()
        
        # Get yesterday's date
        yesterday = (datetime.now() - timedelta(days=1)).strftime('%Y-%m-%d')
        today = datetime.now().strftime('%Y-%m-%d')
        
        print(f"  🔍 Checking for incomplete records before {today}...")
        
        # Find records where time_in is NULL and date is before today
        sqlite_cursor.execute("""
            SELECT da.id, da.employee_id, da.attendance_date, e.employee_id as employee_code, 
                   e.first_name, e.last_name
            FROM daily_attendance da
            JOIN employees e ON da.employee_id = e.id
            WHERE da.attendance_date < ?
              AND da.time_in IS NULL
              AND da.status != 'absent'
              AND da.status != 'leave'
        """, (today,))
        
        incomplete_records = sqlite_cursor.fetchall()
        incomplete_count = len(incomplete_records)
        
        if incomplete_count > 0:
            print(f"  📝 Found {incomplete_count} incomplete record(s) to process")
            
            absent_count = 0
            leave_count = 0
            
            # Process each incomplete record
            for record_id, emp_id, att_date, emp_code, first_name, last_name in incomplete_records:
                emp_name = f"{first_name} {last_name}"
                
                # Check if employee has approved leave for this date
                is_on_leave, leave_type = is_on_approved_leave(emp_id, att_date, mysql_conn)
                
                if is_on_leave:
                    # Mark as leave
                    sqlite_cursor.execute("""
                        UPDATE daily_attendance
                        SET status = 'leave',
                            notes = ?,
                            late_minutes = NULL,
                            early_departure_minutes = NULL,
                            overtime_minutes = NULL
                        WHERE id = ?
                    """, (f"On {leave_type} Leave", record_id))
                    print(f"    🏖️  {emp_name} ({emp_code}) - Marked as LEAVE ({leave_type}) on {att_date}")
                    leave_count += 1
                else:
                    # Mark as absent
                    sqlite_cursor.execute("""
                        UPDATE daily_attendance
                        SET status = 'absent',
                            late_minutes = NULL,
                            early_departure_minutes = NULL,
                            overtime_minutes = NULL
                        WHERE id = ?
                    """, (record_id,))
                    print(f"    ❌ {emp_name} ({emp_code}) - Marked as ABSENT on {att_date}")
                    absent_count += 1
            
            sqlite_conn.commit()
            print(f"  ✓ Processed {incomplete_count} record(s): {absent_count} absent, {leave_count} on leave")
        else:
            print(f"  ✓ No incomplete records found")
        
        # Close connections
        sqlite_conn.close()
        if mysql_conn:
            mysql_conn.close()
        
        return True
        
    except Exception as e:
        print(f"  ❌ Error marking absences: {e}")
        import traceback
        traceback.print_exc()
        return False

def initialize_daily_attendance_records():
    """
    Create daily_attendance records for all employees scheduled for today.
    Also creates records for employees on approved leave.
    
    This function:
    1. Gets the current day of week
    2. Finds all active employees with schedules for today
    3. Creates daily_attendance records with status='incomplete'
    4. Checks for employees on approved leave and creates records with status='leave'
    5. Skips employees who already have records for today
    
    Returns:
        tuple: (success: bool, created_count: int, skipped_count: int, leave_count: int)
    """
    try:
        # Connect to SQLite
        sqlite_conn = get_db_connection()
        sqlite_cursor = sqlite_conn.cursor()
        
        # Connect to MySQL for leave checking
        mysql_conn = get_mysql_connection()
        
        # Get current date and day of week
        now = datetime.now()
        today = now.strftime('%Y-%m-%d')
        day_of_week = now.weekday()  # 0=Monday, 6=Sunday
        
        print(f"  📅 Date: {now.strftime('%A, %B %d, %Y')} (day_of_week={day_of_week})")
        
        # Find all employees scheduled for today
        sqlite_cursor.execute("""
            SELECT DISTINCT e.id, e.employee_id, e.first_name, e.last_name
            FROM employees e
            INNER JOIN employee_schedules es ON e.id = es.employee_id
            INNER JOIN schedules s ON es.schedule_id = s.id
            INNER JOIN schedule_periods sp ON s.id = sp.schedule_id
            WHERE LOWER(e.status) = 'active'
              AND es.is_active = 1
              AND sp.is_active = 1
              AND sp.day_of_week = ?
              AND (es.end_date IS NULL OR es.end_date >= ?)
        """, (day_of_week, today))
        
        scheduled_employees = sqlite_cursor.fetchall()
        
        if not scheduled_employees:
            print(f"  ℹ️  No employees scheduled for today")
            sqlite_conn.close()
            if mysql_conn:
                mysql_conn.close()
            return True, 0, 0, 0
        
        print(f"  👥 Found {len(scheduled_employees)} employee(s) scheduled for today")
        
        created_count = 0
        skipped_count = 0
        leave_count = 0
        
        for emp_id, emp_code, first_name, last_name in scheduled_employees:
            emp_name = f"{first_name} {last_name}"
            
            # Check if record already exists for today
            sqlite_cursor.execute("""
                SELECT id FROM daily_attendance
                WHERE employee_id = ? AND attendance_date = ?
            """, (emp_id, today))
            
            existing_record = sqlite_cursor.fetchone()
            
            if existing_record:
                print(f"    ⏭️  {emp_name} ({emp_code}) - Record already exists")
                skipped_count += 1
                continue
            
            # Check if employee has approved leave for today
            is_on_leave, leave_type = is_on_approved_leave(emp_id, today, mysql_conn)
            
            if is_on_leave:
                # Create record with status='leave'
                sqlite_cursor.execute("""
                    INSERT INTO daily_attendance 
                    (employee_id, attendance_date, status, time_in, time_out, 
                     late_minutes, early_departure_minutes, overtime_minutes, notes)
                    VALUES (?, ?, 'leave', NULL, NULL, NULL, NULL, NULL, ?)
                """, (emp_id, today, f"On {leave_type} Leave"))
                
                print(f"    🏖️  {emp_name} ({emp_code}) - Leave record created ({leave_type})")
                leave_count += 1
            else:
                # Create normal incomplete record
                sqlite_cursor.execute("""
                    INSERT INTO daily_attendance 
                    (employee_id, attendance_date, status, time_in, time_out, 
                     late_minutes, early_departure_minutes, overtime_minutes)
                    VALUES (?, ?, 'incomplete', NULL, NULL, NULL, NULL, NULL)
                """, (emp_id, today))
                
                print(f"    ✓ {emp_name} ({emp_code}) - Record created")
                created_count += 1
        
        sqlite_conn.commit()
        sqlite_conn.close()
        
        if mysql_conn:
            mysql_conn.close()
        
        print(f"  ✅ Summary: {created_count} created, {skipped_count} skipped, {leave_count} on leave")
        return True, created_count, skipped_count, leave_count
        
    except Exception as e:
        print(f"  ❌ Error initializing daily attendance: {e}")
        import traceback
        traceback.print_exc()
        return False, 0, 0, 0

def run_daily_initialization():
    """
    Main function to run both initialization tasks:
    1. Mark previous day's incomplete records as absent or leave
    2. Initialize today's daily_attendance records (including leave records)
    
    Returns:
        bool: True if successful, False otherwise
    """
    print("\n" + "=" * 70)
    print("Daily Attendance Initialization")
    print("=" * 70)
    
    # Step 1: Mark previous absences
    print("\n[Step 1/2] Marking previous day absences and leaves...")
    mark_success = mark_previous_day_absences()
    
    # Step 2: Initialize today's records
    print("\n[Step 2/2] Initializing today's attendance records...")
    init_success, created, skipped, leave = initialize_daily_attendance_records()
    
    print("\n" + "=" * 70)
    
    return mark_success and init_success

# Test functionality
if __name__ == "__main__":
    print("Testing daily attendance initializer...")
    success = run_daily_initialization()
    
    if success:
        print("\n✅ Daily attendance initialization completed successfully")
    else:
        print("\n❌ Daily attendance initialization failed")
        sys.exit(1)
