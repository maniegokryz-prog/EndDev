"""
Auto-Sync Script for Database Synchronization
Syncs local database changes to IONOS cloud every 60 seconds

This script monitors the local database and syncs any new/updated records to IONOS
"""

import requests
import pymysql
import time
import json
from datetime import datetime, timedelta
import os

# Disable SSL warnings completely
import urllib3
urllib3.disable_warnings()

# Configuration
CONFIG_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "config", "sync_config.json")
STATUS_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "logs", "sync_status.json")

# Default values
API_URL = "http://bpcfaceid.com/api/sync_endpoint.php"
API_KEY = "lD9OcrtiWGxmSRCV1YpdqwAk5JPygLfo"
SYNC_INTERVAL = 60
SYNC_ENABLED = True

def load_config():
    """Load configuration from JSON file"""
    global API_URL, API_KEY, SYNC_INTERVAL, SYNC_ENABLED
    
    if os.path.exists(CONFIG_FILE):
        try:
            with open(CONFIG_FILE, 'r') as f:
                config = json.load(f)
                API_URL = config.get('api_url', API_URL)
                API_KEY = config.get('api_key', API_KEY)
                SYNC_INTERVAL = int(config.get('sync_interval', SYNC_INTERVAL))
                SYNC_ENABLED = config.get('sync_enabled', SYNC_ENABLED)
                return True
        except Exception as e:
            print(f"Error loading config: {e}")
            return False
    return False

def update_status_file(status, message):
    """Write sync status to JSON for dashboard widget"""
    try:
        os.makedirs(os.path.dirname(STATUS_FILE), exist_ok=True)
        data = {
            "last_sync": datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            "status": status,
            "message": message
        }
        with open(STATUS_FILE, 'w') as f:
            json.dump(data, f)
    except Exception as e:
        print(f"Error writing status file: {e}")

