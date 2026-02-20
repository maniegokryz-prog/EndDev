"""
Attendance Logger Module

This module handles logging attendance events to the local SQLite database.
It provides functions to:
1. Log time in/time out events
2. Determine attendance log type based on recent history
3. Query attendance records
4. Handle attendance-related database operations

This module is used by the Kiosk face recognition system to record attendance.
"""

import sqlite3
import os
import sys
from datetime import datetime, timedelta

# Fix Windows console encoding for Unicode characters
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

from init_local_db import get_db_connection, DB_PATH

# Import logging utilities
from attendance_logging import (
    log_attendance_event, 
    log_error, 
    log_daily_attendance_update
)

class AttendanceLogger:
    """
    Handles all attendance logging operations for the kiosk system.
    """
    
    def __init__(self):
        """Initialize the attendance logger."""
        # Ensure database exists
        if not os.path.exists(DB_PATH):
            print("Database not found. Initializing...")
            from init_local_db import create_database
            create_database()
    
    def log_attendance(self, employee_db_id, log_type=None, notes=None, source='webcam', cooldown_minutes=0, min_work_minutes=0, one_session_per_day=False):
        """
        Log an attendance event for an employee.
        
        Args:
            employee_db_id (int): The database ID of the employee (from employees table)
            log_type (str, optional): Type of log ('time_in' or 'time_out'). 
                                     If None, will auto-determine based on last log.
            notes (str, optional): Additional notes for this log entry
            source (str, optional): Source of the log ('webcam', 'manual login', 'kiosk'). 
                                   Defaults to 'webcam'.
            cooldown_minutes (int): Min minutes between any scans.
            min_work_minutes (int): Min minutes before allowing Time Out (prevents early out).
            one_session_per_day (bool): If True, blocks any new logs after a Time Out.
        
        Returns:
            dict: Result containing success status, log_id, log_type, and message
        """
        conn = None
        try:
            # Get employee info for logging
            emp_info = self.get_employee_by_db_id(employee_db_id)
            if not emp_info:
                error_msg = f"Employee with DB ID {employee_db_id} not found"
                log_error("log_attendance", error_msg, employee_db_id)
                return {
                    'success': False,
                    'message': error_msg
                }
            
            employee_code = emp_info['employee_id']
            employee_name = emp_info['full_name']
            
            conn = get_db_connection()
            cursor = conn.cursor()
            
            # Get current datetime
            now = datetime.now()
            log_date = now.strftime('%Y-%m-%d')
            log_time = now.strftime('%Y-%m-%d %H:%M:%S')

            # --- Cooldown Check ---
            if cooldown_minutes > 0:
                # Check the most recent log for this employee today
                cursor.execute("""
                    SELECT log_type, log_time
                    FROM attendance_logs
                    WHERE employee_id = ? AND log_date = ?
                    ORDER BY log_time DESC
                    LIMIT 1
                """, (employee_db_id, log_date))
                
                last_log = cursor.fetchone()
                
                if last_log:
                    last_type = last_log[0]
                    last_time_str = last_log[1]
                    try:
                        last_time = datetime.strptime(last_time_str, '%Y-%m-%d %H:%M:%S')
                        time_diff = (now - last_time).total_seconds() / 60.0
                        
                        if time_diff < cooldown_minutes:
                            print(f"  ⏳ Cooldown active for {employee_name} (Last: {last_time_str}, Diff: {time_diff:.1f}m)")
                            conn.close()
                            
                            # Return special status
                            msg = ""
                            if last_type == 'time_in':
                                msg = f"Already Time In at {last_time.strftime('%I:%M %p')}"
                            elif last_type == 'time_out':
                                msg = f"Already Time Out at {last_time.strftime('%I:%M %p')}"
                            else:
                                msg = f"Already logged ({last_type}) recently"
                                
                            return {
                                'success': False,
                                'status': 'cooldown',
                                'log_type': last_type,
                                'message': msg
                            }
                    except ValueError:
                        pass # Error parsing time, ignore cooldown

            # --- Single Session & Min Duration Check ---
            # Determine effective log type (if not provided) to perform checks
            effective_log_type = log_type
            if effective_log_type is None:
                # Basic check: if no logs -> time_in, if last was time_in -> time_out
                # We need to peek at last log (re-using last_log from cooldown or fetching it)
                if 'last_log' not in locals() or last_log is None:
                    cursor.execute("SELECT log_type, log_time FROM attendance_logs WHERE employee_id = ? AND log_date = ? ORDER BY log_time DESC LIMIT 1", (employee_db_id, log_date))
                    last_log = cursor.fetchone()
                
                if not last_log:
                    effective_log_type = 'time_in'
                else:
                    effective_log_type = 'time_out' if last_log[0] == 'time_in' else 'time_in'

            # A. Check Single Session (If strictly one session per day)
            if one_session_per_day and effective_log_type == 'time_in':
                # If we are trying to Time In, check if we ALREADY completed a session (i.e. have a time_out)
                cursor.execute("""
                    SELECT 1 FROM daily_attendance 
                    WHERE employee_id = ? AND attendance_date = ? AND time_out IS NOT NULL
                """, (employee_db_id, log_date))
                if cursor.fetchone():
                    print(f"  🛑 Single Session blocked for {employee_name} (Already completed today)")
                    conn.close()
                    return {
                        'success': False,
                        'status': 'completed',
                        'message': "Attendance Completed"
                    }

            # B. Check Min Work Duration (Prevent accidental early Time Out)
            if min_work_minutes > 0 and effective_log_type == 'time_out':
                # We are trying to Time Out. Ensure enough time passed since Time In.
                # Find the Time In for this session
                cursor.execute("SELECT log_time FROM attendance_logs WHERE employee_id = ? AND log_date = ? AND log_type = 'time_in' ORDER BY log_time DESC LIMIT 1", (employee_db_id, log_date))
                time_in_row = cursor.fetchone()
                
                if time_in_row:
                    try:
                        t_in = datetime.strptime(time_in_row[0], '%Y-%m-%d %H:%M:%S')
                        duration_mins = (now - t_in).total_seconds() / 60.0
                        if duration_mins < min_work_minutes:
                            remaining = int(min_work_minutes - duration_mins)
                            print(f"  🛑 Min Duration blocked for {employee_name} (Worked: {duration_mins:.1f}m, Min: {min_work_minutes}m)")
                            conn.close()
                            return {
                                'success': False,
                                'status': 'too_early',
                                'message': f"Too Early: Wait {remaining} min"
                            }
                    except ValueError: pass

            
            # Auto-determine log type if not provided
            if log_type is None:
                log_type = self._determine_log_type(employee_db_id, conn)
                print(f"  ℹ️  Auto-determined log type: {log_type}")
            
            # Validate log_type
            if log_type not in ['time_in', 'time_out', 'visit']:
                error_msg = f'Invalid log_type: {log_type}. Must be "time_in", "time_out", or "visit".'
                log_error("log_attendance", error_msg, employee_code)
                return {
                    'success': False,
                    'message': error_msg
                }
            
            # Calculate status-based notes if not manually provided
            if notes is None:
                if log_type == 'visit':
                    notes = "Visit"
                else:
                    notes = self._calculate_attendance_status(employee_db_id, log_type, now, conn)
            
            print(f"  📝 Inserting attendance log: {log_type} at {log_time} (Source: {source})")
            
            # Insert attendance log
            cursor.execute("""
                INSERT INTO attendance_logs 
                (employee_id, log_date, log_type, log_time, source, notes, synced)
                VALUES (?, ?, ?, ?, ?, ?, 0)
            """, (employee_db_id, log_date, log_type, log_time, source, notes))
            
            log_id = cursor.lastrowid
            print(f"  ✓ Attendance log inserted with ID: {log_id}")
            
            # Update or create daily_attendance record
            print(f"  📊 Updating daily_attendance table...")
            self._update_daily_attendance(employee_db_id, log_type, now, conn)
            print(f"  ✓ Daily attendance updated")
            
            # Commit all changes
            print(f"  💾 Committing transaction...")
            conn.commit()
            print(f"  ✓ Transaction committed successfully")
            
            conn.close()
            
            print(f"✅ Attendance logged: Employee ID={employee_db_id}, Type={log_type}, Time={log_time}")
            if notes:
                print(f"   Status: {notes}")
            
            # Log to file
            log_attendance_event(employee_code, employee_name, log_type, notes)
            
            return {
                'success': True,
                'log_id': log_id,
                'log_type': log_type,
                'log_time': log_time,
                'notes': notes,
                'message': f'{log_type.replace("_", " ").title()} recorded successfully'
            }
            
        except sqlite3.Error as e:
            if conn:
                conn.rollback()
                conn.close()
            print(f"❌ Database error while logging attendance: {e}")
            log_error("log_attendance", f"SQLite error: {str(e)}", 
                     emp_info['employee_id'] if emp_info else str(employee_db_id), e)
            import traceback
            traceback.print_exc()
            return {
                'success': False,
                'message': f'Database error: {str(e)}'
            }
        except Exception as e:
            if conn:
                conn.rollback()
                conn.close()
            print(f"❌ Unexpected error while logging attendance: {e}")
            log_error("log_attendance", f"Unexpected error: {str(e)}", 
                     emp_info['employee_id'] if emp_info else str(employee_db_id), e)
            import traceback
            traceback.print_exc()
            return {
                'success': False,
                'message': f'Error: {str(e)}'
            }
    
    def _determine_log_type(self, employee_db_id, conn=None):
        """
        Automatically determine if this should be a time_in or time_out log.
        
        Logic:
        1. Check the most recent log for today
        2. If no log today, or last log was time_out -> return 'time_in'
        3. If last log was time_in -> return 'time_out'
        
        Args:
            employee_db_id (int): The database ID of the employee
            conn (sqlite3.Connection, optional): Existing database connection
        
        Returns:
            str: 'time_in' or 'time_out'
        """
        close_conn = False
        if conn is None:
            conn = get_db_connection()
            close_conn = True
        
        cursor = conn.cursor()
        
        # Get today's date
        today = datetime.now().strftime('%Y-%m-%d')
        
        if close_conn:
            # Don't close yet, we need it for subsequent queries
            pass

        # 1. Check daily_attendance FIRST (Synced from server/manual entry)
        # This is the Source of Truth for "Current Session Status"
        cursor.execute("""
            SELECT time_in, time_out, status
            FROM daily_attendance 
            WHERE employee_id = ? AND attendance_date = ?
        """, (employee_db_id, today))
        
        daily_record = cursor.fetchone()
        print(f"DEBUG: _determine_log_type daily_record: {daily_record}")

        if daily_record:
            d_time_in = daily_record[0]
            d_time_out = daily_record[1]
            d_status = daily_record[2]
            
            # Check for generic "empty" values for Time Out
            # Including '00:00', '00:00:00' which might come from some SQL defaults
            is_out_empty = (not d_time_out or 
                           str(d_time_out).strip() == '' or 
                           str(d_time_out).strip() == 'None' or
                           str(d_time_out).strip() == '00:00' or 
                           str(d_time_out).strip() == '00:00:00')

            # If we have Time In but NO Time Out (or status is incomplete) -> Must Time Out
            # FIX: Ensure d_time_in exists. Initializer creates 'incomplete' records with NULL time_in.
            if d_time_in and (is_out_empty or d_status == 'incomplete'):
                if close_conn: conn.close()
                return 'time_out'
                
            # If we have Time In AND Time Out -> Session Completed
            # If explicit 'complete' status -> Session Completed
            if (d_time_in and not is_out_empty) or d_status == 'complete':
                 # Enforce one session per day check here if needed, 
                 # but usually we default to 'time_in' (new session) or blocked.
                 # If one_session_per_day is enforced elsewhere, we just return 'time_in' 
                 # and let the caller handle "Already Completed".
                 pass
                
            # If we have Time In AND Time Out -> Session Completed
            # If one_session_per_day logic is handled in log_attendance, we just default to time_in 
            # (or let log_attendance block it)
            if d_time_in and d_time_out:
                # Session closed. Next start is Time In.
                pass 

        # 2. If no incomplete daily record, check local logs to toggle state
        # Get the most recent log for this employee today
        cursor.execute("""
            SELECT log_type, log_time
            FROM attendance_logs
            WHERE employee_id = ? AND log_date = ?
            ORDER BY log_time DESC
            LIMIT 1
        """, (employee_db_id, today))
        
        last_log = cursor.fetchone()
        
        if close_conn:
            conn.close()
        
        # If last log found locally, use it to determine next step
        if last_log:
            last_log_type = last_log[0]
            if last_log_type == 'time_in':
                return 'time_out'
            else:
                return 'time_in'
        
        # Default to time_in
        return 'time_in'
    
    def _calculate_attendance_status(self, employee_db_id, log_type, log_datetime, conn=None):
        """
        Calculate if the employee is late/on-time or overtime/undertime based on their schedule.
        
        Args:
            employee_db_id (int): The database ID of the employee
            log_type (str): 'time_in' or 'time_out'
            log_datetime (datetime): The datetime of the log
            conn (sqlite3.Connection, optional): Existing database connection
        
        Returns:
            str: Status message like "Time In: On-time" or "Time Out: Overtime by 30 minutes"
        """
        close_conn = False
        if conn is None:
            conn = get_db_connection()
            close_conn = True
        
        try:
            cursor = conn.cursor()
            
            # Get current day of week (0=Monday, 6=Sunday)
            day_of_week = log_datetime.weekday()
            
            # Get employee's active schedule for today (all periods for calculation purposes)
            cursor.execute("""
                SELECT sp.start_time, sp.end_time, sp.period_name
                FROM employee_schedules es
                JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
                WHERE es.employee_id = ?
                  AND es.is_active = 1
                  AND sp.day_of_week = ?
                  AND sp.is_active = 1
                  AND (es.end_date IS NULL OR es.end_date >= ?)
                ORDER BY CAST(sp.start_time AS TIME) ASC
            """, (employee_db_id, day_of_week, log_datetime.strftime('%Y-%m-%d')))
            
            schedule_periods = cursor.fetchall()
            
            if close_conn:
                conn.close()
            
            # If no schedule found, return generic message
            if not schedule_periods:
                if log_type == 'time_in':
                    return "Time In: No schedule assigned"
                else:
                    return "Time Out: No schedule assigned"
            
            # For time_in: use FIRST period start time (earliest start of the day)
            # For time_out: use LAST period end time (latest end of the day)
            if log_type == 'time_in':
                start_time_str, end_time_str, period_name = schedule_periods[0]
            else:
                start_time_str, end_time_str, period_name = schedule_periods[-1]
            
            # Parse schedule times (format: HH:MM:SS)
            scheduled_time_str = start_time_str if log_type == 'time_in' else end_time_str
            
            try:
                # Parse scheduled time
                scheduled_hour, scheduled_minute, scheduled_second = map(int, scheduled_time_str.split(':'))
                scheduled_datetime = log_datetime.replace(
                    hour=scheduled_hour, 
                    minute=scheduled_minute, 
                    second=scheduled_second,
                    microsecond=0
                )
                
                # Calculate difference in minutes
                time_diff = (log_datetime - scheduled_datetime).total_seconds() / 60
                
                if log_type == 'time_in':
                    # For time_in: positive diff = late, negative/zero = on-time
                    if time_diff <= 0:
                        return "Time In: On-time"
                    else:
                        minutes_late = int(time_diff)
                        # Send late notification
                        self._send_late_notification(employee_db_id, minutes_late)
                        return f"Time In: Late by {minutes_late} minute{'s' if minutes_late != 1 else ''}"
                else:  # time_out
                    # For time_out: positive diff = overtime, negative = undertime
                    if time_diff >= 0:
                        minutes_overtime = int(time_diff)
                        if minutes_overtime == 0:
                            return "Time Out: On-time"
                        return f"Time Out: Overtime by {minutes_overtime} minute{'s' if minutes_overtime != 1 else ''}"
                    else:
                        minutes_undertime = int(abs(time_diff))
                        return f"Time Out: Undertime by {minutes_undertime} minute{'s' if minutes_undertime != 1 else ''}"
                        
            except (ValueError, AttributeError) as e:
                print(f"⚠ Error parsing schedule time '{scheduled_time_str}': {e}")
                if log_type == 'time_in':
                    return "Time In: Schedule time error"
                else:
                    return "Time Out: Schedule time error"
                    
        except Exception as e:
            print(f"⚠ Error calculating attendance status: {e}")
            if log_type == 'time_in':
                return "Time In: Status calculation error"
            else:
                return "Time Out: Status calculation error"
    
    def _update_daily_attendance(self, employee_db_id, log_type, log_datetime, conn):
        """
        Update or create daily_attendance record when user times in/out.
        
        Args:
            employee_db_id (int): The database ID of the employee
            log_type (str): 'time_in' or 'time_out'
            log_datetime (datetime): The datetime of the log
            conn (sqlite3.Connection): Existing database connection
        """
        try:
            # Get employee info for logging
            emp_info = self.get_employee_by_db_id(employee_db_id)
            employee_code = emp_info['employee_id'] if emp_info else str(employee_db_id)
            employee_name = emp_info['full_name'] if emp_info else "Unknown"
            
            cursor = conn.cursor()
            log_date = log_datetime.strftime('%Y-%m-%d')
            log_time_str = log_datetime.strftime('%Y-%m-%d %H:%M:%S')
            log_time_only = log_datetime.strftime('%H:%M:%S')  # Only time for daily_attendance
            day_of_week = log_datetime.weekday()
            
            print(f"     🔍 Checking for existing daily_attendance record...")
            
            # Check if daily_attendance record exists
            cursor.execute("""
                SELECT id, time_in, time_out, late_minutes FROM daily_attendance
                WHERE employee_id = ? AND attendance_date = ?
            """, (employee_db_id, log_date))
            
            existing_record = cursor.fetchone()
            
            if existing_record:
                print(f"     ✓ Found existing record (ID: {existing_record[0]})")
            else:
                print(f"     ℹ️  No existing record found, will create new")
            
            # Get employee's schedule for today
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
            """, (employee_db_id, day_of_week, log_date))
            
            schedule_periods = cursor.fetchall()

            if log_type == 'visit':
                # Handle VISIT
                if not existing_record:
                    # New visit - Time In (Start Time)
                    print(f"     📝 Creating new daily_attendance record for VISIT...")
                    cursor.execute("""
                        INSERT INTO daily_attendance
                        (employee_id, attendance_date, time_in, status, calculated_at)
                        VALUES (?, ?, ?, 'visit', ?)
                    """, (employee_db_id, log_date, log_time_only, log_time_str))
                    
                    print(f"     ✓ Created new visit record")
                    log_daily_attendance_update(employee_code, employee_name, "visit started", 
                                              {"time_in": log_time_only})
                else:
                    # Existing visit - Update Time Out (End Time)
                    print(f"     📝 Updating daily_attendance record for VISIT (Time Out)...")
                    
                    # Calculate duration
                    time_in_str = existing_record[1]
                    actual_minutes = 0
                    if time_in_str:
                         try:
                             time_in_parts = list(map(int, time_in_str.split(':')))
                             time_in_dt = log_datetime.replace(hour=time_in_parts[0], minute=time_in_parts[1], second=time_in_parts[2] if len(time_in_parts)>2 else 0, microsecond=0)
                             actual_minutes = int((log_datetime - time_in_dt).total_seconds() / 60)
                         except: pass

                    cursor.execute("""
                        UPDATE daily_attendance
                        SET time_out = ?, actual_hours = ?, status = 'visit', calculated_at = ?
                        WHERE id = ?
                    """, (log_time_only, actual_minutes, log_time_str, existing_record[0]))
                    
                    print(f"     ✓ Updated visit record (Duration: {actual_minutes} min)")
                    log_daily_attendance_update(employee_code, employee_name, "visit update", 
                                              {"time_out": log_time_only, "duration": f"{actual_minutes}min"})
                
                return

            if log_type == 'time_in':
                print(f"     🕐 Processing TIME IN...")
                
                # SAFEGUARD: Check if existing record already has a valid time_in
                if existing_record and existing_record[1]: 
                     print(f"     ⚠️  Warning: Attempting to overwrite Time In.")
                     return
                
                late_minutes = 0
                if schedule_periods:
                    # Calculate late minutes based on FIRST period start time
                    first_period_start = schedule_periods[0][0]
                    scheduled_hour, scheduled_minute, scheduled_second = map(int, first_period_start.split(':'))
                    scheduled_datetime = log_datetime.replace(
                        hour=scheduled_hour, 
                        minute=scheduled_minute, 
                        second=scheduled_second,
                        microsecond=0
                    )
                    
                    time_diff = (log_datetime - scheduled_datetime).total_seconds() / 60
                    if time_diff > 0:
                        late_minutes = int(time_diff)
                    
                    print(f"     🎯 First period start: {first_period_start}, Late: {late_minutes} min")

                if existing_record:
                    # Update existing record (scheduled_hours already set by initializer)
                    print(f"     📝 Updating time_in for existing record...")
                    cursor.execute("""
                        UPDATE daily_attendance
                        SET time_in = ?, late_minutes = ?, calculated_at = ?
                        WHERE id = ?
                    """, (log_time_only, late_minutes, log_time_str, existing_record[0]))
                    print(f"     ✓ Updated existing record")
                else:
                    # Create new record
                    scheduled_hours = 0
                    if schedule_periods:
                        print(f"     🔢 Calculating scheduled hours (fallback)...")
                        for period in schedule_periods:
                            start_time = period[0]
                            end_time = period[1]
                            
                            start_hour, start_minute, _ = map(int, start_time.split(':'))
                            end_hour, end_minute, _ = map(int, end_time.split(':'))
                            
                            period_minutes = (end_hour * 60 + end_minute) - (start_hour * 60 + start_minute)
                            scheduled_hours += period_minutes
                        
                        print(f"     ⏰ Scheduled hours: {scheduled_hours} min ({scheduled_hours/60.0:.2f}h)")
                    
                    print(f"     📝 Creating new daily_attendance record...")
                    cursor.execute("""
                        INSERT INTO daily_attendance
                        (employee_id, attendance_date, time_in, late_minutes, scheduled_hours, status, calculated_at)
                        VALUES (?, ?, ?, ?, ?, 'incomplete', ?)
                    """, (employee_db_id, log_date, log_time_only, late_minutes, scheduled_hours, log_time_str))
                    print(f"     ✓ Created new record with scheduled_hours={scheduled_hours} min")
                
                print(f"     📊 Daily attendance updated: time_in recorded, late_minutes={late_minutes}")
                log_daily_attendance_update(employee_code, employee_name, "time_in updated", 
                                          {"time_in": log_time_only, "late_minutes": late_minutes})
            
            elif log_type == 'time_out':
                # Handle TIME OUT
                print(f"DEBUG: Handling TIME OUT for Existing ID: {existing_record[0] if existing_record else 'None'}")
                
                # Helper to calculate hours
                def calculate_hours(t_in, t_out):
                    try:
                        fmt = '%H:%M:%S'
                        tdelta = datetime.strptime(t_out, fmt) - datetime.strptime(t_in, fmt)
                        # changing to total seconds / 3600
                        return round(tdelta.total_seconds() / 3600, 2)
                    except:
                        return 0.0

                if not existing_record:
                    print("DEBUG: No existing record found for time_out. Checking if creating new one makes sense or error.")
                    # Create record if doesn't exist (user timed out without timing in)
                    # But wait, logic below says:
                    # We usually expect a time_in record. If not, it's a "Ghost Time Out" -> Status: Complete (but missing Time In?)
                    # Actually, if no existing record, we create one with time_out set.
                    
                    # Fetch schedule hours for today (simplified default 8h if not found)
                    # ... (existing logic) ...
                    print(f"     ⚠️  No time_in record found, creating time_out only record...")
                    cursor.execute("""
                        INSERT INTO daily_attendance
                        (employee_id, attendance_date, time_out, status, calculated_at)
                        VALUES (?, ?, ?, 'incomplete', ?)
                    """, (employee_db_id, log_date, log_time_only, log_time_str))
                    print(f"     📊 Daily attendance created: time_out recorded (no time_in)")
                    log_daily_attendance_update(employee_code, employee_name, "time_out only (no time_in)", 
                                              {"time_out": log_time_only, "status": "incomplete"})
                    return
                
                # Get the time_in for this record
                time_in_str = existing_record[1]
                late_minutes = existing_record[3] if existing_record[3] else 0
                print(f"     ℹ️  Found time_in: {time_in_str}")
                print(f"     ℹ️  Late minutes from time_in: {late_minutes}")
                
                # Calculate actual_hours, overtime, undertime
                # NOTE: scheduled_hours was already calculated during time_in
                # NOTE: actual_hours are stored as MINUTES (not hours)
                actual_hours = 0     # This is actually minutes despite the field name
                early_departure_minutes = 0
                overtime_minutes = 0
                
                print(f"     🔢 Calculating actual hours and status...")
                
                # Determine status: complete if both time_in and time_out exist
                status = 'complete' if time_in_str and log_time_str else 'incomplete'
                print(f"     ✓ Status determined: {status}")
                
                # Calculate actual_hours with schedule clamping
                actual_hours = 0
                if time_in_str:
                    try:
                        # Parse Log times
                        time_in_parts = list(map(int, time_in_str.split(':')))
                        t_in_dt = log_datetime.replace(
                            hour=time_in_parts[0], 
                            minute=time_in_parts[1], 
                            second=time_in_parts[2] if len(time_in_parts)>2 else 0, 
                            microsecond=0
                        )
                        t_out_dt = log_datetime  # Current time is Time Out

                        # Default calculation (Actual In to Actual Out)
                        calc_start_dt = t_in_dt
                        calc_end_dt = t_out_dt

                        # Apply Schedule Clamping if available
                        if schedule_periods:
                            # Parse Schedule Start (First Period)
                            sched_start_str = schedule_periods[0][0]
                            ss_h, ss_m, ss_s = map(int, sched_start_str.split(':'))
                            sched_start_dt = log_datetime.replace(hour=ss_h, minute=ss_m, second=ss_s, microsecond=0)

                            # Parse Schedule End (Last Period)
                            sched_end_str = schedule_periods[-1][1]
                            se_h, se_m, se_s = map(int, sched_end_str.split(':'))
                            sched_end_dt = log_datetime.replace(hour=se_h, minute=se_m, second=se_s, microsecond=0)

                            # CLAMP START / ROUND LATE START
                            if t_in_dt < sched_start_dt:
                                # Early In: Clamp to Schedule Start
                                print(f"     ✂️  Clipping Early In: {t_in_dt.strftime('%H:%M')} -> {sched_start_dt.strftime('%H:%M')}")
                                calc_start_dt = sched_start_dt
                            elif t_in_dt > sched_start_dt:
                                # Late In: Round UP to next full hour
                                # Logic: If 8:23 -> 9:00. If 8:00:01 -> 9:00.
                                # Use timedelta to get to next hour
                                delta_min = 60 - t_in_dt.minute
                                # If minute is 0 but second > 0, we still need to round up? 
                                # Let's assume strict next hour.
                                # If already exactly on hour (minute=0, second=0), no rounding needed? 
                                # But t_in_dt > sched_start_dt implies it IS late.
                                # If sched 8:00, in 9:00 -> Late 1h. Start at 9:00.
                                
                                # If we strictly add minutes to reach :00
                                current_min = t_in_dt.minute
                                current_sec = t_in_dt.second
                                
                                if current_min == 0 and current_sec == 0:
                                    # Exactly on an hour (but late). e.g. Sched 8:00, In 9:00.
                                    # Do not add another hour.
                                    calc_start_dt = t_in_dt
                                else:
                                    # Round up
                                    # We can just add 1 hour and zero out min/sec?
                                    # No, if 8:01 -> 9:00.
                                    next_hour_dt = (t_in_dt + timedelta(hours=1)).replace(minute=0, second=0, microsecond=0)
                                    print(f"     ✂️  Rounding Late In: {t_in_dt.strftime('%H:%M:%S')} -> {next_hour_dt.strftime('%H:%M:%S')}")
                                    calc_start_dt = next_hour_dt
                            
                            # CLAMP END: effectively ignores Overtime (Late Out)
                            if t_out_dt > sched_end_dt:
                                print(f"     ✂️  Clipping Late Out: {t_out_dt.strftime('%H:%M')} -> {sched_end_dt.strftime('%H:%M')}")
                                calc_end_dt = sched_end_dt
                        
                        # Calculate duration in minutes
                        diff_seconds = (calc_end_dt - calc_start_dt).total_seconds()
                        if diff_seconds < 0:
                            diff_seconds = 0
                        
                        actual_hours = round(diff_seconds / 60, 2)
                        print(f"     ⏱️  Actual hours (Clamped/Rounded): {actual_hours} min ({actual_hours/60.0:.2f}h)")

                        # Calculate Overtime / Undertime (unclamped vs schedule)
                        if schedule_periods:
                             # Use floating point diff for calculation
                             ot_diff = (t_out_dt - sched_end_dt).total_seconds() / 60.0
                             
                             if ot_diff > 0:
                                 overtime_minutes = int(ot_diff)
                                 print(f"     ⏰ Overtime detected: {overtime_minutes} minutes")
                             elif ot_diff < 0:
                                 early_departure_minutes = int(abs(ot_diff))
                                 print(f"     ⚠️  Undertime detected: {early_departure_minutes} minutes")
                        


                    except Exception as e:
                        print(f"     ❌ Error calculating actual_hours: {e}")
                        actual_hours = 0
                        import traceback
                        traceback.print_exc()
                
                # Update the record (scheduled_hours was already set during time_in)
                print(f"     📝 Updating daily_attendance record with time_out...")
                cursor.execute("""
                    UPDATE daily_attendance
                    SET time_out = ?,
                        actual_hours = ?,
                        early_departure_minutes = ?,
                        overtime_minutes = ?,
                        status = ?,
                        calculated_at = ?
                    WHERE id = ?
                """, (log_time_only, actual_hours, 
                      early_departure_minutes, overtime_minutes, status, 
                      log_time_str, existing_record[0]))
                
                print(f"     ✓ Update query executed")
                print(f"     📊 Daily attendance updated: time_out recorded")
                print(f"        Actual: {actual_hours} min ({actual_hours/60.0:.2f}h), Status: {status}")
                if early_departure_minutes > 0:
                    print(f"        Undertime: {early_departure_minutes} min")
                if overtime_minutes > 0:
                    print(f"        Overtime: {overtime_minutes} min")
                
                # Log to file
                log_daily_attendance_update(employee_code, employee_name, "time_out updated", {
                    "time_out": log_time_only,
                    "actual_hours": f"{actual_hours}min ({actual_hours/60.0:.2f}h)",
                    "status": status,
                    "early_departure": f"{early_departure_minutes}min" if early_departure_minutes > 0 else "0",
                    "overtime": f"{overtime_minutes}min" if overtime_minutes > 0 else "0"
                })
        
        except Exception as e:
            print(f"     ❌ Error updating daily_attendance: {e}")
            log_error("_update_daily_attendance", f"Error updating daily attendance: {str(e)}", 
                     employee_code, e)
            import traceback
            traceback.print_exc()
            raise  # Re-raise to trigger rollback in parent function
    
    def get_today_logs(self, employee_db_id):
        """
        Get all attendance logs for an employee today.
        
        Args:
            employee_db_id (int): The database ID of the employee
        
        Returns:
            list: List of log dictionaries with keys: id, log_type, log_time, notes
        """
        try:
            conn = get_db_connection()
            cursor = conn.cursor()
            
            today = datetime.now().strftime('%Y-%m-%d')
            
            cursor.execute("""
                SELECT id, log_type, log_time, notes, synced
                FROM attendance_logs
                WHERE employee_id = ? AND log_date = ?
                ORDER BY log_time ASC
            """, (employee_db_id, today))
            
            rows = cursor.fetchall()
            conn.close()
            
            logs = []
            for row in rows:
                logs.append({
                    'id': row[0],
                    'log_type': row[1],
                    'log_time': row[2],
                    'notes': row[3],
                    'synced': row[4]
                })
            
            return logs
            
        except Exception as e:
            print(f"❌ Error fetching today's logs: {e}")
            return []
    
    def get_last_log_time(self, employee_db_id):
        """
        Get the timestamp of the last attendance log for an employee.
        
        Args:
            employee_db_id (int): The database ID of the employee
        
        Returns:
            datetime or None: Datetime of last log, or None if no logs exist
        """
        try:
            conn = get_db_connection()
            cursor = conn.cursor()
            
            cursor.execute("""
                SELECT log_time
                FROM attendance_logs
                WHERE employee_id = ?
                ORDER BY log_time DESC
                LIMIT 1
            """, (employee_db_id,))
            
            row = cursor.fetchone()
            conn.close()
            
            if row:
                return datetime.strptime(row[0], '%Y-%m-%d %H:%M:%S')
            return None
            
        except Exception as e:
            print(f"❌ Error fetching last log time: {e}")
            return None
    
    def get_unsynced_logs(self, limit=100):
        """
        Get all attendance logs that haven't been synced to MySQL yet.
        
        Args:
            limit (int): Maximum number of logs to retrieve
        
        Returns:
            list: List of unsynced log dictionaries
        """
        try:
            conn = get_db_connection()
            cursor = conn.cursor()
            
            cursor.execute("""
                SELECT al.id, al.employee_id, e.employee_id as employee_code,
                       al.log_date, al.log_type, al.log_time, al.source, al.notes
                FROM attendance_logs al
                JOIN employees e ON al.employee_id = e.id
                WHERE al.synced = 0
                ORDER BY al.log_time ASC
                LIMIT ?
            """, (limit,))
            
            rows = cursor.fetchall()
            conn.close()
            
            logs = []
            for row in rows:
                logs.append({
                    'id': row[0],
                    'employee_id': row[1],
                    'employee_code': row[2],
                    'log_date': row[3],
                    'log_type': row[4],
                    'log_time': row[5],
                    'source': row[6],
                    'notes': row[7]
                })
            
            return logs
            
        except Exception as e:
            print(f"❌ Error fetching unsynced logs: {e}")
            return []
    
    def mark_log_synced(self, log_id, mysql_id=None):
        """
        Mark a log as synced to MySQL.
        
        Args:
            log_id (int): The ID of the log in the local database
            mysql_id (int, optional): The ID assigned by MySQL server
        
        Returns:
            bool: True if successful, False otherwise
        """
        try:
            conn = get_db_connection()
            cursor = conn.cursor()
            
            synced_at = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            
            cursor.execute("""
                UPDATE attendance_logs
                SET synced = 1, synced_at = ?, mysql_id = ?
                WHERE id = ?
            """, (synced_at, mysql_id, log_id))
            
            conn.commit()
            conn.close()
            
            return True
            
        except Exception as e:
            print(f"❌ Error marking log as synced: {e}")
            return False
    
    def get_employee_by_code(self, employee_code):
        """
        Get employee information by their employee code.
        
        Args:
            employee_code (str): The employee's unique code (e.g., "EMP001")
        
        Returns:
            dict or None: Employee data dictionary, or None if not found
        """
        try:
            conn = get_db_connection()
            cursor = conn.cursor()
            
            cursor.execute("""
                SELECT id, employee_id, first_name, middle_name, last_name,
                       email, phone, department, position, status
                FROM employees
                WHERE employee_id = ?
            """, (employee_code,))
            
            row = cursor.fetchone()
            conn.close()
            
            if row:
                return {
                    'db_id': row[0],
                    'employee_id': row[1],
                    'first_name': row[2],
                    'middle_name': row[3],
                    'last_name': row[4],
                    'full_name': f"{row[2]} {row[4]}",
                    'email': row[5],
                    'phone': row[6],
                    'department': row[7],
                    'position': row[8],
                    'status': row[9]
                }
            return None
            
        except Exception as e:
            print(f"❌ Error fetching employee by code: {e}")
            return None
    
    def get_employee_by_db_id(self, db_id):
        """
        Get employee information by their database ID.
        
        Args:
            db_id (int): The employee's database ID
        
        Returns:
            dict or None: Employee data dictionary, or None if not found
        """
        try:
            conn = get_db_connection()
            cursor = conn.cursor()
            
            cursor.execute("""
                SELECT id, employee_id, first_name, middle_name, last_name,
                       email, phone, department, position, status, roles
                FROM employees
                WHERE id = ?
            """, (db_id,))
            
            row = cursor.fetchone()
            conn.close()
            
            if row:
                return {
                    'db_id': row[0],
                    'employee_id': row[1],
                    'first_name': row[2],
                    'middle_name': row[3],
                    'last_name': row[4],
                    'full_name': f"{row[2]} {row[4]}",
                    'email': row[5],
                    'phone': row[6],
                    'department': row[7],
                    'position': row[8],
                    'status': row[9],
                    'role': row[10]
                }
            return None
            
        except Exception as e:
            print(f"❌ Error fetching employee by ID: {e}")
            return None
    
    def _send_late_notification(self, employee_db_id, minutes_late):
        """
        Send a notification to the employee when they are late.
        Connects to MySQL server to insert notification.
        
        Args:
            employee_db_id (int): The database ID of the employee (SQLite local ID)
            minutes_late (int): Number of minutes the employee is late
        """
        try:
            import mysql.connector
            
            # Get employee info from local SQLite
            emp_info = self.get_employee_by_db_id(employee_db_id)
            if not emp_info:
                print(f"⚠️  Cannot send late notification: Employee not found (DB ID: {employee_db_id})")
                return
            
            employee_code = emp_info['employee_id']
            employee_name = emp_info['full_name']
            
            # Connect to MySQL server
            mysql_conn = mysql.connector.connect(
                host='localhost',
                user='root',
                password='Confirmp@ssword123',
                database='database_records'
            )
            mysql_cursor = mysql_conn.cursor()
            
            # Get the MySQL employee ID (different from SQLite ID)
            mysql_cursor.execute("SELECT id FROM employees WHERE employee_id = %s", (employee_code,))
            result = mysql_cursor.fetchone()
            
            if not result:
                print(f"⚠️  Cannot send late notification: Employee not found in MySQL (Code: {employee_code})")
                mysql_conn.close()
                return
            
            mysql_employee_id = result[0]
            
            # Check if notifications table exists
            mysql_cursor.execute("SHOW TABLES LIKE 'notifications'")
            if mysql_cursor.fetchone() is None:
                print(f"⚠️  Cannot send late notification: notifications table doesn't exist")
                mysql_conn.close()
                return
            
            # Create late notification message
            if minutes_late >= 60:
                hours = minutes_late // 60
                mins = minutes_late % 60
                if mins > 0:
                    time_desc = f"{hours}h {mins}m"
                else:
                    time_desc = f"{hours}h"
            else:
                time_desc = f"{minutes_late}m"
            
            message = f"{employee_name}, You are late by {time_desc}"
            
            # Link to profile page
            link = "/EndDev/staffmanagement/staffinfo.php"
            
            # Insert notification
            mysql_cursor.execute("""
                INSERT INTO notifications (employee_id, type, message, link, target, is_read)
                VALUES (%s, 'late_attendance', %s, %s, 'employee', 0)
            """, (mysql_employee_id, message, link))
            
            mysql_conn.commit()
            mysql_conn.close()
            
            print(f"📬 Late notification sent: {employee_name} ({employee_code}) - Late by {time_desc}")
            log_attendance_event(employee_code, employee_name, "late_notification", f"Late by {time_desc}")
            
        except Exception as e:
            print(f"⚠️  Error sending late notification: {e}")
            log_error("send_late_notification", f"Failed to send notification: {str(e)}", 
                     emp_info['employee_id'] if emp_info else str(employee_db_id), e)

# Singleton instance for easy importing
_logger_instance = None

def get_logger():
    """
    Get the singleton attendance logger instance.
    
    Returns:
        AttendanceLogger: The attendance logger instance
    """
    global _logger_instance
    if _logger_instance is None:
        _logger_instance = AttendanceLogger()
    return _logger_instance

# Test functionality
if __name__ == "__main__":
    print("=" * 70)
    print("Attendance Logger Test")
    print("=" * 70)
    
    logger = get_logger()
    
    # Example: Get unsynced logs
    print("\nFetching unsynced logs...")
    unsynced = logger.get_unsynced_logs()
    print(f"Found {len(unsynced)} unsynced logs")
    for log in unsynced[:5]:  # Show first 5
        print(f"  - {log['employee_code']}: {log['log_type']} at {log['log_time']}")
    
    print("\n" + "=" * 70)
