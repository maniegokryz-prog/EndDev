"""
Auto-Sync Script for Database Synchronization
Syncs local database changes to IONOS cloud every 60 seconds

This script monitors the local database and syncs any new/updated records to IONOS
"""

import requests
import mysql.connector
import time
import json
from datetime import datetime
import os

# Disable SSL warnings completely
import urllib3
urllib3.disable_warnings()

# Configuration
API_URL = "http://bpcfaceid.com/api/sync_endpoint.php"  # Use HTTP to bypass SSL issues
API_KEY = "lD9OcrtiWGxmSRCV1YpdqwAk5JPygLfo"  # Must match sync_endpoint.php

# Local Database Configuration
LOCAL_DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': 'Confirmp@ssword123',
    'database': 'database_records'
}

# Sync interval (seconds)
SYNC_INTERVAL = 60

# Log file
LOG_FILE = "logs/auto_sync.log"

def log_message(message):
    """Log messages with timestamp"""
    timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    log_entry = f"[{timestamp}] {message}\n"
    
    # Create logs directory if it doesn't exist
    os.makedirs('logs', exist_ok=True)
    
    # Write to log file
    with open(LOG_FILE, 'a', encoding='utf-8') as f:
        f.write(log_entry)
    
    # Also print to console
    print(log_entry.strip())

def sync_to_cloud(table, data, action='insert', where=''):
    """Send data to cloud API"""
    try:
        headers = {
            'X-API-KEY': API_KEY,
            'User-Agent': 'Auto-Sync/1.0'
        }
        
        payload = {
            'action': action,
            'table': table,
            'data': json.dumps(data),
            'where': where
        }
        
        # Use verify=False to bypass SSL certificate validation
        response = requests.post(API_URL, data=payload, headers=headers, timeout=30, verify=False)
        
        if response.status_code == 200:
            result = response.json()
            if result.get('success'):
                log_message(f"✅ Synced {action} to {table}: {result.get('message', 'Success')}")
                return True
            else:
                log_message(f"❌ Sync failed: {result.get('error', 'Unknown error')}")
                return False
        else:
            # Log the full error response for debugging
            error_text = response.text[:500] if len(response.text) > 500 else response.text
            log_message(f"❌ API returned status {response.status_code}")
            log_message(f"   Response: {error_text}")
            return False
            
    except requests.exceptions.RequestException as e:
        log_message(f"❌ Network error: {str(e)}")
        return False
    except Exception as e:
        log_message(f"❌ Sync error: {str(e)}")
        return False

def sync_with_lookup(table, data):
    """Send data to cloud API with FK lookup (for employee_schedules and employee_assignments)"""
    try:
        headers = {
            'X-API-KEY': API_KEY,
            'User-Agent': 'Auto-Sync/1.0'
        }
        
        payload = {
            'action': 'sync_with_lookup',
            'table': table,
            'data': json.dumps(data)
        }
        
        # Use verify=False to bypass SSL certificate validation
        response = requests.post(API_URL, data=payload, headers=headers, timeout=30, verify=False)
        
        if response.status_code == 200:
            result = response.json()
            if result.get('success'):
                log_message(f"✅ Synced with lookup to {table}: {result.get('message', 'Success')}")
                return True
            else:
                log_message(f"❌ Lookup sync failed for {table}: {result.get('error', 'Unknown error')}")
                return False
        else:
            error_text = response.text[:500] if len(response.text) > 500 else response.text
            log_message(f"❌ Lookup API returned status {response.status_code} for {table}")
            log_message(f"   Response: {error_text}")
            return False
            
    except requests.exceptions.RequestException as e:
        log_message(f"❌ Lookup network error for {table}: {str(e)}")
        return False
    except Exception as e:
        log_message(f"❌ Lookup sync error for {table}: {str(e)}")
        return False

def get_unsynced_attendance():
    """Get attendance records that need syncing (from last hour)"""
    try:
        conn = mysql.connector.connect(**LOCAL_DB_CONFIG)
        cursor = conn.cursor(dictionary=True)
        
        # Get recent attendance logs (using created_at column)
        query = """
            SELECT * FROM attendance_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY created_at DESC
        """
        cursor.execute(query)
        logs = cursor.fetchall()
        
        # Get recent daily attendance (using calculated_at column)
        query = """
            SELECT * FROM daily_attendance 
            WHERE calculated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY attendance_date DESC
        """
        cursor.execute(query)
        daily = cursor.fetchall()
        
        cursor.close()
        conn.close()
        
        return logs, daily
        
    except mysql.connector.Error as e:
        log_message(f"❌ Database error: {str(e)}")
        return [], []

