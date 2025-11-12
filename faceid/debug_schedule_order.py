# -*- coding: utf-8 -*-
"""Debug schedule period ordering"""
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

conn = get_db_connection()
cursor = conn.cursor()

now = datetime.now()
day_of_week = now.weekday()

print("=" * 70)
print("SCHEDULE PERIOD ORDER DEBUG")
print("=" * 70)
print(f"Current time: {now.strftime('%Y-%m-%d %H:%M:%S')}")
print(f"Day of week: {day_of_week} (0=Monday)")

# Get schedule periods with ORDER BY start_time ASC
print("\n" + "=" * 70)
print("Query with ORDER BY CAST(sp.start_time AS TIME) ASC:")
print("=" * 70)

cursor.execute("""
    SELECT sp.start_time, sp.end_time, sp.period_name
    FROM employee_schedules es
    JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
    WHERE es.employee_id = 1
      AND es.is_active = 1
      AND sp.day_of_week = ?
      AND sp.is_active = 1
    ORDER BY CAST(sp.start_time AS TIME) ASC
""", (day_of_week,))

periods = cursor.fetchall()
print(f"\nFound {len(periods)} periods:")
for i, p in enumerate(periods):
    print(f"  [{i}] {p[0]} to {p[1]} - {p[2]}")
    if i == 0:
        print(f"      ^^^ FIRST PERIOD (used for late calculation)")

conn.close()

print("\n" + "=" * 70)
print("ANALYSIS:")
print("=" * 70)
if periods:
    first = periods[0]
    print(f"First period start: {first[0]}")
    
    # Calculate late minutes from first period
    start_hour, start_minute, _ = map(int, first[0].split(':'))
    scheduled_datetime = now.replace(
        hour=start_hour,
        minute=start_minute,
        second=0,
        microsecond=0
    )
    
    time_diff = (now - scheduled_datetime).total_seconds() / 60
    late_minutes = int(time_diff) if time_diff > 0 else 0
    
    print(f"Current time: {now.strftime('%H:%M:%S')}")
    print(f"Time difference: {time_diff:.1f} minutes")
    print(f"Late minutes: {late_minutes}")
    
    # What should it be?
    print("\n" + "-" * 70)
    print("EXPECTED CALCULATION:")
    print("-" * 70)
    print("Schedule: 07:00:00 to 12:00:00 (first period)")
    print("          23:00:00 to 23:59:00 (second period)")
    print(f"Time in:  {now.strftime('%H:%M:%S')}")
    print("Should calculate: 23:45 - 07:00 = 16 hours 45 min = ~1005 minutes")

print("\n" + "=" * 70)