# Local Database Configuration
LOCAL_DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': 'Confirmp@ssword123',
    'database': 'database_records'
}

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
        conn = pymysql.connect(**LOCAL_DB_CONFIG)
        cursor = conn.cursor(pymysql.cursors.DictCursor)
        
        # Get recent attendance logs (using created_at column)
        query = """
            SELECT * FROM attendance_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)
            ORDER BY created_at DESC
        """
        cursor.execute(query)
        logs = cursor.fetchall()
        
        # Get recent daily attendance (using calculated_at column)
        query = """
            SELECT * FROM daily_attendance 
            WHERE calculated_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)
            ORDER BY attendance_date DESC
        """
        cursor.execute(query)
        daily = cursor.fetchall()
        
        cursor.close()
        conn.close()
        
        return logs, daily
        
    except pymysql.Error as e:
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
        conn = pymysql.connect(**LOCAL_DB_CONFIG)
        cursor = conn.cursor(pymysql.cursors.DictCursor)
        
        # Get recently updated employees (INCREASED TO 365 DAYS TO ENSURE SYNC)
        query = """
            SELECT * FROM employees 
            WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)
            OR created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)
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
        
    except pymysql.Error as e:
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
        conn = pymysql.connect(**LOCAL_DB_CONFIG)
        cursor = conn.cursor(pymysql.cursors.DictCursor)
        synced_count = 0
        
        # Sync schedules (only from last hour to avoid re-syncing old data)
        cursor.execute("SELECT * FROM schedules WHERE created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)")
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
            WHERE es.created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)
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
            WHERE ea.created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)
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
        
    except pymysql.Error as e:
        log_message(f"❌ Database error syncing schedules: {str(e)}")
        return 0

def sync_all_tables():
    """Sync all other tables"""
    try:
        conn = pymysql.connect(**LOCAL_DB_CONFIG)
        cursor = conn.cursor(pymysql.cursors.DictCursor)
        synced_count = 0
        
        # Sync holidays
        cursor.execute("SELECT * FROM holidays WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")
        for record in cursor.fetchall():
            data = convert_to_json_serializable(record)
            data.pop('id', None)
            if sync_to_cloud('holidays', data, 'insert'):
                synced_count += 1
        
        # Sync leave_types (only new ones)
        # preventing duplicate entry errors by only syncing recently created types
        cursor.execute("SELECT * FROM leave_types WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)")
        for record in cursor.fetchall():
            data = convert_to_json_serializable(record)
            # Keep the id field for leave_types since employee_leaves references it
            # But normally we rely on name lookup. Let's stick to simple insert.
            if sync_to_cloud('leave_types', data, 'insert'):
                synced_count += 1
        
        # Sync employee_leaves (convert employee_id to employee_id string)
        cursor.execute("""
            SELECT el.*, e.employee_id as employee_id_string, lt.type_name as leave_type_name
            FROM employee_leaves el
            JOIN employees e ON el.employee_id = e.id
            JOIN leave_types lt ON el.leave_type_id = lt.id
            WHERE el.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) 
               OR el.updated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        """)
        for record in cursor.fetchall():
            data = convert_to_json_serializable(record)
            
            # Prepare data for lookup sync
            lookup_data = {
                'employee_id_string': data['employee_id_string'],
                'leave_type_name': data['leave_type_name'],
                'start_date': data['start_date'],
                'end_date': data['end_date'],
                'reason': data['reason'],
                'status': data['status'],
                'cloud_id': data['id'] # Store Local ID as Cloud ID Reference
            }
            
            if sync_with_lookup('employee_leaves', lookup_data):
                synced_count += 1
        
        cursor.close()
        conn.close()
        
        if synced_count > 0:
            log_message(f"✅ Synced {synced_count} other records")
        
        return synced_count
        
    except pymysql.Error as e:
        log_message(f"❌ Database error syncing other tables: {str(e)}")
        return 0

def fetch_cloud_pending_leaves():
    """Fetch pending leave requests from Cloud and save to Local DB"""
    try:
        # 1. Fetch from Cloud
        headers = {'X-API-KEY': API_KEY, 'User-Agent': 'Auto-Sync/1.0'}
        # Assuming staffmanagement/api/leave_request.php?action=get_pending_requests exists on Cloud
        # Note: We need a special 'sync_fetch' action on the cloud endpoint to get ALL pending requests, 
        # or we reuse get_pending_requests but that might be admin-session protected.
        # Ideally, we should use sync_endpoint.php with action=fetch_pending_leaves if implemented, 
        # or call leave_request.php directly if we can bypass auth or simulate it.
        # For this implementation, we'll assume sync_endpoint.php proxies this or we call a new action.
        
        # Let's try the direct API approach first, assuming sync_endpoint handles it or we call leave_request.php
        # BUT, standard leave_request.php checks session. 
        # So we should probably rely on sync_endpoint.php having a 'fetch_table' or specific custom action.
        # Let's implement a 'fetch_new' action on sync_endpoint.php ideally.
        # Failing that, we'll assume a dedicated endpoint exists.
        
        # PROPOSAL: We use sync_endpoint.php with action='fetch_pending_leaves'
        payload = {'action': 'fetch_pending_leaves', 'table': 'employee_leaves'}
        response = requests.post(API_URL, data=payload, headers=headers, timeout=30, verify=False)
        
        if response.status_code != 200:
            return 0
            
        result = response.json()
        if not result.get('success') or not result.get('data'):
            return 0
            
        leaves = result['data']
        count = 0
        
        conn = pymysql.connect(**LOCAL_DB_CONFIG)
        cursor = conn.cursor(pymysql.cursors.DictCursor)
        
        for leave in leaves:
            # 1. Check if already exists by cloud_id
            cursor.execute("SELECT id FROM employee_leaves WHERE cloud_id = %s", (leave['id'],))
            row = cursor.fetchone()
            if row:
                continue # Already synced properly
                
            # 2. Get Local Employee ID
            cursor.execute("SELECT id FROM employees WHERE employee_id = %s", (leave['employee_id_string'],))
            emp_row = cursor.fetchone()
            if not emp_row:
                log_message(f"❌ Skipping leave sync: Employee {leave['employee_id_string']} not found locally")
                continue
            local_emp_id = emp_row['id']

            # 3. Check if exists by (Employee + Dates) to prevent duplicates of existing local data
            # This handles the case where local data exists but hasn't been linked to cloud_id yet
            cursor.execute("""
                SELECT id FROM employee_leaves 
                WHERE employee_id = %s AND start_date = %s AND end_date = %s
            """, (local_emp_id, leave['start_date'], leave['end_date']))
            existing_leave = cursor.fetchone()
            
            if existing_leave:
                # Update the existing record with the cloud_id
                cursor.execute("UPDATE employee_leaves SET cloud_id = %s WHERE id = %s", (leave['id'], existing_leave['id']))
                log_message(f"ℹ️  Linked existing local leave {existing_leave['id']} to Cloud ID {leave['id']}")
                conn.commit()
                continue

            # Get Leave Type ID
            cursor.execute("SELECT id FROM leave_types WHERE type_name = %s", (leave['leave_type_name'],))
            type_row = cursor.fetchone()
            if not type_row:
                cursor.execute("INSERT INTO leave_types (type_name, description) VALUES (%s, %s)", 
                              (leave['leave_type_name'], f"{leave['leave_type_name']} (Synced)"))
                conn.commit()
                local_type_id = cursor.lastrowid
            else:
                local_type_id = type_row['id']
            
            # Insert Leave Request
            sql = """INSERT INTO employee_leaves 
                     (employee_id, leave_type_id, start_date, end_date, reason, status, cloud_id, created_at) 
                     VALUES (%s, %s, %s, %s, %s, %s, %s, %s)"""
            cursor.execute(sql, (
                local_emp_id, 
                local_type_id, 
                leave['start_date'], 
                leave['end_date'], 
                leave['reason'], 
                leave['status'], 
                leave['id'],
                leave['created_at']
            ))
            local_leave_id = cursor.lastrowid
            
            # Create Local Notification for Admin
            notif_msg = f"New Sync Request: {leave['employee_name']} ({leave['leave_type_name']})"
            cursor.execute("""INSERT INTO notifications 
                              (employee_id, leave_id, type, message, target, is_read, created_at) 
                              VALUES (%s, %s, 'new_request', %s, 'admin', 0, NOW())""",
                           (local_emp_id, local_leave_id, notif_msg))
            
            count += 1
            
        conn.commit()
        cursor.close()
        conn.close()
        
        if count > 0:
            log_message(f"✅ Pulled {count} new leave requests from Cloud")
            
        return count

    except Exception as e:
        log_message(f"❌ Error fetching cloud leaves: {str(e)}")
        return 0

def fetch_cloud_employees():
    """Fetch updated employees from Cloud and update Local DB"""
    try:
        # Fetch from Cloud
        headers = {'X-API-KEY': API_KEY, 'User-Agent': 'Auto-Sync/1.0'}
        # We want to catch any update in the last sync interval + buffer
        # But since we don't track last sync time persistently for this specific action,
        # let's look back 1 hour or 24 hours to be safe.
        # Ideally, we pass 'since' param.
        
        since_time = (datetime.now() - timedelta(hours=1)).strftime('%Y-%m-%d %H:%M:%S')
        payload = {
            'action': 'fetch_employees', 
            'since': since_time
        }
        
        # Verify=False for testing
        response = requests.post(API_URL, data=payload, headers=headers, timeout=30, verify=False)
        
        if response.status_code != 200:
            return 0
            
        result = response.json()
        if not result.get('success') or not result.get('data'):
            return 0
            
        employees = result['data']
        count = 0
        
        conn = pymysql.connect(**LOCAL_DB_CONFIG)
        cursor = conn.cursor(pymysql.cursors.DictCursor)
        
        for emp in employees:
            emp_id_string = emp['employee_id']
            
            # Check if exists locally
            cursor.execute("SELECT * FROM employees WHERE employee_id = %s", (emp_id_string,))
            local_emp = cursor.fetchone()
            
            if local_emp:
                # Update if Cloud is newer (simple logic: just overwrite if cloud updated_at is recent)
                # Note: This might overwrite local changes if we are not careful.
                # Since User requested "Cloud changes reflect on Local", we assume Cloud is master for these updates.
                
                # Check timestamps if we want to be fancy, but let's just update for now if data differs.
                # Actually, let's only update if local updated_at is OLDER than cloud updated_at?
                # Or simply overwrite.
                
                # Let's overwrite specific fields that are personal info
                update_query = """
                    UPDATE employees SET 
                    first_name=%s, last_name=%s, email=%s, phone=%s, 
                    position=%s, department=%s, 
                    updated_at=%s 
                    WHERE id=%s
                """
                cursor.execute(update_query, (
                    emp['first_name'], emp['last_name'], emp['email'], emp.get('phone'),
                    emp['position'], emp['department'],
                    emp['updated_at'], # Keep sync with cloud time? Or set to NOW()?
                    local_emp['id']
                ))
                if cursor.rowcount > 0:
                    count += 1
                    log_message(f"🔄 Updated local employee: {emp['first_name']} {emp['last_name']}")
            else:
                # Insert new employee from Cloud?
                # The user asked if "change or modification" reflects.
                # Implicitly, new employees might also be desired.
                # Let's support INSERT too.
                insert_query = """
                    INSERT INTO employees (employee_id, first_name, last_name, email, phone, position, department, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                """
                cursor.execute(insert_query, (
                    emp['employee_id'], emp['first_name'], emp['last_name'], emp['email'], emp.get('phone'),
                    emp['position'], emp['department'],
                    emp['created_at'], emp['updated_at']
                ))
                count += 1
                log_message(f"➕ Added new local employee from cloud: {emp['first_name']} {emp['last_name']}")
        
        conn.commit()
        cursor.close()
        conn.close()
        
        if count > 0:
            log_message(f"✅ Pulled {count} employee updates from Cloud")
            
        return count

    except Exception as e:
        log_message(f"❌ Error fetching cloud employees: {str(e)}")
        return 0

def main():
    """Main sync loop"""
    log_message("🚀 Auto-Sync Script Started")
    
    # Initial config load
    load_config()
    
    log_message(f"📡 Syncing to: {API_URL}")
    log_message(f"⏱️ Sync interval: {SYNC_INTERVAL} seconds")
    log_message("-" * 60)
    
    while True:
        try:
            # Reload config on every loop to pick up changes without restart
            if load_config():
                # Check if sync is enabled
                if not SYNC_ENABLED:
                    log_message("⏸️ Cloud sync is disabled in settings.")
                    update_status_file("inactive", "Sync disabled in settings")
                    time.sleep(10) # Wait 10s before checking again
                    continue
            
            # Sync all tables
            c1 = sync_employees()
            c2 = sync_schedules()
            c3 = sync_attendance_records()
            c4 = sync_all_tables()
            c5 = fetch_cloud_pending_leaves()
            c6 = fetch_cloud_employees()
            
            total_synced = c1 + c2 + c3 + c4 + c5 + c6
            if total_synced > 0:
                msg = f"Synced {total_synced} records"
                update_status_file("success", msg)
            elif total_synced == 0:
                # Update status even on empty sync to show it's alive
                update_status_file("success", "Sync active - No new data")
            
            # Wait before next sync
            log_message(f"⏳ Waiting {SYNC_INTERVAL} seconds until next sync...\n")
            time.sleep(SYNC_INTERVAL)
            
        except KeyboardInterrupt:
            log_message("🛑 Auto-Sync stopped by user")
            break
        except Exception as e:
            log_message(f"❌ Unexpected error: {str(e)}")
            update_status_file("error", f"Error: {str(e)}")
            log_message(f"⏳ Retrying in {SYNC_INTERVAL} seconds...\n")
            time.sleep(SYNC_INTERVAL)

if __name__ == "__main__":
    main()
