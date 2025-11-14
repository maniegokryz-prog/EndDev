# -*- coding: utf-8 -*-
"""Check current day and schedule data"""
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

from init_local_db import get_db_connection

# Check today's date
now = datetime.now()
print("=" * 60)
print("CURRENT DATE/TIME ANALYSIS")
print("=" * 60)
print(f"Current Date/Time: {now.strftime('%A, %B %d, %Y at %I:%M:%S %p')}")
print(f"weekday() value: {now.weekday()} (0=Monday, 1=Tuesday, 2=Wednesday, 3=Thursday, 4=Friday, 5=Saturday, 6=Sunday)")
print(f"isoweekday() value: {now.isoweekday()} (1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday, 7=Sunday)")

# Check what's in the database
print("\n" + "=" * 60)
print("SCHEDULE DATA IN DATABASE")
print("=" * 60)

conn = get_db_connection()
cursor = conn.cursor()

# Check employee schedules
cursor.execute("""
    SELECT e.id, e.employee_id, e.first_name, e.last_name,
           s.schedule_name,
           sp.day_of_week, sp.period_name, sp.start_time, sp.end_time
    FROM employees e
    JOIN employee_schedules es ON e.id = es.employee_id
    JOIN schedules s ON es.schedule_id = s.id
    JOIN schedule_periods sp ON s.id = sp.schedule_id
    WHERE es.is_active = 1 AND sp.is_active = 1
    ORDER BY e.id, sp.day_of_week
""")

schedules = cursor.fetchall()

if schedules:
    print(f"\nFound {len(schedules)} schedule period(s):\n")
    days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
    
    current_emp = None
    for sch in schedules:
        emp_id, emp_code, first_name, last_name, schedule_name, day_of_week, period_name, start_time, end_time = sch
        
        if current_emp != emp_id:
            print(f"\nEmployee: {first_name} {last_name} ({emp_code})")
            print(f"Schedule: {schedule_name}")
            current_emp = emp_id
        
        day_name = days[day_of_week] if 0 <= day_of_week <= 6 else f"Invalid({day_of_week})"
        is_today = "← TODAY!" if day_of_week == now.weekday() else ""
        print(f"  Day {day_of_week} ({day_name}): {start_time} to {end_time} {is_today}")
else:
    print("\nNo active schedules found in database")

# Check if there's a schedule for today
print("\n" + "=" * 60)
print(f"SCHEDULE MATCH FOR TODAY (weekday={now.weekday()})")
print("=" * 60)

cursor.execute("""
    SELECT e.employee_id, e.first_name, e.last_name,
           sp.start_time, sp.end_time, sp.period_name
    FROM employees e
    JOIN employee_schedules es ON e.id = es.employee_id
    JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
    WHERE es.is_active = 1 
      AND sp.day_of_week = ?
      AND sp.is_active = 1
""", (now.weekday(),))

today_schedules = cursor.fetchall()

if today_schedules:
    print(f"\nFound {len(today_schedules)} employee(s) with schedule for today:\n")
    for emp_code, first_name, last_name, start_time, end_time, period_name in today_schedules:
        print(f"  {first_name} {last_name} ({emp_code})")
        print(f"    Scheduled: {start_time} to {end_time}")
        print(f"    Period: {period_name or 'N/A'}")
else:
    print("\n⚠ No schedules found for today!")
    print(f"  Looking for day_of_week = {now.weekday()}")
    print("\nThis means the system will return 'No schedule assigned'")

conn.close()

print("\n" + "=" * 60)
