import pymysql
import sys
import datetime

# Fix encoding
import codecs
sys.stdout = codecs.getwriter('utf-8')(sys.stdout.buffer, 'strict')

MYSQL_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': 'Confirmp@ssword123',
    'database': 'database_records',
    'charset': 'utf8mb4'
}

def check_mysql_data():
    try:
        conn = pymysql.connect(**MYSQL_CONFIG)
        cursor = conn.cursor()
        
        print("Checking MySQL 'daily_attendance' table for recent 5 records:")
        cursor.execute("""
            SELECT id, employee_id, attendance_date, time_in, time_out, actual_hours, status 
            FROM daily_attendance 
            ORDER BY id DESC 
            LIMIT 5
        """)
        
        rows = cursor.fetchall()
        for row in rows:
            # Handle timedelta for time fields
            t_in = row[3]
            t_out = row[4]
            print(f"ID: {row[0]}, Emp: {row[1]}, Date: {row[2]}, In: {t_in}, Out: {t_out}, Hours: {row[5]}, Status: {row[6]}")
            
        conn.close()
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    check_mysql_data()