def convert_to_json_serializable(data):
    """Convert datetime and other non-serializable types to strings"""
    from datetime import datetime, date, time, timedelta
    from decimal import Decimal
    
    if isinstance(data, dict):
        return {k: convert_to_json_serializable(v) for k, v in data.items()}
    elif isinstance(data, list):
        return [convert_to_json_serializable(item) for item in data]
    elif isinstance(data, datetime):
        return data.strftime('%Y-%m-%d %H:%M:%S')
    elif isinstance(data, date):
        return data.strftime('%Y-%m-%d')
    elif isinstance(data, time):
        return data.strftime('%H:%M:%S')
    elif isinstance(data, timedelta):
        return str(data)
    elif isinstance(data, Decimal):
        return float(data)
    elif isinstance(data, (bytes, bytearray)):
        return data.decode('utf-8', errors='ignore')
    else:
        return data

def sync_attendance_records():
    """Sync recent attendance records"""
    log_message("🔄 Starting attendance sync...")
    
    logs, daily = get_unsynced_attendance()
    
    synced_count = 0
    
    # Sync attendance logs
    for log in logs:
        data = convert_to_json_serializable(log)
        # Remove id field to avoid conflicts
        data.pop('id', None)
        
        if sync_to_cloud('attendance_logs', data, 'insert'):
            synced_count += 1
    
    # Sync daily attendance
    for record in daily:
        data = convert_to_json_serializable(record)
        data.pop('id', None)
        
        # Check if record exists, update or insert
        where = f"employee_id = '{record['employee_id']}' AND attendance_date = '{record['attendance_date']}'"
        
        if sync_to_cloud('daily_attendance', data, 'insert'):
            synced_count += 1
    
    log_message(f"✅ Sync completed: {synced_count} records synced")
    return synced_count

def sync_employees():
    """Sync employee records that were recently updated"""
    try:
        conn = mysql.connector.connect(**LOCAL_DB_CONFIG)
        cursor = conn.cursor(dictionary=True)
        
        # Get recently updated employees
        query = """
            SELECT * FROM employees 
            WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            OR created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        """
        cursor.execute(query)
        employees = cursor.fetchall()
        
        cursor.close()
        conn.close()
        
        synced_count = 0
        for emp in employees:
            data = convert_to_json_serializable(emp)
            data.pop('id', None)
            
            if sync_to_cloud('employees', data, 'insert'):
                synced_count += 1
        
        if synced_count > 0:
            log_message(f"✅ Synced {synced_count} employee records")
        
        return synced_count
        
    except mysql.connector.Error as e:
        log_message(f"❌ Database error syncing employees: {str(e)}")
        return 0

def get_cloud_employee_id(local_employee_id, conn):
    """Get IONOS employee.id from localhost employee.id by matching employee_id string"""
    try:
        cursor = conn.cursor(dictionary=True)
        
        # Get the employee_id string from localhost
        cursor.execute("SELECT employee_id FROM employees WHERE id = %s", (local_employee_id,))
        result = cursor.fetchone()
        
        if not result:
            return None
        
        employee_id_string = result['employee_id']
        
        # We'll need to query IONOS to get the matching id, but we can't do that from here
        # Instead, return the employee_id string for reference-based sync
        return employee_id_string
        
    except Exception as e:
        log_message(f"❌ Error mapping employee ID: {str(e)}")
        return None

def get_cloud_schedule_id(local_schedule_id, conn):
    """Get IONOS schedule.id from localhost schedule.id by matching schedule_name"""
    try:
        cursor = conn.cursor(dictionary=True)
        
        # Get the schedule_name from localhost
        cursor.execute("SELECT schedule_name FROM schedules WHERE id = %s", (local_schedule_id,))
        result = cursor.fetchone()
        
        if not result:
            return None
        
        return result['schedule_name']
        
    except Exception as e:
        log_message(f"❌ Error mapping schedule ID: {str(e)}")
        return None

