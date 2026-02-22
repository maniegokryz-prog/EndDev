"""
Kiosk Local API Server

This tiny HTTP server listens on localhost (127.0.0.1:5001) for commands 
from the PHP web application. Its primary purpose is to clear the local 
SQLite database (`kiosk_local.db`) synchronously when the admin clears the 
MySQL database, preventing the Sync Manager from re-uploading wiped records.
"""

import os
import sys
import json
import sqlite3
import shutil
from pathlib import Path
from http.server import HTTPServer, BaseHTTPRequestHandler

# Add the database directory to path
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DB_DIR = os.path.join(SCRIPT_DIR, "database")
DB_PATH = os.path.join(DB_DIR, "kiosk_local.db")
sys.path.insert(0, DB_DIR)

class KioskAPIHandler(BaseHTTPRequestHandler):
    def _send_json_response(self, status_code, data):
        self.send_response(status_code)
        self.send_header('Content-type', 'application/json')
        # CORS headers just in case
        self.send_header('Access-Control-Allow-Origin', '*')
        self.end_headers()
        self.wfile.write(json.dumps(data).encode('utf-8'))

    def do_POST(self):
        if self.path == '/api/clear_records':
            try:
                if not os.path.exists(DB_PATH):
                    self._send_json_response(404, {"success": False, "message": "Local DB not found"})
                    return

                # Connect to SQLite
                conn = sqlite3.connect(DB_PATH)
                cursor = conn.cursor()

                # Wipe attendance records
                cursor.execute("DELETE FROM attendance_logs")
                cursor.execute("DELETE FROM daily_attendance")
                
                # Reset Auto-Increment Counters
                cursor.execute("DELETE FROM sqlite_sequence WHERE name IN ('attendance_logs', 'daily_attendance')")
                
                # Update sync status to prevent trying to sync wiped records
                cursor.execute("UPDATE sync_status SET last_push_time = datetime('now') WHERE table_name IN ('attendance_logs', 'daily_attendance')")

                conn.commit()
                conn.execute("VACUUM") # Shrink database file and defragment
                conn.close()

                # --- Clear Python Cache ---
                # Recursively discover and delete all __pycache__ directories in the faceid folder
                pycache_count = 0
                for path in Path(SCRIPT_DIR).rglob('__pycache__'):
                    if path.is_dir():
                        try:
                            shutil.rmtree(path)
                            pycache_count += 1
                        except Exception as e:
                            print(f"[KIOSK API] Failed to delete cache {path}: {e}")

                print(f"[KIOSK API] Successfully cleared local attendance records, vacuumed DB, and deleted {pycache_count} pycache folders.")
                self._send_json_response(200, {"success": True, "message": f"Local records cleared & {pycache_count} cache folders deleted"})
                
            except Exception as e:
                print(f"[KIOSK API] Error clearing records: {e}")
                self._send_json_response(500, {"success": False, "message": str(e)})
        else:
            self._send_json_response(404, {"success": False, "message": "Endpoint not found"})

    def do_GET(self):
        if self.path == '/api/status':
            self._send_json_response(200, {"success": True, "message": "Kiosk API is running"})
        else:
            self._send_json_response(404, {"success": False, "message": "Endpoint not found"})

    # Suppress default HTTP logging to keep console clean
    def log_message(self, format, *args):
        pass

def run_api_server(port=5001):
    server_address = ('127.0.0.1', port)
    try:
        httpd = HTTPServer(server_address, KioskAPIHandler)
        print(f"[KIOSK API] Listening on http://{server_address[0]}:{server_address[1]}")
        httpd.serve_forever()
    except Exception as e:
        print(f"[KIOSK API ERROR] Failed to start server: {e}")

if __name__ == '__main__':
    run_api_server()
