
import os
import sys
import time
from datetime import datetime, timedelta

# Add script dir to path
script_dir = r"c:\inetpub\wwwroot\EndDev\faceid"
sys.path.append(script_dir)

from attendance_logger import AttendanceLogger

def run_verification():
    logger = AttendanceLogger()
    
    # Use a dummy employee ID for testing
    try:
        from database.init_local_db import get_db_connection
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("SELECT id, employee_id, first_name FROM employees LIMIT 1")
        emp = cursor.fetchone()
        conn.close()
        
        if not emp:
            print("No employees found in DB to test with.")
            return
            
        emp_id = emp[0]
        emp_code = emp[1]
        print(f"Testing with Employee: {emp[1]} ({emp[2]}) [DB ID: {emp_id}]")
        
        # 1. Simulate Time In (Force success by ignoring cooldown)
        print("\n--- Test 1: Log Time In ---")
        res1 = logger.log_attendance(emp_id, log_type='time_in', cooldown_minutes=0, min_work_minutes=0, one_session_per_day=False)
        print(f"Result 1: {res1.get('status', 'success') if not res1.get('success') else 'success'} - {res1.get('message')}")

        # 2. Try to Time Out immediately (Should be blocked by Min Duration = 60 mins)
        print("\n--- Test 2: Early Time Out (Expect Block) ---")
        res2 = logger.log_attendance(emp_id, log_type='time_out', cooldown_minutes=0, min_work_minutes=60, one_session_per_day=True)
        print(f"Result 2: {res2.get('status', 'success') if not res2.get('success') else 'success'} - {res2.get('message')}")
        
        if res2.get('status') == 'too_early':
             print("✅ PASS: Min Duration blocked correctly")
        else:
             print("❌ FAIL: Min Duration check failed")

        # 3. Simulate Valid Time Out (Force min duration = 0 to allow it)
        print("\n--- Test 3: Valid Time Out ---")
        res3 = logger.log_attendance(emp_id, log_type='time_out', cooldown_minutes=0, min_work_minutes=0, one_session_per_day=True)
        print(f"Result 3: {res3.get('status', 'success') if not res3.get('success') else 'success'} - {res3.get('message')}")
        
        # 4. Try to Log In Again (Should be blocked by Single Session)
        print("\n--- Test 4: Re-Entry (Expect Block) ---")
        res4 = logger.log_attendance(emp_id, log_type='time_in', cooldown_minutes=0, min_work_minutes=0, one_session_per_day=True)
        print(f"Result 4: {res4.get('status', 'success') if not res4.get('success') else 'success'} - {res4.get('message')}")

        if res4.get('status') == 'completed':
             print("✅ PASS: Single Session blocked correctly")
        else:
             print("❌ FAIL: Single Session check failed")

    except Exception as e:
        print(f"Test Failed: {e}")

if __name__ == "__main__":
    run_verification()
