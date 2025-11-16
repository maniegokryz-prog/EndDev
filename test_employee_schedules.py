import mysql.connector

conn = mysql.connector.connect(
    host='localhost',
    user='root',
    password='Confirmp@ssword123',
    database='database_records'
)

cursor = conn.cursor(dictionary=True)

# Check recent employee_schedules
cursor.execute("""
    SELECT COUNT(*) as count 
    FROM employee_schedules 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
""")
print(f"Recent employee_schedules count: {cursor.fetchone()['count']}")

# Get sample records
cursor.execute("""
    SELECT es.id, e.employee_id, s.schedule_name, es.created_at, es.effective_date, es.is_active
    FROM employee_schedules es
    JOIN employees e ON es.employee_id = e.id
    JOIN schedules s ON es.schedule_id = s.id
    WHERE es.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    LIMIT 5
""")

print("\nSample employee_schedules records:")
for row in cursor.fetchall():
    print(f"  ID: {row['id']}, Employee: {row['employee_id']}, Schedule: {row['schedule_name']}, Created: {row['created_at']}")

cursor.close()
conn.close()
