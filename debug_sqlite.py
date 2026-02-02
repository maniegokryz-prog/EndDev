import sqlite3
import os

DB_PATH = 'c:/inetpub/wwwroot/EndDev/faceid/database/kiosk_local.db'

def inspect_db():
    if not os.path.exists(DB_PATH):
        print(f"Database not found at {DB_PATH}")
        return

    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()

    print("--- Recent Attendance Logs ---")
    cursor.execute("SELECT id, employee_id, log_date, log_time, log_type FROM attendance_logs ORDER BY id DESC LIMIT 5")
    for row in cursor.fetchall():
        print(row)

    print("\n--- Recent Daily Attendance ---")
    cursor.execute("SELECT id, time_in, time_out, actual_hours, status FROM daily_attendance ORDER BY id DESC LIMIT 5")
    for row in cursor.fetchall():
        print(f"ID: {row[0]}, In: {row[1]}, Out: {row[2]}, Hrs: {row[3]}, Stat: {row[4]}")
    
    conn.close()

if __name__ == "__main__":
    inspect_db()
