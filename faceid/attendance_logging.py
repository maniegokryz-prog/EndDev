"""
Attendance Logging Configuration

This module provides logging utilities for the attendance system.
Creates separate log files for attendance events and errors.
"""

import logging
import os
import sys
from datetime import datetime

# Fix Windows console encoding
if sys.platform == 'win32':
    try:
        import codecs
        sys.stdout = codecs.getwriter('utf-8')(sys.stdout.buffer, 'strict')
        sys.stderr = codecs.getwriter('utf-8')(sys.stderr.buffer, 'strict')
    except Exception:
        pass

# Get the logs directory path
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
LOGS_DIR = os.path.join(os.path.dirname(SCRIPT_DIR), "logs")

# Create logs directory if it doesn't exist
if not os.path.exists(LOGS_DIR):
    os.makedirs(LOGS_DIR)

# Define log file paths
ATTENDANCE_LOG_FILE = os.path.join(LOGS_DIR, "attendance_events.log")
ERROR_LOG_FILE = os.path.join(LOGS_DIR, "attendance_errors.log")

def setup_logger(name, log_file, level=logging.INFO):
    """
    Set up a logger with file and console handlers.
    
    Args:
        name (str): Logger name
        log_file (str): Path to log file
        level: Logging level
    
    Returns:
        logging.Logger: Configured logger
    """
    formatter = logging.Formatter(
        '[%(asctime)s] [%(levelname)s] %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    
    # File handler
    file_handler = logging.FileHandler(log_file, encoding='utf-8')
    file_handler.setFormatter(formatter)
    
    # Console handler
    console_handler = logging.StreamHandler(sys.stdout)
    console_handler.setFormatter(formatter)
    
    # Create logger
    logger = logging.getLogger(name)
    logger.setLevel(level)
    
    # Remove existing handlers to avoid duplicates
    logger.handlers = []
    
    logger.addHandler(file_handler)
    logger.addHandler(console_handler)
    
    return logger

# Create loggers
attendance_logger = setup_logger('attendance', ATTENDANCE_LOG_FILE, logging.INFO)
error_logger = setup_logger('error', ERROR_LOG_FILE, logging.ERROR)

<<<<<<< HEAD
def log_attendance_event(employee_id, employee_name, log_type, status, notes=None):
=======
def log_attendance_event(employee_id, employee_name, log_type, notes=None):
>>>>>>> de54bce0e298425ce30c77eb7e2cb27b74dc8ef5
    """
    Log an attendance event.
    
    Args:
        employee_id: Employee ID (e.g., "MA22013612")
        employee_name: Employee name
        log_type: 'time_in' or 'time_out'
<<<<<<< HEAD
        status: Status message (e.g., "On-time", "Late by 5 minutes")
        notes: Additional notes
    """
    message = f"Employee: {employee_name} ({employee_id}) | Type: {log_type.upper()} | Status: {status}"
=======
        notes: Additional notes or status message
    """
    message = f"Employee: {employee_name} ({employee_id}) | Type: {log_type.upper()}"
>>>>>>> de54bce0e298425ce30c77eb7e2cb27b74dc8ef5
    if notes:
        message += f" | Notes: {notes}"
    attendance_logger.info(message)

def log_error(context, error_message, employee_id=None, exception=None):
    """
    Log an error.
    
    Args:
        context: Where the error occurred (e.g., "log_attendance", "update_daily_attendance")
        error_message: Error description
        employee_id: Optional employee ID
        exception: Optional exception object
    """
    message = f"Context: {context} | Error: {error_message}"
    if employee_id:
        message += f" | Employee: {employee_id}"
    if exception:
        message += f" | Exception: {type(exception).__name__}: {str(exception)}"
    error_logger.error(message)
    
    # Also log stack trace if exception provided
    if exception:
        import traceback
        error_logger.error(f"Stack trace:\n{''.join(traceback.format_tb(exception.__traceback__))}")

def log_daily_attendance_update(employee_id, employee_name, action, details):
    """
    Log a daily attendance table update.
    
    Args:
        employee_id: Employee ID
        employee_name: Employee name
        action: Action taken (e.g., "Created", "Updated time_in", "Updated time_out")
        details: Dictionary with update details
    """
    message = f"Daily Attendance {action} | Employee: {employee_name} ({employee_id})"
    if details:
        detail_str = " | ".join([f"{k}={v}" for k, v in details.items()])
        message += f" | {detail_str}"
    attendance_logger.info(message)

if __name__ == "__main__":
    """Test the logging system."""
    print("Testing attendance logging system...")
    print(f"Attendance log: {ATTENDANCE_LOG_FILE}")
    print(f"Error log: {ERROR_LOG_FILE}")
    
    # Test attendance log
    log_attendance_event("MA22013612", "John Doe", "time_in", "On-time")
    log_attendance_event("MA22013612", "John Doe", "time_out", "Overtime by 15 minutes")
    
    # Test error log
    try:
        raise ValueError("Test error")
    except Exception as e:
        log_error("test_context", "This is a test error", "MA22013612", e)
    
    print("\nLogging test complete. Check the log files.")
