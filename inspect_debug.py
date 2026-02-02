import sqlite3

def inspect():
    conn = sqlite3.connect('c:/inetpub/wwwroot/EndDev/faceid/database/kiosk_local.db')
    cursor = conn.cursor()
    
    print("Inspecting Record...")
    # Get last daily attendance
    cursor.execute("SELECT id, time_in, typeof(time_in), time_out, typeof(time_out), actual_hours, status FROM daily_attendance ORDER BY id DESC LIMIT 1")
    row = cursor.fetchone()
    if row:
        print(f"ID: {row[0]}")
        print(f"TimeIn: {repr(row[1])} (Type: {row[2]})")
        print(f"TimeOut: {repr(row[3])} (Type: {row[4]})")
        print(f"ActualHours: {row[5]}")
        print(f"Status: {row[6]}")
    else:
        print("No records found.")
    conn.close()

if __name__ == "__main__":
    inspect()
