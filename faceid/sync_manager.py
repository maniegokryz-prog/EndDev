"""
Sync Manager - Bidirectional Synchronization between SQLite and MySQL

This module handles:
1. PUSH: Sending attendance logs from SQLite to MySQL server
2. PULL: Fetching employee updates, schedules, and assignments from MySQL

The sync runs continuously with configurable intervals:
- Push: Every time there are unsynced records (immediate)
- Pull: Every 60 seconds to check for updates

Configuration:
- Edit MySQL connection settings in the CONFIG section below
- Adjust sync intervals as needed
"""

import sqlite3
import pymysql
import os
import sys
import time
import json
from datetime import datetime, timedelta
from threading import Thread, Event

# Fix Windows console encoding for Unicode characters
if sys.platform == 'win32':
    try:
        # Set console to UTF-8 mode
        import codecs
        sys.stdout = codecs.getwriter('utf-8')(sys.stdout.buffer, 'strict')
        sys.stderr = codecs.getwriter('utf-8')(sys.stderr.buffer, 'strict')
    except Exception:
        # If UTF-8 encoding fails, use ASCII-safe output
        pass

# Add the database directory to path
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DB_DIR = os.path.join(SCRIPT_DIR, "database")
sys.path.insert(0, DB_DIR)

from init_local_db import get_db_connection, DB_PATH

# ============================================================================
# CONFIGURATION
# ============================================================================

# MySQL Server Configuration
MYSQL_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': 'Confirmp@ssword123',
    'database': 'database_records',
    'charset': 'utf8mb4',
    'connect_timeout': 5
}

# Sync intervals (in seconds)
PULL_INTERVAL = 60  # Pull updates every 60 seconds
PUSH_INTERVAL = 5   # Check for unsynced logs every 5 seconds

# Retry configuration
MAX_RETRY_ATTEMPTS = 3
RETRY_DELAY = 5  # seconds between retries

