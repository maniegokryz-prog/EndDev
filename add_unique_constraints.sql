-- Add unique constraints to prevent duplicate syncs
-- Run this on IONOS database via phpMyAdmin

-- For schedule_periods: unique per schedule, day, and time
ALTER TABLE schedule_periods 
ADD UNIQUE KEY unique_schedule_period (schedule_id, day_of_week, start_time, end_time);

-- For employee_schedules: already has unique constraint on employee_id + schedule_id + effective_date
-- Check if it exists, if not add it
ALTER TABLE employee_schedules 
ADD UNIQUE KEY unique_employee_schedule (employee_id, schedule_id, effective_date);

-- For employee_assignments: already has unique constraint on employee_id + schedule_period_id
-- The schema already defines this as UNIQUE(employee_id, schedule_period_id)

-- For attendance_logs: unique per employee, date, time, and type
ALTER TABLE attendance_logs 
ADD UNIQUE KEY unique_attendance_log (employee_id, log_date, log_time, log_type);

-- For daily_attendance: already has UNIQUE(employee_id, attendance_date)

-- Verify constraints
SHOW INDEX FROM schedule_periods;
SHOW INDEX FROM employee_schedules;
SHOW INDEX FROM employee_assignments;
SHOW INDEX FROM attendance_logs;
