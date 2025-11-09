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
    
    def log_attendance(self, employee_db_id, log_type=None, notes=None):
        """
        Log an attendance event for an employee.
        
        Args:
            employee_db_id (int): The database ID of the employee (from employees table)
            log_type (str, optional): Type of log ('time_in' or 'time_out'). 
                                     If None, will auto-determine based on last log.
            notes (str, optional): Additional notes for this log entry
        
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
            
            # Auto-determine log type if not provided
            if log_type is None:
                log_type = self._determine_log_type(employee_db_id, conn)
                print(f"  ℹ️  Auto-determined log type: {log_type}")
            
            # Validate log_type
            if log_type not in ['time_in', 'time_out']:
                error_msg = f'Invalid log_type: {log_type}. Must be "time_in" or "time_out".'
                log_error("log_attendance", error_msg, employee_code)
                return {
                    'success': False,
                    'message': error_msg
                }
            
            # Calculate status-based notes if not manually provided
            if notes is None:
                notes = self._calculate_attendance_status(employee_db_id, log_type, now, conn)
            
            print(f"  📝 Inserting attendance log: {log_type} at {log_time}")
            
            # Insert attendance log
            cursor.execute("""
                INSERT INTO attendance_logs 
                (employee_id, log_date, log_type, log_time, source, notes, synced)
                VALUES (?, ?, ?, ?, 'kiosk', ?, 0)
            """, (employee_db_id, log_date, log_type, log_time, notes))
            
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
        
        # If no log today, or last log was time_out, next should be time_in
        if last_log is None:
            return 'time_in'
        
        last_log_type = last_log[0]
        
        # Alternate between time_in and time_out
        if last_log_type == 'time_in':
            return 'time_out'
        else:
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
            
            # Get employee's active schedule for today
            cursor.execute("""
                SELECT sp.start_time, sp.end_time, sp.period_name
                FROM employee_schedules es
                JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
                WHERE es.employee_id = ?
                  AND es.is_active = 1
                  AND sp.day_of_week = ?
                  AND sp.is_active = 1
                  AND (es.end_date IS NULL OR es.end_date >= ?)
                ORDER BY es.effective_date DESC
                LIMIT 1
            """, (employee_db_id, day_of_week, log_datetime.strftime('%Y-%m-%d')))
            
            schedule = cursor.fetchone()
            
            if close_conn:
                conn.close()
            
            # If no schedule found, return generic message
            if schedule is None:
                if log_type == 'time_in':
                    return "Time In: No schedule assigned"
                else:
                    return "Time Out: No schedule assigned"
            
            start_time_str, end_time_str, period_name = schedule
            
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
                ORDER BY sp.start_time ASC
            """, (employee_db_id, day_of_week, log_date))
            
            schedule_periods = cursor.fetchall()
            
            if log_type == 'time_in':
                # Handle TIME IN
                late_minutes = 0
                
                if schedule_periods:
                    # Calculate late minutes based on first period start time
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
                
                if existing_record:
                    # Update existing record
                    print(f"     📝 Updating time_in for existing record...")
                    cursor.execute("""
                        UPDATE daily_attendance
                        SET time_in = ?, late_minutes = ?, calculated_at = ?
                        WHERE id = ?
                    """, (log_time_only, late_minutes, log_time_str, existing_record[0]))
                    print(f"     ✓ Updated existing record")
                else:
                    # Create new record
                    print(f"     📝 Creating new daily_attendance record...")
                    cursor.execute("""
                        INSERT INTO daily_attendance
                        (employee_id, attendance_date, time_in, late_minutes, status, calculated_at)
                        VALUES (?, ?, ?, ?, 'incomplete', ?)
                    """, (employee_db_id, log_date, log_time_only, late_minutes, log_time_str))
                    print(f"     ✓ Created new record")
                
                print(f"     📊 Daily attendance updated: time_in recorded, late_minutes={late_minutes}")
                log_daily_attendance_update(employee_code, employee_name, "time_in updated", 
                                          {"time_in": log_time_only, "late_minutes": late_minutes})
            
            elif log_type == 'time_out':
                # Handle TIME OUT
                print(f"     🕐 Processing TIME OUT...")
                
                if not existing_record:
                    # Create record if doesn't exist (user timed out without timing in)
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
                
                # Calculate scheduled_hours, actual_hours, overtime, undertime
                # NOTE: scheduled_hours and actual_hours are stored as MINUTES (not hours)
                scheduled_hours = 0  # This is actually minutes despite the field name
                actual_hours = 0     # This is actually minutes despite the field name
                early_departure_minutes = 0
                overtime_minutes = 0
                
                print(f"     🔢 Calculating hours and status...")
                print(f"     📅 Schedule periods found: {len(schedule_periods)}")
                
                if schedule_periods and time_in_str:
                    # Calculate scheduled_hours: from first period start to last period end
                    # NOTE: Result is stored in MINUTES (field name is misleading)
                    first_start = schedule_periods[0][0]
                    last_end = schedule_periods[-1][1]
                    
                    # Parse times
                    first_start_hour, first_start_minute, _ = map(int, first_start.split(':'))
                    last_end_hour, last_end_minute, _ = map(int, last_end.split(':'))
                    
                    first_start_minutes = first_start_hour * 60 + first_start_minute
                    last_end_minutes = last_end_hour * 60 + last_end_minute
                    
                    scheduled_hours = last_end_minutes - first_start_minutes  # Store as minutes
                    print(f"     ⏰ Scheduled hours (first to last period): {scheduled_hours} min ({scheduled_hours/60.0:.2f}h)")
                    
                    # Calculate actual_hours from schedule periods (sum of all periods)
                    total_period_minutes = 0
                    for period in schedule_periods:
                        start_time = period[0]
                        end_time = period[1]
                        
                        start_hour, start_minute, _ = map(int, start_time.split(':'))
                        end_hour, end_minute, _ = map(int, end_time.split(':'))
                        
                        period_minutes = (end_hour * 60 + end_minute) - (start_hour * 60 + start_minute)
                        total_period_minutes += period_minutes
                    
                    print(f"     ⏱️  Total period minutes (sum of all periods): {total_period_minutes}")
                    
                    # Calculate early departure or overtime
                    last_end_hour, last_end_minute, last_end_second = map(int, last_end.split(':'))
                    scheduled_end_datetime = log_datetime.replace(
                        hour=last_end_hour,
                        minute=last_end_minute,
                        second=last_end_second,
                        microsecond=0
                    )
                    
                    time_diff = (log_datetime - scheduled_end_datetime).total_seconds() / 60
                    
                    if time_diff < 0:
                        # Left early (undertime)
                        early_departure_minutes = int(abs(time_diff))
                        print(f"     ⚠️  Undertime detected: {early_departure_minutes} minutes")
                    else:
                        # Overtime
                        overtime_minutes = int(time_diff)
                        print(f"     ⏰ Overtime detected: {overtime_minutes} minutes")
                    
                    # Calculate actual_hours: total period minutes - late minutes - undertime minutes
                    # NOTE: Result is stored in MINUTES (field name is misleading)
                    # Formula: total_period_minutes - late_minutes - early_departure_minutes
                    actual_minutes = total_period_minutes - late_minutes - early_departure_minutes
                    
                    # Ensure actual_hours is not negative
                    if actual_minutes < 0:
                        actual_minutes = 0
                    
                    actual_hours = actual_minutes  # Store as minutes (not hours)
                    
                    print(f"     📊 Calculation: {total_period_minutes} min - {late_minutes} min (late) - {early_departure_minutes} min (undertime) = {actual_minutes} min ({actual_minutes/60.0:.2f}h)")
                
                # Determine status: complete if both time_in and time_out exist
                status = 'complete' if time_in_str and log_time_str else 'incomplete'
                print(f"     ✓ Status determined: {status}")
                
                # Update the record
                print(f"     📝 Updating daily_attendance record with time_out...")
                cursor.execute("""
                    UPDATE daily_attendance
                    SET time_out = ?,
                        scheduled_hours = ?,
                        actual_hours = ?,
                        early_departure_minutes = ?,
                        overtime_minutes = ?,
                        status = ?,
                        calculated_at = ?
                    WHERE id = ?
                """, (log_time_only, scheduled_hours, actual_hours, 
                      early_departure_minutes, overtime_minutes, status, 
                      log_time_str, existing_record[0]))
                
                print(f"     ✓ Update query executed")
                print(f"     📊 Daily attendance updated: time_out recorded")
                print(f"        Scheduled: {scheduled_hours} min ({scheduled_hours/60.0:.2f}h), Actual: {actual_hours} min ({actual_hours/60.0:.2f}h), Status: {status}")
                if early_departure_minutes > 0:
                    print(f"        Undertime: {early_departure_minutes} min")
                if overtime_minutes > 0:
                    print(f"        Overtime: {overtime_minutes} min")
                
                # Log to file
                log_daily_attendance_update(employee_code, employee_name, "time_out updated", {
                    "time_out": log_time_only,
                    "scheduled_hours": f"{scheduled_hours}min ({scheduled_hours/60.0:.2f}h)",
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
                       email, phone, department, position, status
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
                    'status': row[9]
                }
            return None
            
        except Exception as e:
            print(f"❌ Error fetching employee by ID: {e}")
            return None

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