class SyncManager:
    """
    Manages bidirectional synchronization between local SQLite and remote MySQL.
    """
    
    def __init__(self):
        """Initialize the sync manager."""
        self.stop_event = Event()
        self.mysql_available = False
        self.last_push_time = None
        self.last_pull_time = None
        self.push_thread = None
        self.pull_thread = None
        
        # Ensure local database exists
        if not os.path.exists(DB_PATH):
            print("⚠️  Local database not found. Initializing...")
            from init_local_db import create_database
            create_database()
    
    def test_mysql_connection(self):
        """
        Test if MySQL server is accessible.
        
        Returns:
            bool: True if connection successful, False otherwise
        """
        try:
            conn = pymysql.connect(**MYSQL_CONFIG)
            conn.close()
            self.mysql_available = True
            return True
        except Exception as e:
            self.mysql_available = False
            print(f"⚠️  MySQL connection failed: {e}")
            return False
    
    # ========================================================================
    # PUSH: Send attendance logs from SQLite to MySQL
    # ========================================================================
    
    def push_attendance_logs(self):
        """
        Push all unsynced attendance logs to MySQL server.
        
        Returns:
            dict: Result with counts of successful and failed syncs
        """
        try:
            # Get unsynced logs from SQLite
            local_conn = get_db_connection()
            local_cursor = local_conn.cursor()
            
            local_cursor.execute("""
                SELECT al.id, al.employee_id, al.log_date, al.log_type, 
                       al.log_time, al.source, al.notes
                FROM attendance_logs al
                WHERE al.synced = 0
                ORDER BY al.log_time ASC
            """)
            
            unsynced_logs = local_cursor.fetchall()
            
            if not unsynced_logs:
                local_conn.close()
                return {'success': 0, 'failed': 0, 'message': 'No logs to sync'}
            
            print(f"\n📤 Pushing {len(unsynced_logs)} attendance logs to MySQL...")
            
            # Connect to MySQL
            mysql_conn = pymysql.connect(**MYSQL_CONFIG)
            mysql_cursor = mysql_conn.cursor()
            
            success_count = 0
            failed_count = 0
            
            for log in unsynced_logs:
                local_id, employee_id, log_date, log_type, log_time, source, notes = log
                
                try:
                    # Insert into MySQL attendance_logs table
                    mysql_cursor.execute("""
                        INSERT INTO attendance_logs 
                        (employee_id, log_date, log_type, log_time, source, notes)
                        VALUES (%s, %s, %s, %s, %s, %s)
                    """, (employee_id, log_date, log_type, log_time, source, notes))
                    
                    mysql_id = mysql_cursor.lastrowid
                    mysql_conn.commit()
                    
                    # Mark as synced in local database
                    synced_at = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
                    local_cursor.execute("""
                        UPDATE attendance_logs
                        SET synced = 1, synced_at = ?, mysql_id = ?
                        WHERE id = ?
                    """, (synced_at, mysql_id, local_id))
                    
                    local_conn.commit()
                    success_count += 1
                    print(f"  ✓ Synced log ID {local_id} -> MySQL ID {mysql_id}")
                    
                except Exception as e:
                    failed_count += 1
                    print(f"  ❌ Failed to sync log ID {local_id}: {e}")
                    mysql_conn.rollback()
            
            # Update sync status
            self._update_sync_status('attendance_logs', 'push', success_count > 0)
            
            mysql_conn.close()
            local_conn.close()
            
            print(f"✅ Push complete: {success_count} success, {failed_count} failed")
            self.last_push_time = datetime.now()
            
            return {
                'success': success_count,
                'failed': failed_count,
                'message': f'Pushed {success_count} logs successfully'
            }
            
        except pymysql.Error as e:
            print(f"❌ MySQL error during push: {e}")
            self._update_sync_status('attendance_logs', 'push', False, str(e))
            return {'success': 0, 'failed': 0, 'message': f'MySQL error: {str(e)}'}
        except Exception as e:
            print(f"❌ Unexpected error during push: {e}")
            return {'success': 0, 'failed': 0, 'message': f'Error: {str(e)}'}
    
    def push_daily_attendance(self):
        """
        Push daily_attendance records from SQLite to MySQL server.
        This syncs the calculated daily attendance summaries.
        
        Returns:
            dict: Result with counts of successful and failed syncs
        """
        try:
            # Get all daily_attendance records from SQLite
            local_conn = get_db_connection()
            local_cursor = local_conn.cursor()
            
            # Sync records from the last 7 days to catch any missed records
            cutoff_date = (datetime.now() - timedelta(days=7)).strftime('%Y-%m-%d')
            
            local_cursor.execute("""
                SELECT id, employee_id, attendance_date, time_in, time_out,
                       scheduled_hours, actual_hours, late_minutes, 
                       early_departure_minutes, overtime_minutes, break_time_minutes,
                       status, notes, calculated_at
                FROM daily_attendance
                WHERE attendance_date >= ?
                ORDER BY attendance_date DESC, employee_id ASC
            """, (cutoff_date,))
            
            records = local_cursor.fetchall()
            
            if not records:
                local_conn.close()
                return {'success': 0, 'failed': 0, 'message': 'No daily attendance to sync'}
            
            print(f"\n📤 Pushing {len(records)} daily attendance record(s) to MySQL (last 7 days)...")
            
            # Connect to MySQL
            mysql_conn = pymysql.connect(**MYSQL_CONFIG)
            mysql_cursor = mysql_conn.cursor()
            
            success_count = 0
            failed_count = 0
            
            for record in records:
                (rec_id, employee_id, attendance_date, time_in, time_out,
                 scheduled_hours, actual_hours, late_minutes, 
                 early_departure_minutes, overtime_minutes, break_time_minutes,
                 status, notes, calculated_at) = record
                
                try:
                    # Check if record exists in MySQL
                    mysql_cursor.execute("""
                        SELECT id FROM daily_attendance 
                        WHERE employee_id = %s AND attendance_date = %s
                    """, (employee_id, attendance_date))
                    
                    existing = mysql_cursor.fetchone()
                    
                    if existing:
                        # Update existing record in MySQL
                        mysql_cursor.execute("""
                            UPDATE daily_attendance
                            SET time_in = %s, time_out = %s,
                                scheduled_hours = %s, actual_hours = %s,
                                late_minutes = %s, early_departure_minutes = %s,
                                overtime_minutes = %s, break_time_minutes = %s,
                                status = %s, notes = %s, calculated_at = %s
                            WHERE employee_id = %s AND attendance_date = %s
                        """, (time_in, time_out, scheduled_hours, actual_hours,
                              late_minutes, early_departure_minutes, overtime_minutes,
                              break_time_minutes, status, notes, calculated_at,
                              employee_id, attendance_date))
                        print(f"  ✓ Updated: Employee {employee_id}, Date {attendance_date}, Status: {status}")
                    else:
                        # Insert new record into MySQL
                        mysql_cursor.execute("""
                            INSERT INTO daily_attendance
                            (employee_id, attendance_date, time_in, time_out,
                             scheduled_hours, actual_hours, late_minutes,
                             early_departure_minutes, overtime_minutes, break_time_minutes,
                             status, notes, calculated_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                        """, (employee_id, attendance_date, time_in, time_out,
                              scheduled_hours, actual_hours, late_minutes,
                              early_departure_minutes, overtime_minutes, break_time_minutes,
                              status, notes, calculated_at))
                        print(f"  ✓ Inserted: Employee {employee_id}, Date {attendance_date}, Status: {status}")
                    
                    mysql_conn.commit()
                    success_count += 1
                    
                except Exception as e:
                    failed_count += 1
                    print(f"  ❌ Failed to sync daily_attendance (Employee {employee_id}, Date {attendance_date}): {e}")
                    mysql_conn.rollback()
            
            # Update sync status
            self._update_sync_status('daily_attendance', 'push', success_count > 0)
            
            mysql_conn.close()
            local_conn.close()
            
            print(f"✅ Daily attendance push complete: {success_count} success, {failed_count} failed")
            
            return {
                'success': success_count,
                'failed': failed_count,
                'message': f'Pushed {success_count} daily attendance records successfully'
            }
            
        except pymysql.Error as e:
            print(f"❌ MySQL error during daily attendance push: {e}")
            self._update_sync_status('daily_attendance', 'push', False, str(e))
            return {'success': 0, 'failed': 0, 'message': f'MySQL error: {str(e)}'}
        except Exception as e:
            print(f"❌ Unexpected error during daily attendance push: {e}")
            return {'success': 0, 'failed': 0, 'message': f'Error: {str(e)}'}
    
    # ========================================================================
    # PULL: Fetch updates from MySQL to SQLite
    # ========================================================================
    
    def pull_employees(self):
        """
        Pull employee updates from MySQL to local SQLite.
        Syncs new employees and updates to existing employees.
        
        Returns:
            dict: Result with counts of added and updated employees
        """
        try:
            print("\n📥 Pulling employee updates from MySQL...")
            
            # Connect to MySQL
            mysql_conn = pymysql.connect(**MYSQL_CONFIG)
            mysql_cursor = mysql_conn.cursor(pymysql.cursors.DictCursor)
            
            # Get last sync time
            local_conn = get_db_connection()
            local_cursor = local_conn.cursor()
            
            local_cursor.execute("""
                SELECT last_pull_time FROM sync_status WHERE table_name = 'employees'
            """)
            row = local_cursor.fetchone()
            last_sync = row[0] if row else '2000-01-01 00:00:00'
            
            # Fetch employees updated since last sync
            mysql_cursor.execute("""
                SELECT id, employee_id, first_name, middle_name, last_name,
                       email, phone, department, position, status, profile_photo,
                       created_at, updated_at
                FROM employees
                WHERE updated_at >= %s OR created_at >= %s
                ORDER BY updated_at ASC
            """, (last_sync, last_sync))
            
            employees = mysql_cursor.fetchall()
            
            if not employees:
                mysql_conn.close()
                local_conn.close()
                return {'added': 0, 'updated': 0, 'message': 'No employee updates'}
            
            added_count = 0
            updated_count = 0
            
            for emp in employees:
                # Check if employee exists in local database
                local_cursor.execute("SELECT id FROM employees WHERE id = ?", (emp['id'],))
                exists = local_cursor.fetchone()
                
                if exists:
                    # Update existing employee
                    local_cursor.execute("""
                        UPDATE employees
                        SET employee_id = ?, first_name = ?, middle_name = ?,
                            last_name = ?, email = ?, phone = ?, department = ?,
                            position = ?, status = ?, profile_photo = ?,
                            updated_at = ?, last_synced = ?
                        WHERE id = ?
                    """, (
                        emp['employee_id'], emp['first_name'], emp['middle_name'],
                        emp['last_name'], emp['email'], emp['phone'], emp['department'],
                        emp['position'], emp['status'], emp['profile_photo'],
                        emp['updated_at'], datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                        emp['id']
                    ))
                    updated_count += 1
                    print(f"  ✓ Updated employee: {emp['employee_id']} - {emp['first_name']} {emp['last_name']}")
                else:
                    # Insert new employee
                    local_cursor.execute("""
                        INSERT INTO employees 
                        (id, employee_id, first_name, middle_name, last_name,
                         email, phone, department, position, status, profile_photo,
                         created_at, updated_at, last_synced)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    """, (
                        emp['id'], emp['employee_id'], emp['first_name'], emp['middle_name'],
                        emp['last_name'], emp['email'], emp['phone'], emp['department'],
                        emp['position'], emp['status'], emp['profile_photo'],
                        emp['created_at'], emp['updated_at'], 
                        datetime.now().strftime('%Y-%m-%d %H:%M:%S')
                    ))
                    added_count += 1
                    print(f"  ✓ Added new employee: {emp['employee_id']} - {emp['first_name']} {emp['last_name']}")
            
            local_conn.commit()
            
            # Update sync status
            self._update_sync_status('employees', 'pull', True)
            
            mysql_conn.close()
            local_conn.close()
            
            print(f"✅ Employee sync complete: {added_count} added, {updated_count} updated")
            
            return {
                'added': added_count,
                'updated': updated_count,
                'message': f'Synced {added_count + updated_count} employees'
            }
            
        except Exception as e:
            print(f"❌ Error pulling employees: {e}")
            self._update_sync_status('employees', 'pull', False, str(e))
            return {'added': 0, 'updated': 0, 'message': f'Error: {str(e)}'}
    
    def pull_schedules(self):
        """
        Pull schedule updates from MySQL to local SQLite.
        Syncs schedules, schedule_periods, and employee_schedules.
        
        Returns:
            dict: Result with sync statistics
        """
        try:
            print("\n📥 Pulling schedule updates from MySQL...")
            
            mysql_conn = pymysql.connect(**MYSQL_CONFIG)
            mysql_cursor = mysql_conn.cursor(pymysql.cursors.DictCursor)
            
            local_conn = get_db_connection()
            local_cursor = local_conn.cursor()
            
            # Get last sync time
            local_cursor.execute("""
                SELECT last_pull_time FROM sync_status WHERE table_name = 'schedules'
            """)
            row = local_cursor.fetchone()
            last_sync = row[0] if row else '2000-01-01 00:00:00'
            
            # Fetch ALL schedules (not just new ones)
            # We need to sync all schedules, not just those created after last sync
            mysql_cursor.execute("""
                SELECT id, schedule_name, description, created_at
                FROM schedules
            """)
            
            schedules = mysql_cursor.fetchall()
            schedule_count = 0
            
            for sched in schedules:
                local_cursor.execute("SELECT id FROM schedules WHERE id = ?", (sched['id'],))
                exists = local_cursor.fetchone()
                
                if exists:
                    local_cursor.execute("""
                        UPDATE schedules
                        SET schedule_name = ?, description = ?, last_synced = ?
                        WHERE id = ?
                    """, (sched['schedule_name'], sched['description'],
                          datetime.now().strftime('%Y-%m-%d %H:%M:%S'), sched['id']))
                else:
                    local_cursor.execute("""
                        INSERT INTO schedules (id, schedule_name, description, created_at, last_synced)
                        VALUES (?, ?, ?, ?, ?)
                    """, (sched['id'], sched['schedule_name'], sched['description'],
                          sched['created_at'], datetime.now().strftime('%Y-%m-%d %H:%M:%S')))
                schedule_count += 1
            
            # Fetch schedule periods from MySQL
            mysql_cursor.execute("""
                SELECT id, schedule_id, day_of_week, period_name, start_time, end_time, is_active
                FROM schedule_periods
            """)
            
            mysql_periods = mysql_cursor.fetchall()
            
            # Get all MySQL period IDs
            mysql_period_ids = set([p['id'] for p in mysql_periods])
            
            # Get all local period IDs
            local_cursor.execute("SELECT id FROM schedule_periods")
            local_period_ids = set([row[0] for row in local_cursor.fetchall()])
            
            # Delete orphaned periods (exist in SQLite but not in MySQL)
            orphaned_ids = local_period_ids - mysql_period_ids
            if orphaned_ids:
                placeholders = ','.join(['?' for _ in orphaned_ids])
                local_cursor.execute(f"DELETE FROM schedule_periods WHERE id IN ({placeholders})", 
                                   tuple(orphaned_ids))
                print(f"  🗑️  Deleted {len(orphaned_ids)} orphaned schedule period(s)")
            
            # Now sync all periods from MySQL
            period_count = 0
            for period in mysql_periods:
                local_cursor.execute("SELECT id FROM schedule_periods WHERE id = ?", (period['id'],))
                exists = local_cursor.fetchone()
                
                # Convert timedelta to string format for SQLite
                # Note: timedelta(0) evaluates to False, so check for None explicitly
                start_time = str(period['start_time']) if period['start_time'] is not None else None
                end_time = str(period['end_time']) if period['end_time'] is not None else None
                
                if exists:
                    local_cursor.execute("""
                        UPDATE schedule_periods
                        SET schedule_id = ?, day_of_week = ?, period_name = ?,
                            start_time = ?, end_time = ?, is_active = ?, last_synced = ?
                        WHERE id = ?
                    """, (period['schedule_id'], period['day_of_week'], period['period_name'],
                          start_time, end_time, period['is_active'],
                          datetime.now().strftime('%Y-%m-%d %H:%M:%S'), period['id']))
                else:
                    local_cursor.execute("""
                        INSERT INTO schedule_periods 
                        (id, schedule_id, day_of_week, period_name, start_time, end_time, is_active, last_synced)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    """, (period['id'], period['schedule_id'], period['day_of_week'],
                          period['period_name'], start_time, end_time,
                          period['is_active'], datetime.now().strftime('%Y-%m-%d %H:%M:%S')))
                period_count += 1
            
            # Fetch ALL employee schedules from MySQL
            mysql_cursor.execute("""
                SELECT id, employee_id, schedule_id, effective_date, end_date, is_active, created_at
                FROM employee_schedules
            """)
            
            mysql_emp_schedules = mysql_cursor.fetchall()
            
            # Get all MySQL employee_schedule IDs
            mysql_es_ids = set([es['id'] for es in mysql_emp_schedules])
            
            # Get all local employee_schedule IDs
            local_cursor.execute("SELECT id FROM employee_schedules")
            local_es_ids = set([row[0] for row in local_cursor.fetchall()])
            
            # Delete orphaned employee schedules (exist in SQLite but not in MySQL)
            orphaned_es_ids = local_es_ids - mysql_es_ids
            if orphaned_es_ids:
                placeholders = ','.join(['?' for _ in orphaned_es_ids])
                local_cursor.execute(f"DELETE FROM employee_schedules WHERE id IN ({placeholders})", 
                                   tuple(orphaned_es_ids))
                print(f"  🗑️  Deleted {len(orphaned_es_ids)} orphaned employee schedule(s)")
            
            # Now sync all employee schedules from MySQL
            emp_sched_count = 0
            for es in mysql_emp_schedules:
                local_cursor.execute("SELECT id FROM employee_schedules WHERE id = ?", (es['id'],))
                exists = local_cursor.fetchone()
                
                if exists:
                    local_cursor.execute("""
                        UPDATE employee_schedules
                        SET employee_id = ?, schedule_id = ?, effective_date = ?,
                            end_date = ?, is_active = ?, last_synced = ?
                        WHERE id = ?
                    """, (es['employee_id'], es['schedule_id'], es['effective_date'],
                          es['end_date'], es['is_active'],
                          datetime.now().strftime('%Y-%m-%d %H:%M:%S'), es['id']))
                else:
                    local_cursor.execute("""
                        INSERT INTO employee_schedules
                        (id, employee_id, schedule_id, effective_date, end_date, is_active, created_at, last_synced)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    """, (es['id'], es['employee_id'], es['schedule_id'], es['effective_date'],
                          es['end_date'], es['is_active'], es['created_at'],
                          datetime.now().strftime('%Y-%m-%d %H:%M:%S')))
                emp_sched_count += 1
            
            local_conn.commit()
            
            # Update sync status
            self._update_sync_status('schedules', 'pull', True)
            self._update_sync_status('schedule_periods', 'pull', True)
            self._update_sync_status('employee_schedules', 'pull', True)
            
            mysql_conn.close()
            local_conn.close()
            
            print(f"✅ Schedule sync complete: {schedule_count} schedules, {period_count} periods, {emp_sched_count} assignments")
            
            return {
                'schedules': schedule_count,
                'periods': period_count,
                'employee_schedules': emp_sched_count,
                'message': 'Schedule sync completed'
            }
            
        except Exception as e:
            print(f"❌ Error pulling schedules: {e}")
            self._update_sync_status('schedules', 'pull', False, str(e))
            return {'schedules': 0, 'periods': 0, 'employee_schedules': 0, 'message': f'Error: {str(e)}'}
    
    def pull_daily_attendance(self):
        """
        Pull daily attendance summary from MySQL to local SQLite.
        
        Returns:
            dict: Result with sync statistics
        """
        try:
            print("\n📥 Pulling daily attendance from MySQL...")
            
            mysql_conn = pymysql.connect(**MYSQL_CONFIG)
            mysql_cursor = mysql_conn.cursor(pymysql.cursors.DictCursor)
            
            local_conn = get_db_connection()
            local_cursor = local_conn.cursor()
            
            # Get last sync time
            local_cursor.execute("""
                SELECT last_pull_time FROM sync_status WHERE table_name = 'daily_attendance'
            """)
            row = local_cursor.fetchone()
            last_sync = row[0] if row else '2000-01-01 00:00:00'
            
            # Fetch daily attendance records (all records, not just new ones)
            mysql_cursor.execute("""
                SELECT id, employee_id, attendance_date, time_in, time_out,
                       scheduled_hours, actual_hours, late_minutes, early_departure_minutes, 
                       overtime_minutes, break_time_minutes, status, notes, calculated_at
                FROM daily_attendance
            """)
            
            records = mysql_cursor.fetchall()
            added_count = 0
            updated_count = 0
            
            for record in records:
                local_cursor.execute("SELECT id FROM daily_attendance WHERE id = ?", (record['id'],))
                exists = local_cursor.fetchone()
                
                if exists:
                    # Update existing record
                    local_cursor.execute("""
                        UPDATE daily_attendance
                        SET employee_id = ?, attendance_date = ?, time_in = ?, time_out = ?,
                            scheduled_hours = ?, actual_hours = ?, late_minutes = ?, 
                            early_departure_minutes = ?, overtime_minutes = ?, break_time_minutes = ?, 
                            status = ?, notes = ?, calculated_at = ?, last_synced = ?
                        WHERE id = ?
                    """, (
                        record['employee_id'], record['attendance_date'], record['time_in'], 
                        record['time_out'], record['scheduled_hours'], record['actual_hours'], 
                        record['late_minutes'], record['early_departure_minutes'],
                        record['overtime_minutes'], record['break_time_minutes'], record['status'],
                        record['notes'], record['calculated_at'],
                        datetime.now().strftime('%Y-%m-%d %H:%M:%S'), record['id']
                    ))
                    updated_count += 1
                else:
                    # Insert new record
                    local_cursor.execute("""
                        INSERT INTO daily_attendance
                        (id, employee_id, attendance_date, time_in, time_out,
                         scheduled_hours, actual_hours, late_minutes, early_departure_minutes, 
                         overtime_minutes, break_time_minutes, status, notes, calculated_at, last_synced)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    """, (
                        record['id'], record['employee_id'], record['attendance_date'],
                        record['time_in'], record['time_out'], record['scheduled_hours'], 
                        record['actual_hours'], record['late_minutes'],
                        record['early_departure_minutes'], record['overtime_minutes'],
                        record['break_time_minutes'], record['status'], record['notes'],
                        record['calculated_at'], datetime.now().strftime('%Y-%m-%d %H:%M:%S')
                    ))
                    added_count += 1
            
            local_conn.commit()
            
            # Update sync status
            self._update_sync_status('daily_attendance', 'pull', True)
            
            mysql_conn.close()
            local_conn.close()
            
            print(f"✅ Daily attendance sync complete: {added_count} added, {updated_count} updated")
            
            return {
                'added': added_count,
                'updated': updated_count,
                'message': f'Synced {added_count + updated_count} daily attendance records'
            }
            
        except Exception as e:
            print(f"❌ Error pulling daily attendance: {e}")
            self._update_sync_status('daily_attendance', 'pull', False, str(e))
            return {'added': 0, 'updated': 0, 'message': f'Error: {str(e)}'}
    
    def pull_all_updates(self):
        """
        Pull all updates from MySQL (employees, schedules, and daily attendance).
        
        Returns:
            dict: Combined results from all pull operations
        """
        results = {}
        
        # Pull employees
        emp_result = self.pull_employees()
        results['employees'] = emp_result
        
        # Pull schedules
        sched_result = self.pull_schedules()
        results['schedules'] = sched_result
        
        # Pull daily attendance
        daily_result = self.pull_daily_attendance()
        results['daily_attendance'] = daily_result
        
        self.last_pull_time = datetime.now()
        
        return results
    
    # ========================================================================
    # Background Sync Threads
    # ========================================================================
    
    def _push_loop(self):
        """Background thread for continuous push operations."""
        print("🔄 Push sync thread started")
        
        while not self.stop_event.is_set():
            try:
                if self.test_mysql_connection():
                    # Push attendance logs
                    result = self.push_attendance_logs()
                    if result['success'] > 0:
                        print(f"⏰ [{datetime.now().strftime('%H:%M:%S')}] Pushed {result['success']} attendance logs")
                    
                    # Push daily attendance records
                    daily_result = self.push_daily_attendance()
                    if daily_result['success'] > 0:
                        print(f"⏰ [{datetime.now().strftime('%H:%M:%S')}] Pushed {daily_result['success']} daily attendance records")
                else:
                    print(f"⏰ [{datetime.now().strftime('%H:%M:%S')}] MySQL unavailable, will retry...")
            except Exception as e:
                print(f"❌ Error in push loop: {e}")
            
            # Wait before next push check
            self.stop_event.wait(PUSH_INTERVAL)
    
    def _pull_loop(self):
        """Background thread for continuous pull operations."""
        print("🔄 Pull sync thread started")
        
        while not self.stop_event.is_set():
            try:
                if self.test_mysql_connection():
                    print(f"\n⏰ [{datetime.now().strftime('%H:%M:%S')}] Running scheduled pull...")
                    self.pull_all_updates()
                else:
                    print(f"⏰ [{datetime.now().strftime('%H:%M:%S')}] MySQL unavailable, will retry...")
            except Exception as e:
                print(f"❌ Error in pull loop: {e}")
            
            # Wait before next pull (60 seconds)
            self.stop_event.wait(PULL_INTERVAL)
    
    def start_continuous_sync(self):
        """
        Start continuous background synchronization.
        Runs push and pull operations in separate threads.
        """
        print("\n" + "=" * 70)
        print("Starting Continuous Sync Manager")
        print("=" * 70)
        print(f"Push interval: {PUSH_INTERVAL} seconds")
        print(f"Pull interval: {PULL_INTERVAL} seconds")
        print(f"Local database: {DB_PATH}")
        print("=" * 70 + "\n")
        
        # Test initial connection
        if self.test_mysql_connection():
            print("✅ MySQL server is accessible")
            # Do initial pull
            print("\n🔄 Performing initial data pull...")
            self.pull_all_updates()
        else:
            print("⚠️  MySQL server is not accessible. Will retry automatically.")
        
        # Start push thread
        self.push_thread = Thread(target=self._push_loop, daemon=True)
        self.push_thread.start()
        
        # Start pull thread
        self.pull_thread = Thread(target=self._pull_loop, daemon=True)
        self.pull_thread.start()
        
        print("\n✅ Sync threads started. Press Ctrl+C to stop.\n")
    
    def stop_sync(self):
        """Stop the continuous synchronization."""
        print("\n🛑 Stopping sync threads...")
        self.stop_event.set()
        
        if self.push_thread:
            self.push_thread.join(timeout=2)
        if self.pull_thread:
            self.pull_thread.join(timeout=2)
        
        print("✅ Sync stopped")
    
    # ========================================================================
    # Utility Methods
    # ========================================================================
    
    def _update_sync_status(self, table_name, sync_type, success, error_msg=None):
        """
        Update the sync status table.
        
        Args:
            table_name (str): Name of the table that was synced
            sync_type (str): 'push' or 'pull'
            success (bool): Whether the sync was successful
            error_msg (str, optional): Error message if sync failed
        """
        try:
            conn = get_db_connection()
            cursor = conn.cursor()
            
            now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            
            if sync_type == 'push':
                cursor.execute("""
                    UPDATE sync_status
                    SET last_push_time = ?, last_push_success = ?, push_error_message = ?, updated_at = ?
                    WHERE table_name = ?
                """, (now, 1 if success else 0, error_msg, now, table_name))
            else:  # pull
                cursor.execute("""
                    UPDATE sync_status
                    SET last_pull_time = ?, last_pull_success = ?, pull_error_message = ?, updated_at = ?
                    WHERE table_name = ?
                """, (now, 1 if success else 0, error_msg, now, table_name))
            
            conn.commit()
            conn.close()
        except Exception as e:
            print(f"⚠️  Warning: Could not update sync status: {e}")
    
    def get_sync_status(self):
        """
        Get the current sync status for all tables.
        
        Returns:
            list: List of sync status dictionaries
        """
        try:
            conn = get_db_connection()
            cursor = conn.cursor()
            
            cursor.execute("""
                SELECT table_name, last_pull_time, last_push_time,
                       last_pull_success, last_push_success,
                       pull_error_message, push_error_message, updated_at
                FROM sync_status
                ORDER BY table_name
            """)
            
            rows = cursor.fetchall()
            conn.close()
            
            status = []
            for row in rows:
                status.append({
                    'table': row[0],
                    'last_pull': row[1],
                    'last_push': row[2],
                    'pull_ok': bool(row[3]),
                    'push_ok': bool(row[4]),
                    'pull_error': row[5],
                    'push_error': row[6],
                    'updated': row[7]
                })
            
            return status
        except Exception as e:
            print(f"❌ Error getting sync status: {e}")
            return []

