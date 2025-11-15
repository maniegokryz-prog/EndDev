# Performance Metrics Calculation Documentation

## Overview
The Performance Metrics feature provides visual analytics of employee attendance using donut charts. It calculates four key metrics based on data from the `daily_attendance` table.

---

## Data Source
**Table:** `daily_attendance`

**Key Columns:**
- `employee_id` - Employee's internal database ID
- `attendance_date` - Date of attendance record
- `status` - Attendance status (`complete`, `incomplete`, `absent`)
- `late_minutes` - Minutes late (0 or NULL = on time, >0 = late)
- `time_in` - Clock in time
- `time_out` - Clock out time

---

## Metric Calculations

### 1. **PRESENT Metric** 🟢
**What it measures:** Days where the employee successfully completed their attendance (both time in and time out recorded)

**Calculation:**
```
Present Count = COUNT(records WHERE status = 'complete')
Present Percentage = (Present Count / Total Scheduled Days) × 100
```

**Example:**
- Total Scheduled Days: 20
- Complete Records: 18
- **Present = (18/20) × 100 = 90.0%**

**Color:** Green (#28a745)

**Business Meaning:** Shows employee's overall attendance reliability. High percentage indicates good attendance habits.

---

### 2. **ABSENT Metric** 🔴
**What it measures:** Days where the employee did not show up to work at all

**Calculation:**
```
Absent Count = COUNT(records WHERE status = 'absent')
Absent Percentage = (Absent Count / Total Scheduled Days) × 100
```

**Example:**
- Total Scheduled Days: 20
- Absent Records: 2
- **Absent = (2/20) × 100 = 10.0%**

**Color:** Red (#dc3545)

**Business Meaning:** Indicates absenteeism rate. Lower is better. High percentage may indicate issues requiring attention.

---

### 3. **ON TIME Metric** 🔵
**What it measures:** Days where the employee arrived on or before their scheduled start time

**Calculation:**
```
On Time Count = COUNT(records WHERE status = 'complete' AND (late_minutes = 0 OR late_minutes IS NULL))
On Time Percentage = (On Time Count / Total Scheduled Days) × 100
```

**Example:**
- Total Scheduled Days: 20
- On Time Records: 15
- **On Time = (15/20) × 100 = 75.0%**

**Color:** Blue (#0d6efd)

**Business Meaning:** Shows punctuality. High percentage indicates reliable time management and professionalism.

**Note:** Only counts complete attendance days (not absent or incomplete records)

---

### 4. **LATE Metric** 🟠
**What it measures:** Days where the employee arrived after their scheduled start time

**Calculation:**
```
Late Count = COUNT(records WHERE status = 'complete' AND late_minutes > 0)
Late Percentage = (Late Count / Total Scheduled Days) × 100
```

**Example:**
- Total Scheduled Days: 20
- Late Records: 3
- **Late = (3/20) × 100 = 15.0%**

**Color:** Orange (#fd7e14)

**Business Meaning:** Indicates tardiness rate. Lower is better. Consistent lateness may require intervention.

**Note:** Only counts complete attendance days where late_minutes is positive

---

## Important Notes

### Status Definitions
- **`complete`**: Both time in and time out recorded - Full attendance day
- **`absent`**: No time in or out recorded - Employee didn't attend
- **`incomplete`**: Only time in OR time out recorded - Partial attendance (not counted in metrics)

### Why Incomplete is Excluded
Incomplete records represent edge cases where:
- Employee forgot to clock out
- Employee clocked out without clocking in first
- System issues prevented proper recording

These are not counted in any of the four metrics because they don't represent a clear attendance state.

---

## Filtering Options

### By Month and Year
Users can filter metrics by:
- **Specific Month + Year**: Example: November 2025
- **Entire Year**: Select "All Months" with a year
- **All Time**: Default view without filters

**SQL Example:**
```sql
-- For November 2025
WHERE MONTH(attendance_date) = 11 AND YEAR(attendance_date) = 2025

-- For all of 2025
WHERE YEAR(attendance_date) = 2025
```

---

## Visual Representation

Each metric is displayed as a **donut chart** with:
- **Filled portion** (colored): Represents the metric percentage
- **Empty portion** (gray): Represents the remaining percentage to 100%
- **Center text**: Shows the actual percentage value
- **Below chart**: Shows the count of days

**Chart Configuration:**
- Type: Doughnut (donut) chart
- Cutout: 75% (creates the donut hole)
- Responsive: Adjusts to container size
- No legend or tooltip (cleaner UI)

---

## API Endpoint

**File:** `get_performance_metrics.php`

**Method:** GET

**Parameters:**
- `employee_id` (required) - Employee's employee_id code (e.g., "MA20230001")
- `year` (optional) - Filter by year (default: current year)
- `month` (optional) - Filter by month (1-12)

**Response Format:**
```json
{
  "success": true,
  "employee": {
    "id": "MA20230001",
    "name": "John Doe"
  },
  "period": {
    "month": 11,
    "year": 2025
  },
  "metrics": {
    "present": {
      "count": 18,
      "percentage": 90.0,
      "description": "Days with complete time in and time out"
    },
    "absent": {
      "count": 2,
      "percentage": 10.0,
      "description": "Days marked as absent"
    },
    "onTime": {
      "count": 15,
      "percentage": 75.0,
      "description": "Days arrived on or before scheduled time"
    },
    "late": {
      "count": 3,
      "percentage": 15.0,
      "description": "Days arrived after scheduled time"
    }
  },
  "summary": {
    "total_scheduled_days": 20,
    "total_complete": 18,
    "total_absent": 2,
    "total_on_time": 15,
    "total_late": 3
  }
}
```

---

## Real-World Example

**Scenario:** Employee John Doe in November 2025 (20 working days)

**Attendance Breakdown:**
- Day 1-15: Arrived on time, clocked out properly ✅ (15 complete, 0 late_minutes)
- Day 16-18: Arrived 10 mins late, clocked out properly ⏰ (3 complete, 10 late_minutes)
- Day 19-20: Didn't show up ❌ (2 absent)

**Resulting Metrics:**
- 🟢 **Present**: 18/20 = 90.0% (days 1-18)
- 🔴 **Absent**: 2/20 = 10.0% (days 19-20)
- 🔵 **On Time**: 15/20 = 75.0% (days 1-15)
- 🟠 **Late**: 3/20 = 15.0% (days 16-18)

**Verification:** 90% + 10% = 100% ✓ (Present + Absent)
**Verification:** 75% + 15% = 90% ✓ (On Time + Late = Present)

---

## Technical Implementation

### Frontend (JavaScript)
- **Chart Library**: Chart.js v4.4.0
- **Chart Type**: Doughnut with 75% cutout
- **Dynamic Loading**: AJAX fetch on page load and filter change
- **Responsive**: Charts adapt to screen size

### Backend (PHP)
- **Database**: MySQL with prepared statements
- **Security**: Parameter binding to prevent SQL injection
- **Error Handling**: Try-catch blocks with meaningful error messages
- **Performance**: Single optimized query per request

### UX Features
- Loading spinner while fetching data
- Error state display if API fails
- Hover effects on metric boxes
- Automatic month/year selection (current by default)
- Real-time updates when filters change

---

## Maintenance Notes

### Adding New Metrics
To add a new metric:
1. Update `get_performance_metrics.php` with calculation logic
2. Add new canvas element in HTML
3. Create chart in JavaScript `loadPerformanceMetrics()` function
4. Choose appropriate color from Bootstrap palette

### Troubleshooting
- **No data showing**: Check if employee has records in `daily_attendance` table
- **Wrong percentages**: Verify `status` and `late_minutes` values in database
- **Charts not rendering**: Check browser console for Chart.js errors

---

*Last Updated: November 13, 2025*
