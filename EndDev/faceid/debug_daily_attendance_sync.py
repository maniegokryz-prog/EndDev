"""
Debug script to check SQLite daily_attendance records and compare with MySQL
"""
import sqlite3
import pymysql
import os
import sys

# Add database directory to path
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DB_DIR = os.path.join(SCRIPT_DIR, "database")
sys.path.insert(0, DB_DIR)

from init_local_db import DB_PATH

print("\n" + "=" * 100)
print("SQLite Daily Attendance Records")
print("=" * 100)

try:
    # Check SQLite
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    cursor.execute("""
        SELECT id, employee_id, attendance_date, time_in, time_out,
               scheduled_hours, actual_hours, late_minutes,
               early_departure_minutes, overtime_minutes, status, calculated_at
        FROM daily_attendance
        ORDER BY attendance_date DESC, employee_id ASC
    """)
    
    sqlite_rows = cursor.fetchall()
    
    if sqlite_rows:
        print(f"\nTotal SQLite records: {len(sqlite_rows)}\n")
        for row in sqlite_rows:
            print(f"SQLite Record ID: {row[0]}")
            print(f"  Employee ID: {row[1]}")
            print(f"  Date: {row[2]}")
            print(f"  Time In: {row[3]}")
            print(f"  Time Out: {row[4]}")
            print(f"  Scheduled: {row[5]}h | Actual: {row[6]}h")
            print(f"  Late: {row[7]}min | Early: {row[8]}min | Overtime: {row[9]}min")
            print(f"  Status: {row[10]}")
            print(f"  Calculated At: {row[11]}")
            print()
    else:
        print("\nNo records in SQLite")
    
    conn.close()
    
except Exception as e:
    print(f"SQLite Error: {e}")

print("=" * 100)
print("MySQL Daily Attendance Records")
print("=" * 100)

try:
    # Check MySQL
    mysql_conn = pymysql.connect(
        host='localhost',
        user='root',
        password='Confirmp@ssword123',
        database='database_records'
    )
    
    mysql_cursor = mysql_conn.cursor()
    
    mysql_cursor.execute("""
        SELECT id, employee_id, attendance_date, time_in, time_out,
               scheduled_hours, actual_hours, late_minutes,
               early_departure_minutes, overtime_minutes, status, calculated_at
        FROM daily_attendance
        ORDER BY attendance_date DESC, employee_id ASC
    """)
    
    mysql_rows = mysql_cursor.fetchall()
    
    if mysql_rows:
        print(f"\nTotal MySQL records: {len(mysql_rows)}\n")
        for row in mysql_rows:
            print(f"MySQL Record ID: {row[0]}")
            print(f"  Employee ID: {row[1]}")
            print(f"  Date: {row[2]}")
            print(f"  Time In: {row[3]}")
            print(f"  Time Out: {row[4]}")
            print(f"  Scheduled: {row[5]}h | Actual: {row[6]}h")
            print(f"  Late: {row[7]}min | Early: {row[8]}min | Overtime: {row[9]}min")
            print(f"  Status: {row[10]}")
            print(f"  Calculated At: {row[11]}")
            print()
    else:
        print("\nNo records in MySQL")
    
    mysql_conn.close()
    
except Exception as e:
    print(f"MySQL Error: {e}")

print("=" * 100)
print("Comparison Summary")
print("=" * 100)
print(f"SQLite Records: {len(sqlite_rows) if 'sqlite_rows' in locals() else 0}")
print(f"MySQL Records: {len(mysql_rows) if 'mysql_rows' in locals() else 0}")

if 'sqlite_rows' in locals() and 'mysql_rows' in locals():
    if len(sqlite_rows) > len(mysql_rows):
        print(f"\n⚠️  SQLite has {len(sqlite_rows) - len(mysql_rows)} more record(s) than MySQL")
        print("   Need to run sync to push missing records")
    elif len(sqlite_rows) < len(mysql_rows):
        print(f"\n⚠️  MySQL has {len(mysql_rows) - len(sqlite_rows)} more record(s) than SQLite")
    else:
        print("\n✅ Record counts match")

print("=" * 100 + "\n")