# ============================================================================
# CLI Interface
# ============================================================================

def main():
    """Main entry point for the sync manager."""
    import argparse
    
    parser = argparse.ArgumentParser(description='SQLite-MySQL Sync Manager')
    parser.add_argument('--mode', choices=['push', 'pull', 'continuous', 'status'],
                       default='continuous',
                       help='Sync mode: push (logs to MySQL), pull (updates from MySQL), continuous (both), status (show sync status)')
    
    args = parser.parse_args()
    
    manager = SyncManager()
    
    if args.mode == 'status':
        print("\n" + "=" * 70)
        print("Sync Status")
        print("=" * 70)
        status = manager.get_sync_status()
        for s in status:
            print(f"\nTable: {s['table']}")
            print(f"  Last Pull: {s['last_pull']} {'✓' if s['pull_ok'] else '✗'}")
            print(f"  Last Push: {s['last_push']} {'✓' if s['push_ok'] else '✗'}")
            if s['pull_error']:
                print(f"  Pull Error: {s['pull_error']}")
            if s['push_error']:
                print(f"  Push Error: {s['push_error']}")
        print("=" * 70)
    
    elif args.mode == 'push':
        print("\nRunning one-time push...")
        if manager.test_mysql_connection():
            # Push attendance logs
            result = manager.push_attendance_logs()
            print(f"\nAttendance Logs: {result['message']}")
            
            # Push daily attendance
            daily_result = manager.push_daily_attendance()
            print(f"Daily Attendance: {daily_result['message']}")
        else:
            print("❌ Cannot connect to MySQL server")
    
    elif args.mode == 'pull':
        print("\nRunning one-time pull...")
        if manager.test_mysql_connection():
            manager.pull_all_updates()
        else:
            print("❌ Cannot connect to MySQL server")
    
    elif args.mode == 'continuous':
        manager.start_continuous_sync()
        
        try:
            # Keep the main thread alive
            while True:
                time.sleep(1)
        except KeyboardInterrupt:
            manager.stop_sync()
            print("\n👋 Sync manager stopped.")

if __name__ == "__main__":
    main()
