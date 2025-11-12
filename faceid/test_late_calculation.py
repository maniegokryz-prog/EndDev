# -*- coding: utf-8 -*-
"""Test the late minutes calculation with multiple periods"""
import sys
import os
from datetime import datetime

# Add the database directory to Python path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'database'))

# Configure UTF-8 output for Windows console
if sys.platform == 'win32':
    import codecs
    sys.stdout = codecs.getwriter('utf-8')(sys.stdout.buffer, 'strict')
    sys.stderr = codecs.getwriter('utf-8')(sys.stderr.buffer, 'strict')

from attendance_logger import get_logger

print("=" * 70)
print("TESTING LATE MINUTES CALCULATION")
print("=" * 70)

logger = get_logger()

# Test with employee MA22013612 (Justine Alianza)
# Schedule: 7:00 AM - 12:00 PM and 11:00 PM - 11:59 PM

# Simulate time-in at 11:38 PM (current time)
# Should calculate against 11:00 PM start (38 minutes late)
# NOT against 7:00 AM start (991 minutes late)

print("\nEmployee: MA22013612 (Justine Alianza)")
print("Schedule Periods:")
print("  - 7:00 AM to 12:00 PM")
print("  - 11:00 PM to 11:59 PM")
print("\nTest Scenario: Time-in at 11:38 PM")
print("Expected: Late by ~38 minutes (compared to 11:00 PM)")
print("Previously: Late by ~991 minutes (compared to 7:00 AM)")

# Get employee info
emp = logger.get_employee_by_code('MA22013612')
if emp:
    print(f"\nFound employee: {emp['full_name']} (DB ID: {emp['db_id']})")
    
    # Log attendance (this will show the calculation)
    print("\n" + "-" * 70)
    print("Logging attendance...")
    print("-" * 70)
    result = logger.log_attendance(emp['db_id'], log_type='time_in')
    
    print("\n" + "-" * 70)
    print("RESULT:")
    print("-" * 70)
    if result['success']:
        print(f"✅ Success!")
        print(f"   Log Type: {result['log_type']}")
        print(f"   Log Time: {result['log_time']}")
        print(f"   Status: {result['notes']}")
        print(f"   Message: {result['message']}")
    else:
        print(f"❌ Failed: {result['message']}")
    
    # Check the daily_attendance record
    print("\n" + "-" * 70)
    print("Checking daily_attendance table...")
    print("-" * 70)
    
    from init_local_db import get_db_connection
    conn = get_db_connection()
    cursor = conn.cursor()
    
    today = datetime.now().strftime('%Y-%m-%d')
    cursor.execute("""
        SELECT attendance_date, time_in, late_minutes, status
        FROM daily_attendance
        WHERE employee_id = ? AND attendance_date = ?
    """, (emp['db_id'], today))
    
    record = cursor.fetchone()
    if record:
        print(f"Date: {record[0]}")
        print(f"Time In: {record[1]}")
        print(f"Late Minutes: {record[2]}")
        print(f"Status: {record[3]}")
        
        if record[2] and record[2] < 100:
            print("\n✅ CORRECT! Late minutes is reasonable for 11:00 PM start time")
        else:
            print(f"\n⚠️  Late minutes seems incorrect (expected ~38, got {record[2]})")
    else:
        print("No record found in daily_attendance table")
    
    conn.close()
else:
    print("\n❌ Employee MA22013612 not found in database")

print("\n" + "=" * 70)
