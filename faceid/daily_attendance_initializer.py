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

from init_local_db import get_db_connection # type: ignore

def mark_previous_day_absences():
    """
    Mark all incomplete daily_attendance records from previous days as 'absent'.
    
    An incomplete record is one where:
    - time_in is NULL (employee never showed up)
    - status is not already 'absent'
    
    This should run at the start of each day to finalize previous day's attendance.
    """
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # Get yesterday's date
        yesterday = (datetime.now() - timedelta(days=1)).strftime('%Y-%m-%d')
        today = datetime.now().strftime('%Y-%m-%d')
        
        print(f"  🔍 Checking for incomplete records before {today}...")
        
        # Find records where time_in is NULL and date is before today
        cursor.execute("""
            SELECT COUNT(*) 
            FROM daily_attendance
            WHERE attendance_date < ?
              AND time_in IS NULL
              AND status != 'absent'
        """, (today,))
        
        incomplete_count = cursor.fetchone()[0]
        
        if incomplete_count > 0:
            print(f"  📝 Found {incomplete_count} incomplete record(s) to mark as absent")
            
            # Update incomplete records to absent
            cursor.execute("""
                UPDATE daily_attendance
                SET status = 'absent',
                    late_minutes = NULL,
                    early_departure_minutes = NULL,
                    overtime_minutes = NULL
                WHERE attendance_date < ?
                  AND time_in IS NULL
                  AND status != 'absent'
            """, (today,))
            
            conn.commit()
            print(f"  ✓ Marked {cursor.rowcount} record(s) as absent")
        else:
            print(f"  ✓ No incomplete records found")
        
        conn.close()
        return True
        
    except Exception as e:
        print(f"  ❌ Error marking absences: {e}")
        import traceback
        traceback.print_exc()
        return False