def sync_schedules():
    """Sync schedule-related tables including FK tables with lookup"""
    try:
        conn = mysql.connector.connect(**LOCAL_DB_CONFIG)
        cursor = conn.cursor(dictionary=True)
        synced_count = 0
        
        # Sync schedules (only from last hour to avoid re-syncing old data)
        cursor.execute("SELECT * FROM schedules WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)")
        schedules = cursor.fetchall()
        for record in schedules:
            data = convert_to_json_serializable(record)
            # Keep the id for schedule_periods to reference
            schedule_local_id = data['id']
            data.pop('id', None)
            if sync_to_cloud('schedules', data, 'insert'):
                synced_count += 1
        
        # Sync schedule_periods (only for recently created schedules)
        if schedules:
            schedule_ids = [s['id'] for s in schedules]
            placeholders = ','.join(['%s'] * len(schedule_ids))
            cursor.execute(f"SELECT * FROM schedule_periods WHERE schedule_id IN ({placeholders})", schedule_ids)
            for record in cursor.fetchall():
                data = convert_to_json_serializable(record)
                data.pop('id', None)
                if sync_to_cloud('schedule_periods', data, 'insert'):
                    synced_count += 1
        
        # NOW SYNC employee_schedules with lookup
        cursor.execute("""
            SELECT es.*, e.employee_id as employee_id_string, s.schedule_name
            FROM employee_schedules es
            JOIN employees e ON es.employee_id = e.id
            JOIN schedules s ON es.schedule_id = s.id
            WHERE es.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        """)
        employee_schedules_records = cursor.fetchall()
        log_message(f"ℹ️  Found {len(employee_schedules_records)} employee_schedules records to sync")
        
        for record in employee_schedules_records:
            data = {
                'employee_id_string': record['employee_id_string'],
                'schedule_name': record['schedule_name'],
                'effective_date': convert_to_json_serializable(record['effective_date']),
                'is_active': record['is_active']
            }
            if sync_with_lookup('employee_schedules', data):
                synced_count += 1
        
        # NOW SYNC employee_assignments with lookup
        cursor.execute("""
            SELECT ea.*, e.employee_id as employee_id_string, s.schedule_name,
                   sp.day_of_week, sp.start_time, sp.end_time
            FROM employee_assignments ea
            JOIN employees e ON ea.employee_id = e.id
            JOIN schedule_periods sp ON ea.schedule_period_id = sp.id
            JOIN schedules s ON sp.schedule_id = s.id
            WHERE ea.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        """)
        for record in cursor.fetchall():
            data = {
                'employee_id_string': record['employee_id_string'],
                'schedule_name': record['schedule_name'],
                'day_of_week': record['day_of_week'],
                'start_time': convert_to_json_serializable(record['start_time']),
                'end_time': convert_to_json_serializable(record['end_time']),
                'subject_code': record.get('subject_code', ''),
                'designate_class': record.get('designate_class', ''),
                'room_num': record.get('room_num', ''),
                'is_active': record['is_active']
            }
            if sync_with_lookup('employee_assignments', data):
                synced_count += 1
        
        cursor.close()
        conn.close()
        
        if synced_count > 0:
            log_message(f"✅ Synced {synced_count} schedule records")
        
        return synced_count
        
    except mysql.connector.Error as e:
        log_message(f"❌ Database error syncing schedules: {str(e)}")
        return 0

def sync_all_tables():
    """Sync all other tables"""
    try:
        conn = mysql.connector.connect(**LOCAL_DB_CONFIG)
        cursor = conn.cursor(dictionary=True)
        synced_count = 0
        
        # Sync holidays
        cursor.execute("SELECT * FROM holidays WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")
        for record in cursor.fetchall():
            data = convert_to_json_serializable(record)
            data.pop('id', None)
            if sync_to_cloud('holidays', data, 'insert'):
                synced_count += 1
        
        # Sync leave_types
        cursor.execute("SELECT * FROM leave_types WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")
        for record in cursor.fetchall():
            data = convert_to_json_serializable(record)
            data.pop('id', None)
            if sync_to_cloud('leave_types', data, 'insert'):
                synced_count += 1
        
        # Sync employee_leaves
        cursor.execute("SELECT * FROM employee_leaves WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) OR updated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)")
        for record in cursor.fetchall():
            data = convert_to_json_serializable(record)
            data.pop('id', None)
            if sync_to_cloud('employee_leaves', data, 'insert'):
                synced_count += 1
        
        cursor.close()
        conn.close()
        
        if synced_count > 0:
            log_message(f"✅ Synced {synced_count} other records")
        
        return synced_count
        
    except mysql.connector.Error as e:
        log_message(f"❌ Database error syncing other tables: {str(e)}")
        return 0

def main():
    """Main sync loop"""
    log_message("🚀 Auto-Sync Script Started")
    log_message(f"📡 Syncing to: {API_URL}")
    log_message(f"⏱️ Sync interval: {SYNC_INTERVAL} seconds")
    log_message("-" * 60)
    
    while True:
        try:
            # Sync all tables
            sync_employees()
            sync_schedules()
            sync_attendance_records()
            sync_all_tables()
            
            # Wait before next sync
            log_message(f"⏳ Waiting {SYNC_INTERVAL} seconds until next sync...\n")
            time.sleep(SYNC_INTERVAL)
            
        except KeyboardInterrupt:
            log_message("🛑 Auto-Sync stopped by user")
            break
        except Exception as e:
            log_message(f"❌ Unexpected error: {str(e)}")
            log_message(f"⏳ Retrying in {SYNC_INTERVAL} seconds...\n")
            time.sleep(SYNC_INTERVAL)

if __name__ == "__main__":
    main()
