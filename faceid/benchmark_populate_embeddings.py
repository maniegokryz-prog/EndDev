import mysql.connector
import numpy as np
import time
import sys
import os

# Database Config
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': 'Confirmp@ssword123',
    'database': 'database_records'
}

COUNT = 1000
DIMENSION = 512

def normalize(v):
    norm = np.linalg.norm(v)
    if norm == 0:
        return v
    return v / norm

def main():
    print(f"Starting Benchmark Population: {COUNT} faces...")
    
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor()
        
        # 1. Cleaning up previous benchmark data
        print("Cleaning up previous benchmark data...")
        # Get IDs to delete from employees
        cursor.execute("SELECT id FROM employees WHERE employee_id LIKE 'BENCHMARK_%'")
        ids = [row[0] for row in cursor.fetchall()]
        
        if ids:
            id_list = ','.join(map(str, ids))
            cursor.execute(f"DELETE FROM face_embeddings WHERE employee_id IN ({id_list})")
            cursor.execute(f"DELETE FROM employees WHERE id IN ({id_list})")
            # Also reset auto_increment if needed, but not critical
            conn.commit()
            print(f"Deleted {len(ids)} old records.")
        
        # 2. Inserting new data
        print(f"Generating {COUNT} synthetic employees and embeddings...")
        
        # Prepare queries - simplified columns
        emp_sql = "INSERT INTO employees (employee_id, first_name, last_name, email, created_at, updated_at) VALUES (%s, %s, %s, %s, NOW(), NOW())"
        emb_sql = "INSERT INTO face_embeddings (employee_id, embedding_data, created_at) VALUES (%s, %s, NOW())"
        
        start_time = time.time()
        
        for i in range(1, COUNT + 1):
            emp_code = f"BENCHMARK_{i:04d}"
            fname = f"BenchmarkUser"
            lname = f"{i}"
            email = f"benchmark{i}@example.com"
            
            # Insert Employee
            cursor.execute(emp_sql, (emp_code, fname, lname, email))
            db_id = cursor.lastrowid
            
            # Generate Random Embedding (Normalized)
            # Create random vector [-1, 1]
            raw_vec = np.random.rand(DIMENSION).astype(np.float32) * 2 - 1
            norm_vec = normalize(raw_vec)
            blob = norm_vec.tobytes()
            
            # Insert Embedding
            cursor.execute(emb_sql, (db_id, blob))
            
            if i % 100 == 0:
                conn.commit()
                print(f"Processed {i}/{COUNT}...")
                
        conn.commit()
        duration = time.time() - start_time
        print(f"Done in {duration:.2f} seconds.")
        
        cursor.close()
        conn.close()
        
        # 3. Update Kiosk File
        print("Triggering Kiosk file update (embd_up.py)...")
        script_dir = os.path.dirname(os.path.abspath(__file__))
        embd_up_path = os.path.join(script_dir, "embd_up.py")
        
        import subprocess
        subprocess.run([sys.executable, embd_up_path, "once"])
        
    except Exception as e:
        print(f"Error: {e}")
        sys.exit(1)


if __name__ == "__main__":
    main()