def initialize_daily_attendance_records():
    """
    Create daily_attendance records for all employees scheduled for today.
    
    This function:
    1. Gets the current day of week
    2. Finds all active employees with schedules for today
    3. Creates daily_attendance records with status='incomplete'
    4. Skips employees who already have records for today
    
    Returns:
        tuple: (success: bool, created_count: int, skipped_count: int)
    """
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # Get current date and day of week
        now = datetime.now()
        today = now.strftime('%Y-%m-%d')
        day_of_week = now.weekday()  # 0=Monday, 6=Sunday
        
        print(f"  📅 Date: {now.strftime('%A, %B %d, %Y')} (day_of_week={day_of_week})")
        
        # Find all employees scheduled for today, either by normal schedule or an approved offset
        cursor.execute("""
            SELECT DISTINCT e.id, e.employee_id, e.first_name, e.last_name
            FROM employees e
            LEFT JOIN employee_schedules es ON e.id = es.employee_id AND es.is_active = 1 AND (es.end_date IS NULL OR es.end_date >= ?)
            LEFT JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id AND sp.is_active = 1 AND sp.day_of_week = ?
            LEFT JOIN offset_schedule_requests osr ON e.id = osr.employee_id AND osr.requested_date = ? AND osr.status IN ('approved', 'completed')
            WHERE LOWER(e.status) = 'active'
              AND (sp.id IS NOT NULL OR osr.id IS NOT NULL)
        """, (today, day_of_week, today))
        
        scheduled_employees = cursor.fetchall()
        
        if not scheduled_employees:
            print(f"  ℹ️  No employees scheduled for today")
            conn.close()
            return True, 0, 0
        
        print(f"  👥 Found {len(scheduled_employees)} employee(s) scheduled for today")
        
        created_count = 0
        skipped_count = 0
        
        for emp_id, emp_code, first_name, last_name in scheduled_employees:
            emp_name = f"{first_name} {last_name}"
            
            # Check if record already exists for today
            cursor.execute("""
                SELECT id FROM daily_attendance
                WHERE employee_id = ? AND attendance_date = ?
            """, (emp_id, today))
            
            existing_record = cursor.fetchone()
            
            if existing_record:
                print(f"    ⏭️  {emp_name} ({emp_code}) - Record already exists")
                skipped_count += 1
                continue
            
            # Get scheduled hours. First check if there's an offset.
            cursor.execute("""
                SELECT original_schedule_id, original_day_of_week, start_time, end_time
                FROM offset_schedule_requests
                WHERE employee_id = ? AND requested_date = ? AND status IN ('approved', 'completed')
                ORDER BY id DESC LIMIT 1
            """, (emp_id, today))
            
            offset_req = cursor.fetchone()
            
            scheduled_hours = 0
            
            if offset_req:
                orig_sched_id = offset_req[0]
                orig_dow = offset_req[1]
                custom_start = offset_req[2]
                custom_end = offset_req[3]
                
                if custom_start and custom_end and custom_start != 'None' and custom_end != 'None':
                    # Custom time-based offset
                    sh, sm, _ = map(int, str(custom_start).split(':'))
                    eh, em, _ = map(int, str(custom_end).split(':'))
                    scheduled_hours = (eh * 60 + em) - (sh * 60 + sm)
                elif orig_sched_id is not None and orig_dow is not None:
                    # Based on mirrored schedule
                    cursor.execute("""
                        SELECT start_time, end_time
                        FROM schedule_periods
                        WHERE schedule_id = ? AND day_of_week = ? AND is_active = 1
                        ORDER BY CAST(start_time AS TIME) ASC
                    """, (orig_sched_id, orig_dow))
                    
                    for period in cursor.fetchall():
                        sh, sm, _ = map(int, str(period[0]).split(':'))
                        eh, em, _ = map(int, str(period[1]).split(':'))
                        scheduled_hours += (eh * 60 + em) - (sh * 60 + sm)
            else:
                # Normal Schedule
                cursor.execute("""
                    SELECT sp.start_time, sp.end_time
                    FROM employee_schedules es
                    JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
                    WHERE es.employee_id = ?
                      AND es.is_active = 1
                      AND sp.day_of_week = ?
                      AND sp.is_active = 1
                      AND (es.end_date IS NULL OR es.end_date >= ?)
                    ORDER BY CAST(sp.start_time AS TIME) ASC
                """, (emp_id, day_of_week, today))
                
                for period in cursor.fetchall():
                    sh, sm, _ = map(int, str(period[0]).split(':'))
                    eh, em, _ = map(int, str(period[1]).split(':'))
                    scheduled_hours += (eh * 60 + em) - (sh * 60 + sm)
            
            # Create new daily_attendance record with scheduled_hours
            cursor.execute("""
                INSERT INTO daily_attendance 
                (employee_id, attendance_date, status, scheduled_hours, time_in, time_out, 
                 late_minutes, early_departure_minutes, overtime_minutes)
                VALUES (?, ?, 'incomplete', ?, NULL, NULL, NULL, NULL, NULL)
            """, (emp_id, today, scheduled_hours))
            
            print(f"    ✓ {emp_name} ({emp_code}) - Record created (scheduled: {scheduled_hours} min / {scheduled_hours/60.0:.2f}h)")
            created_count += 1
        
        conn.commit()
        conn.close()
        
        print(f"  ✅ Summary: {created_count} created, {skipped_count} skipped")
        return True, created_count, skipped_count
        
    except Exception as e:
        print(f"  ❌ Error initializing daily attendance: {e}")
        import traceback
        traceback.print_exc()
        return False, 0, 0

def run_daily_initialization():
    """
    Main function to run both initialization tasks:
    1. Mark previous day's incomplete records as absent
    2. Initialize today's daily_attendance records
    
    Returns:
        bool: True if successful, False otherwise
    """
    print("\n" + "=" * 70)
    print("Daily Attendance Initialization")
    print("=" * 70)
    
    # Step 1: Mark previous absences
    print("\n[Step 1/2] Marking previous day absences...")
    mark_success = mark_previous_day_absences()
    
    # Step 2: Initialize today's records
    print("\n[Step 2/2] Initializing today's attendance records...")
    init_success, created, skipped = initialize_daily_attendance_records()
    
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
