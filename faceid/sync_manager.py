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
import urllib.request
import urllib.parse

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

# Define Logs Directory
LOG_DIR = os.path.join(os.path.dirname(SCRIPT_DIR), "logs")
if not os.path.exists(LOG_DIR):
    try:
        os.makedirs(LOG_DIR)
    except Exception as e:
        print(f"⚠️  Warning: Could not create logs directory: {e}")

from init_local_db import get_db_connection, DB_PATH

# ============================================================================
# CONFIGURATION
# ============================================================================

# MySQL Server Configuration
MYSQL_CONFIG = {
    'host': 'localhost',
    'user': 'attendance_admin',
    'password': 'Confirmp@ssword123',
    'database': 'database_records',
    'charset': 'utf8mb4',
    'connect_timeout': 5
}

# Cloud API Configuration
CLOUD_API_CONFIG = {
    'url': 'http://bpcfaceid.com/api/sync_endpoint.php',
    'key': 'lD9OcrtiWGxmSRCV1YpdqwAk5JPygLfo'
}

# Sync intervals (in seconds)
PULL_INTERVAL = 5   # Pull updates every 5 seconds (Near-instant sync)
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

    def _sync_to_cloud_api(self, action, table, data, where_condition=''):
        """
        Push data to Cloud API using urllib.
        """
        try:
            params = {
                'action': action,
                'table': table,
                'data': json.dumps(data),
                'where': where_condition
            }
            
            # Prepare data
            data_encoded = urllib.parse.urlencode(params).encode('utf-8')
            
            # Create request
            req = urllib.request.Request(CLOUD_API_CONFIG['url'], data=data_encoded)
            req.add_header('X-API-KEY', CLOUD_API_CONFIG['key'])
            req.add_header('Content-Type', 'application/x-www-form-urlencoded')
            
            # Send request
            with urllib.request.urlopen(req, timeout=5) as response:
                result = json.loads(response.read().decode('utf-8'))
                if result.get('success'):
                    return True
                else:
                    print(f"  ⚠️  Cloud Sync Failed ({table}): {result.get('error')}")
                    return False
                    
        except Exception as e:
            print(f"  ⚠️  Cloud Sync Error ({table}): {e}")
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
                    # Check if log already exists in MySQL to prevent duplicates
                    mysql_cursor.execute("""
                        SELECT id FROM attendance_logs 
                        WHERE employee_id = %s AND log_date = %s AND log_time = %s AND log_type = %s
                    """, (employee_id, log_date, log_time, log_type))
                    
                    existing_log = mysql_cursor.fetchone()
                    
                    if existing_log:
                        mysql_id = existing_log[0]
                        print(f"  ⚠️  Log already exists in MySQL (ID: {mysql_id}), skipping insert...")
                    else:
                        # Insert into MySQL attendance_logs table
                        mysql_cursor.execute("""
                            INSERT INTO attendance_logs 
                            (employee_id, log_date, log_type, log_time, source, notes)
                            VALUES (%s, %s, %s, %s, %s, %s)
                        """, (employee_id, log_date, log_type, log_time, source, notes))
                        
                        mysql_id = mysql_cursor.lastrowid

                    mysql_conn.commit()

                    # Local DB is now the source of truth for the background sync engine.
                    
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
                SELECT id, employee_id, attendance_date, time_in, break_out, break_in, time_out,
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
                (rec_id, employee_id, attendance_date, time_in, break_out, break_in, time_out,
                 scheduled_hours, actual_hours, late_minutes, 
                 early_departure_minutes, overtime_minutes, break_time_minutes,
                 status, notes, calculated_at) = record
                
                try:
                    # Check if record exists in MySQL
                    mysql_cursor.execute("""
                        SELECT id, time_in, break_out, break_in, time_out, actual_hours, status,
                               late_minutes, early_departure_minutes, overtime_minutes, break_time_minutes
                        FROM daily_attendance 
                        WHERE employee_id = %s AND attendance_date = %s
                    """, (employee_id, attendance_date))
                    
                    existing = mysql_cursor.fetchone()
                    
                    if existing:
                        mysql_id = existing[0]
                        remote_time_in = existing[1]
                        remote_break_out = existing[2]
                        remote_break_in = existing[3]
                        remote_time_out = existing[4]
                        # Correct indices based on new SELECT
                        remote_actual_hours = existing[5]
                        remote_status = existing[6]
                        remote_late_minutes = existing[7]
                        remote_early_minutes = existing[8]
                        remote_overtime = existing[9]
                        remote_break_time = existing[10]
                        
                        # MERGE LOGIC: Don't overwrite valid remote data with local None
                        # If local is None/Empty but remote has value, keep remote value
                        
                        final_time_in = time_in
                        if (not time_in or str(time_in) == '00:00:00') and remote_time_in:
                             final_time_in = remote_time_in
                             print(f"    ℹ️  Preserving remote Time In: {remote_time_in}")

                        final_break_out = break_out
                        if (not break_out or str(break_out) == '00:00:00') and remote_break_out:
                             final_break_out = remote_break_out

                        final_break_in = break_in
                        if (not break_in or str(break_in) == '00:00:00') and remote_break_in:
                             final_break_in = remote_break_in

                        final_time_out = time_out
                        if (not time_out or str(time_out) == '00:00:00') and remote_time_out:
                             final_time_out = remote_time_out
                             print(f"    ℹ️  Preserving remote Time Out: {remote_time_out}")

                        # MERGE LOGIC: Preserve actual_hours and status
                        final_actual_hours = actual_hours
                        if (actual_hours is None or actual_hours == 0) and remote_actual_hours:
                            final_actual_hours = remote_actual_hours
                            print(f"    ℹ️  Preserving remote Actual Hours: {remote_actual_hours}")
                            
                        # MERGE LOGIC: Preserve metrics if local is empty/zero and remote isn't
                        final_late_minutes = late_minutes
                        if (not late_minutes or late_minutes == 0) and remote_late_minutes:
                            final_late_minutes = remote_late_minutes
                            
                        final_early_minutes = early_departure_minutes
                        if (not early_departure_minutes or early_departure_minutes == 0) and remote_early_minutes:
                            final_early_minutes = remote_early_minutes
                            
                        final_overtime = overtime_minutes
                        if (not overtime_minutes or overtime_minutes == 0) and remote_overtime:
                            final_overtime = remote_overtime
                        
                        final_status = status
                        if (not status or status == 'incomplete') and ('complete' in remote_status or 'manual' in remote_status):
                            final_status = remote_status
                            print(f"    ℹ️  Preserving remote Status: {remote_status}")
                            
                        # Upgrade to complete if both time markers are present but status is stuck on incomplete
                        if final_time_in and final_time_out and 'incomplete' in final_status:
                            final_status = final_status.replace('incomplete', 'complete').replace('  ', ' ').strip()
                            if final_late_minutes and final_late_minutes > 0 and 'late' not in final_status:
                                final_status += ' late'
                            if final_early_minutes and final_early_minutes > 0 and 'undertime' not in final_status:
                                final_status += ' undertime'

                        # Update existing record in MySQL
                        mysql_cursor.execute("""
                            UPDATE daily_attendance
                            SET time_in = %s, break_out = %s, break_in = %s, time_out = %s,
                                scheduled_hours = %s, actual_hours = %s,
                                late_minutes = %s, early_departure_minutes = %s,
                                overtime_minutes = %s, break_time_minutes = %s,
                                status = %s, notes = %s, calculated_at = %s,
                                sync_status = 0
                            WHERE employee_id = %s AND attendance_date = %s
                        """, (final_time_in, final_break_out, final_break_in, final_time_out, scheduled_hours, final_actual_hours,
                              final_late_minutes, final_early_minutes, final_overtime,
                              break_time_minutes, final_status, notes, calculated_at,
                              employee_id, attendance_date))
                        print(f"  ✓ Updated: Employee {employee_id}, Date {attendance_date}, Status: {final_status}")
                        
                        # Local DB is now the source of truth for the background sync engine.

                    else:
                        # Insert new record into MySQL
                        mysql_cursor.execute("""
                            INSERT INTO daily_attendance
                            (employee_id, attendance_date, time_in, break_out, break_in, time_out,
                             scheduled_hours, actual_hours, late_minutes,
                             early_departure_minutes, overtime_minutes, break_time_minutes,
                             status, notes, calculated_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                        """, (employee_id, attendance_date, time_in, break_out, break_in, time_out,
                              scheduled_hours, actual_hours, late_minutes,
                              early_departure_minutes, overtime_minutes, break_time_minutes,
                              status, notes, calculated_at))
                        print(f"  ✓ Inserted: Employee {employee_id}, Date {attendance_date}, Status: {status}")
                        
                        # Local DB is now the source of truth for the background sync engine.
                    
                    # Offset Time Bank Logging
                    if 'complete' in (final_status if existing else status) and (final_actual_hours if existing else actual_hours):
                        hours_in_minutes = final_actual_hours if existing else actual_hours
                        if hours_in_minutes > 0:
                            mysql_cursor.execute("""
                                SELECT id, status 
                                FROM offset_schedule_requests 
                                WHERE employee_id = %s AND requested_date = %s 
                                AND status IN ('approved', 'completed')
                            """, (employee_id, attendance_date))
                            offset_req = mysql_cursor.fetchone()
                            if offset_req:
                                offset_id = offset_req[0]
                                worked_hours = round(hours_in_minutes / 60.0, 2)
                                mysql_cursor.execute("""
                                    SELECT id FROM time_bank_ledger 
                                    WHERE source_id = %s AND transaction_type = 'earned'
                                """, (offset_id,))
                                ledger_entry = mysql_cursor.fetchone()
                                if not ledger_entry:
                                    mysql_cursor.execute("""
                                        INSERT INTO time_bank_ledger (employee_id, transaction_type, hours, source_id, description, reference_date)
                                        VALUES (%s, 'earned', %s, %s, 'Completed Offset Schedule', %s)
                                    """, (employee_id, worked_hours, offset_id, attendance_date))
                                    mysql_cursor.execute("UPDATE offset_schedule_requests SET status = 'completed' WHERE id = %s", (offset_id,))
                                    print(f"  💰 Credited {worked_hours} hours to Time Bank for Offset {offset_id}")
                                else:
                                    ledger_id = ledger_entry[0]
                                    mysql_cursor.execute("UPDATE time_bank_ledger SET hours = %s WHERE id = %s", (worked_hours, ledger_id))
                                    print(f"  💰 Updated Time Bank credit to {worked_hours} hours for Offset {offset_id}")

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
                       email, phone, roles, department, position, status, profile_photo,
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
                            last_name = ?, email = ?, phone = ?, roles = ?, department = ?,
                            position = ?, status = ?, profile_photo = ?,
                            updated_at = ?, last_synced = ?
                        WHERE id = ?
                    """, (
                        emp['employee_id'], emp['first_name'], emp['middle_name'],
                        emp['last_name'], emp['email'], emp['phone'], emp['roles'], emp['department'],
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
                         email, phone, roles, department, position, status, profile_photo,
                         created_at, updated_at, last_synced)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    """, (
                        emp['id'], emp['employee_id'], emp['first_name'], emp['middle_name'],
                        emp['last_name'], emp['email'], emp['phone'], emp['roles'], emp['department'],
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
            
            # Helper to safely convert TIME/timedelta to string
            def _format_time_value(val):
                if val is None:
                    return None
                if isinstance(val, timedelta):
                    total_seconds = int(val.total_seconds())
                    hours = total_seconds // 3600
                    minutes = (total_seconds % 3600) // 60
                    seconds = total_seconds % 60
                    return f"{hours:02}:{minutes:02}:{seconds:02}"
                return str(val)

            def _safe_float(val):
                if val is None: return None
                return float(val)

            def _safe_str(val):
                if val is None: return None
                return str(val)

            for record in records:
                local_cursor.execute("SELECT id FROM daily_attendance WHERE id = ?", (record['id'],))
                exists = local_cursor.fetchone()
                
                # Convert Types safely for SQLite
                t_in = _format_time_value(record['time_in'])
                t_out = _format_time_value(record['time_out'])
                att_date = _safe_str(record['attendance_date'])
                calc_at = _safe_str(record['calculated_at'])
                
                # Convert Decimals to float
                sched_hours = _safe_float(record['scheduled_hours'])
                act_hours = _safe_float(record['actual_hours'])
                
                # print(f"DEBUG: Syncing Daily Record {record['id']} - Raw TimeIn: {record['time_in']} ({type(record['time_in'])}) -> Converted: {t_in}")

                if not exists:
                     # Fallback: Check for existing record by natural key (employee_id + date)
                     # This handles cases where local intializer created a record with a different ID than MySQL
                     local_cursor.execute("SELECT id FROM daily_attendance WHERE employee_id = ? AND attendance_date = ?", 
                                          (record['employee_id'], att_date))
                     exists = local_cursor.fetchone()
                     if exists:
                         # print(f"DEBUG: Found match by natural key for Emp {record['employee_id']} Date {att_date}. Local ID: {exists[0]} vs Remote ID: {record['id']}")
                         pass

                if exists:
                    local_id = exists[0]
                    
                    # Get existing local values to prevent overwriting with None
                    local_cursor.execute("SELECT time_in, time_out FROM daily_attendance WHERE id = ?", (local_id,))
                    local_rec = local_cursor.fetchone()
                    local_time_in = local_rec[0]
                    local_time_out = local_rec[1]

                    # MERGE LOGIC: Keep local value if remote is None but local is valid
                    final_t_in = t_in
                    if (not t_in or t_in == '00:00:00') and local_time_in:
                        final_t_in = local_time_in
                        # print(f"    ℹ️  Preserving local Time In: {local_time_in}")

                    final_t_out = t_out
                    if (not t_out or t_out == '00:00:00') and local_time_out:
                         final_t_out = local_time_out
                         print(f"    ℹ️  Preserving local Time Out: {local_time_out} (preventing overwrite by remote NULL)")

                    # MERGE LOGIC: Preserve actual_hours and status
                    local_cursor.execute("SELECT actual_hours, status FROM daily_attendance WHERE id = ?", (local_id,))
                    local_info = local_cursor.fetchone()
                    local_act_hours = local_info[0]
                    local_status = local_info[1]

                    final_act_hours = act_hours
                    if (act_hours is None or act_hours == 0) and (local_act_hours is not None and local_act_hours > 0):
                        final_act_hours = local_act_hours
                        print(f"    ℹ️  Preserving local Actual Hours: {local_act_hours}")

                    final_status = record['status']
                    if (not final_status or final_status == 'incomplete') and (local_status == 'complete' or local_status == 'manual'):
                        final_status = local_status
                        print(f"    ℹ️  Preserving local Status: {local_status}")

                    # Update existing record
                    # Note: We do NOT update the 'id' column, we keep the local ID to maintain referential integrity
                    local_cursor.execute("""
                        UPDATE daily_attendance
                        SET employee_id = ?, attendance_date = ?, time_in = ?, time_out = ?,
                            scheduled_hours = ?, actual_hours = ?, late_minutes = ?, 
                            early_departure_minutes = ?, overtime_minutes = ?, break_time_minutes = ?, 
                            status = ?, notes = ?, calculated_at = ?, last_synced = ?
                        WHERE id = ?
                    """, (
                        record['employee_id'], att_date, final_t_in, 
                        final_t_out, sched_hours, final_act_hours, 
                        record['late_minutes'], record['early_departure_minutes'],
                        record['overtime_minutes'], record['break_time_minutes'], final_status,
                        record['notes'], calc_at,
                        datetime.now().strftime('%Y-%m-%d %H:%M:%S'), local_id
                    ))
                    updated_count += 1
                else:
                    # Insert new record
                    # If ID doesn't exist locall and natural key doesn't exist, it's safe to insert.
                    # We try to use the MySQL ID if possible, but if it conflicts with another table (unlikely in SQLite unless strict), logic holds.
                    try:
                        local_cursor.execute("""
                            INSERT INTO daily_attendance
                            (id, employee_id, attendance_date, time_in, time_out,
                             scheduled_hours, actual_hours, late_minutes, early_departure_minutes, 
                             overtime_minutes, break_time_minutes, status, notes, calculated_at, last_synced)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        """, (
                            record['id'], record['employee_id'], att_date,
                            t_in, t_out, sched_hours, 
                            act_hours, record['late_minutes'],
                            record['early_departure_minutes'], record['overtime_minutes'],
                            record['break_time_minutes'], record['status'], record['notes'],
                            calc_at, datetime.now().strftime('%Y-%m-%d %H:%M:%S')
                        ))
                        added_count += 1
                    except sqlite3.IntegrityError:
                        # Fallback if ID conflict occurs (rare but possible if IDs drifted)
                        local_cursor.execute("""
                            INSERT INTO daily_attendance
                            (employee_id, attendance_date, time_in, time_out,
                             scheduled_hours, actual_hours, late_minutes, early_departure_minutes, 
                             overtime_minutes, break_time_minutes, status, notes, calculated_at, last_synced)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        """, (
                            record['employee_id'], att_date,
                            t_in, t_out, sched_hours, 
                            act_hours, record['late_minutes'],
                            record['early_departure_minutes'], record['overtime_minutes'],
                            record['break_time_minutes'], record['status'], record['notes'],
                            calc_at, datetime.now().strftime('%Y-%m-%d %H:%M:%S')
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
    
    def pull_offset_requests(self):
        """
        Pull approved and completed offset requests from MySQL to local SQLite.
        """
        try:
            print("\n📥 Pulling offset schedule requests from MySQL...")
            
            mysql_conn = pymysql.connect(**MYSQL_CONFIG)
            mysql_cursor = mysql_conn.cursor(pymysql.cursors.DictCursor)
            
            local_conn = get_db_connection()
            local_cursor = local_conn.cursor()
            
            # Fetch ALL approved or completed offset requests
            mysql_cursor.execute("""
                SELECT id, employee_id, original_schedule_id, original_day_of_week, 
                       start_time, end_time, requested_date, status, created_at, updated_at
                FROM offset_schedule_requests
                WHERE status IN ('approved', 'completed')
            """)
            
            offsets = mysql_cursor.fetchall()
            added_count = 0
            updated_count = 0
            
            for offset in offsets:
                local_cursor.execute("SELECT id FROM offset_schedule_requests WHERE id = ?", (offset['id'],))
                exists = local_cursor.fetchone()
                
                # Convert timedeltas/TIME
                s_time = str(offset['start_time']) if offset['start_time'] is not None else None
                e_time = str(offset['end_time']) if offset['end_time'] is not None else None
                
                if exists:
                    local_cursor.execute("""
                        UPDATE offset_schedule_requests
                        SET employee_id = ?, original_schedule_id = ?, original_day_of_week = ?,
                            start_time = ?, end_time = ?, requested_date = ?, status = ?,
                            updated_at = ?, last_synced = ?
                        WHERE id = ?
                    """, (offset['employee_id'], offset['original_schedule_id'], offset['original_day_of_week'],
                          s_time, e_time, offset['requested_date'], offset['status'],
                          offset['updated_at'], datetime.now().strftime('%Y-%m-%d %H:%M:%S'), offset['id']))
                    updated_count += 1
                else:
                    local_cursor.execute("""
                        INSERT INTO offset_schedule_requests
                        (id, employee_id, original_schedule_id, original_day_of_week,
                         start_time, end_time, requested_date, status, created_at, updated_at, last_synced)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    """, (offset['id'], offset['employee_id'], offset['original_schedule_id'], offset['original_day_of_week'],
                          s_time, e_time, offset['requested_date'], offset['status'], 
                          offset['created_at'], offset['updated_at'], datetime.now().strftime('%Y-%m-%d %H:%M:%S')))
                    added_count += 1
            
            local_conn.commit()
            
            self._update_sync_status('offset_schedule_requests', 'pull', True)
            
            mysql_conn.close()
            local_conn.close()
            
            print(f"✅ Offset requests sync complete: {added_count} added, {updated_count} updated")
            return {'added': added_count, 'updated': updated_count}
            
        except Exception as e:
            print(f"❌ Error pulling offset requests: {e}")
            self._update_sync_status('offset_schedule_requests', 'pull', False, str(e))
            return {'added': 0, 'updated': 0}

    def pull_cto_requests(self):
        """
        Pull approved and completed CTO requests from MySQL to local SQLite.
        """
        try:
            print("\n📥 Pulling CTO requests from MySQL...")
            
            mysql_conn = pymysql.connect(**MYSQL_CONFIG)
            mysql_cursor = mysql_conn.cursor(pymysql.cursors.DictCursor)
            
            local_conn = get_db_connection()
            local_cursor = local_conn.cursor()
            
            mysql_cursor.execute("""
                SELECT id, employee_id, requested_date, hours_used, status, created_at
                FROM cto_requests
                WHERE status IN ('approved', 'completed')
            """)
            
            ctos = mysql_cursor.fetchall()
            added_count = 0
            updated_count = 0
            
            for cto in ctos:
                local_cursor.execute("SELECT id FROM cto_requests WHERE id = ?", (cto['id'],))
                exists = local_cursor.fetchone()
                
                if exists:
                    local_cursor.execute("""
                        UPDATE cto_requests
                        SET employee_id = ?, requested_date = ?, hours_used = ?, status = ?,
                            last_synced = ?
                        WHERE id = ?
                    """, (cto['employee_id'], cto['requested_date'], float(cto['hours_used']), cto['status'],
                          datetime.now().strftime('%Y-%m-%d %H:%M:%S'), cto['id']))
                    updated_count += 1
                else:
                    local_cursor.execute("""
                        INSERT INTO cto_requests
                        (id, employee_id, requested_date, hours_used, status, created_at, last_synced)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    """, (cto['id'], cto['employee_id'], cto['requested_date'], float(cto['hours_used']), 
                          cto['status'], cto['created_at'], datetime.now().strftime('%Y-%m-%d %H:%M:%S')))
                    added_count += 1
            
            local_conn.commit()
            
            self._update_sync_status('cto_requests', 'pull', True)
            
            mysql_conn.close()
            local_conn.close()
            
            print(f"✅ CTO requests sync complete: {added_count} added, {updated_count} updated")
            return {'added': added_count, 'updated': updated_count}
            
        except Exception as e:
            print(f"❌ Error pulling CTO requests: {e}")
            self._update_sync_status('cto_requests', 'pull', False, str(e))
            return {'added': 0, 'updated': 0}

    def pull_makeup_class_requests(self):
        """
        Pull approved makeup class requests from MySQL to local SQLite.
        """
        try:
            print("\n📥 Pulling Makeup Class requests from MySQL...")
            
            mysql_conn = pymysql.connect(**MYSQL_CONFIG)
            mysql_cursor = mysql_conn.cursor(pymysql.cursors.DictCursor)
            
            local_conn = get_db_connection()
            local_cursor = local_conn.cursor()
            
            mysql_cursor.execute("""
                SELECT id, employee_id, requested_date, start_time, end_time, status, created_at
                FROM makeup_class_requests
                WHERE status = 'approved'
            """)
            
            makeups = mysql_cursor.fetchall()
            added_count = 0
            updated_count = 0
            
            for m in makeups:
                local_cursor.execute("SELECT id FROM makeup_class_requests WHERE id = ?", (m['id'],))
                exists = local_cursor.fetchone()
                
                # Convert TIME objects to string
                s_time = str(m['start_time']) if m['start_time'] is not None else None
                e_time = str(m['end_time']) if m['end_time'] is not None else None
                
                if exists:
                    local_cursor.execute("""
                        UPDATE makeup_class_requests
                        SET employee_id = ?, requested_date = ?, start_time = ?, end_time = ?, status = ?,
                            last_synced = ?
                        WHERE id = ?
                    """, (m['employee_id'], m['requested_date'], s_time, e_time, m['status'],
                          datetime.now().strftime('%Y-%m-%d %H:%M:%S'), m['id']))
                    updated_count += 1
                else:
                    local_cursor.execute("""
                        INSERT INTO makeup_class_requests
                        (id, employee_id, requested_date, start_time, end_time, status, created_at, last_synced)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    """, (m['id'], m['employee_id'], m['requested_date'], s_time, e_time, 
                          m['status'], m['created_at'], datetime.now().strftime('%Y-%m-%d %H:%M:%S')))
                    added_count += 1
            
            local_conn.commit()
            
            self._update_sync_status('makeup_class_requests', 'pull', True)
            
            mysql_conn.close()
            local_conn.close()
            
            print(f"✅ Makeup Class sync complete: {added_count} added, {updated_count} updated")
            return {'added': added_count, 'updated': updated_count}
            
        except Exception as e:
            print(f"❌ Error pulling Makeup Class requests: {e}")
            self._update_sync_status('makeup_class_requests', 'pull', False, str(e))
            return {'added': 0, 'updated': 0}
            
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
        
        # Pull offsets
        offset_result = self.pull_offset_requests()
        results['offsets'] = offset_result
        
        # Pull CTOs
        cto_result = self.pull_cto_requests()
        results['ctos'] = cto_result
        
        # Pull Makeup Classes
        makeup_result = self.pull_makeup_class_requests()
        results['makeups'] = makeup_result
        
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
        
        # Write stopped status immediately
        self._write_sync_status_json(False, "Sync Stopped")
        
        if self.push_thread:
            self.push_thread.join(timeout=2)
        if self.pull_thread:
            self.pull_thread.join(timeout=2)
        
        print("✅ Sync stopped")
    
    # ========================================================================
    # Utility Methods
    # ========================================================================
    
    def _write_sync_status_json(self, success, message=None):
        """
        Write sync status to JSON file for PHP dashboard.
        """
        try:
            status_file = os.path.join(LOG_DIR, "sync_status.json")
            
            data = {
                "last_sync": datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                "status": "success" if success else "error",
                "message": message if message else ("Synced successfully" if success else "Unknown error")
            }
            
            with open(status_file, 'w') as f:
                json.dump(data, f)
                
        except Exception as e:
            print(f"⚠️  Warning: Could not write sync status JSON: {e}")

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
            
        # Also write to JSON for the dashboard
        self._write_sync_status_json(success, error_msg)
    
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
