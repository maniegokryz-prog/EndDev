# Monthly Leave Request Limit - Implementation Documentation

## Overview
A **strict monthly leave request system** has been implemented with the following rules:
1. **Only 1 pending request at a time** - Employees must wait for admin approval before submitting another request
2. **Maximum 2 approved leaves per month** - Once approved, employees can use up to 2 leave periods per calendar month
3. **No overlapping dates** - Cannot request leave for the same or overlapping dates

This prevents abuse, ensures fair distribution, and maintains an orderly approval workflow.

---

## Implementation Details

### 1. Backend Validation (API Level)

**File:** `staffmanagement/api/leave_request.php`

**Location:** `submitLeaveRequest()` function

**Three-Tier Validation Logic:**

#### RULE 1: No Multiple Pending Requests
```php
// Check if employee has any PENDING requests
$sql_pending = "SELECT id FROM employee_leaves 
                WHERE employee_id = ? 
                AND status = 'pending'";
```
**Blocks:** Any new submission if a pending request exists  
**Error:** "You already have a pending leave request. Please wait for admin approval before submitting another request."

#### RULE 2: Monthly Approved Limit (2 per month)
```php
// Check monthly APPROVED leave request limit
$sql_count = "SELECT COUNT(*) as request_count 
              FROM employee_leaves 
              WHERE employee_id = ? 
              AND status = 'approved'
              AND (DATE_FORMAT(start_date, '%Y-%m') = ? OR DATE_FORMAT(end_date, '%Y-%m') = ?)";
```
**Blocks:** New submission if 2 approved leaves exist in current month  
**Error:** "Monthly leave limit reached. You have already used 2 approved leave requests this month."

#### RULE 3: No Overlapping Dates
```php
// Check for overlapping/duplicate leave dates
$sql = "SELECT id FROM employee_leaves 
        WHERE employee_id = ? 
        AND status IN ('pending', 'approved')
        AND (overlapping date logic)";
```
**Blocks:** Any date range that overlaps with existing pending/approved leaves  
**Error:** "You cannot request leave for the same or overlapping dates. Please choose different dates."

---

### 2. Frontend Validation (User Interface)

**File:** `staffmanagement/staffinfo.php`

**Dynamic Status Indicators:**

| Status | Message | Color |
|--------|---------|-------|
| **Has Pending** | "⏳ You have a pending leave request. Wait for approval..." | 🟡 Warning (Yellow) |
| **2 Approved** | "❌ Monthly limit reached. You have used 2 of 2 approved leaves." | 🔴 Danger (Red) |
| **Available** | "✅ X of 2 leave requests available this month." | 🟢 Success (Green) |

**Real-time Checking:**
- Triggers when modal opens
- Fetches all employee requests
- Separates pending vs approved
- Updates UI accordingly

---

## Rules & Behavior

### Submission Flow:

```
Employee submits request 
    ↓
✅ No pending requests? 
    ↓ YES
✅ Less than 2 approved this month?
    ↓ YES
✅ No date overlaps?
    ↓ YES
✅ REQUEST APPROVED → Status: PENDING
    ↓
Admin reviews
    ↓
→ APPROVED → Count toward monthly limit
→ REJECTED → Does NOT count, employee can submit again
```

### What Blocks Submission:

❌ **Any pending request** (must be approved/rejected first)  
❌ **2 approved leaves this month**  
❌ **Overlapping dates** with existing leaves  

### What Does NOT Block:

✅ **Rejected requests** - Don't count toward limit  
✅ **Previous month's leaves** - Only current month matters  
✅ **Cancelled requests** - Immediately free up slots  

---

## Testing

### Test File
**Location:** `staffmanagement/test_monthly_limit.php`

**How to Test:**
1. Open the test file in browser
2. Change `$test_employee_id` to a real employee ID
3. View:
   - Pending requests status
   - Current month's approved requests
   - Submission eligibility

**Test Scenarios:**

| Scenario | Expected Behavior |
|----------|-------------------|
| No requests | ✅ Can submit (2 available) |
| 1 pending request | ❌ BLOCKED - Must wait for approval |
| 1 approved, 0 pending | ✅ Can submit (1 available) |
| 2 approved this month | ❌ BLOCKED - Monthly limit reached |
| 1 approved + 1 pending | ❌ BLOCKED - Pending exists |
| 2 rejected this month | ✅ Can submit (rejections don't count) |
| 1 approved, admin rejects 2nd | ✅ Can submit again immediately |

**Workflow Test:**
1. Employee submits Request A → Status: PENDING ⏳
2. Employee tries to submit Request B → ❌ BLOCKED
3. Admin approves Request A → Status: APPROVED ✅
4. Employee submits Request B → Status: PENDING ⏳
5. Admin approves Request B → Status: APPROVED ✅
6. Employee tries to submit Request C → ❌ BLOCKED (2 approved this month)

---

## User Experience Flow

### Scenario 1: First Request of Month
1. Employee opens modal → "✅ 2 of 2 leave requests available"
2. Fills form and submits
3. Success → "Leave request submitted and pending approval"
4. Opens modal again → "⏳ You have a pending request. Wait for approval..."

### Scenario 2: After First Approval
1. Admin approves first request
2. Employee opens modal → "✅ 1 of 2 leave requests available"
3. Submits second request → Status: PENDING
4. Opens modal again → "⏳ You have a pending request. Wait for approval..."

### Scenario 3: Monthly Limit Reached
1. Employee has 2 approved leaves this month
2. Opens modal → "❌ Monthly limit reached. You have used 2 of 2..."
3. Form still visible but submission will fail with error

### Scenario 4: Request Rejected
1. Employee had 1 pending request
2. Admin rejects it → Status: REJECTED
3. Employee can immediately submit new request
4. Opens modal → Shows available requests again

---

## Database Schema

**Table:** `employee_leaves`

**Relevant Columns:**
- `employee_id` - Links to employee
- `start_date` - Used for month calculation  
- `end_date` - Also checked for month boundary
- `status` - ENUM('pending', 'approved', 'rejected')
- `created_at` - Timestamp of submission

**Status Flow:**
```
PENDING → (Admin Action) → APPROVED or REJECTED
```

---

## Error Messages

### Backend (API):

**Pending Block:**
```json
{
    "success": false,
    "error": "You already have a pending leave request. Please wait for admin approval before submitting another request."
}
```

**Monthly Limit:**
```json
{
    "success": false,
    "error": "Monthly leave limit reached. You have already used 2 approved leave requests this month."
}
```

**Date Overlap:**
```json
{
    "success": false,
    "error": "You cannot request leave for the same or overlapping dates. Please choose different dates."
}
```

### Frontend:
- Displayed in error modal with icon
- Auto-closes after 5 seconds
- User-friendly language with explanations

---

## Admin Override

**Note:** Admin users can bypass the limit if needed by:
1. Checking the "Auto-approve this request" option
2. The validation still applies, but admins are notified

**Future Enhancement:** Consider adding an admin flag to completely bypass the limit for special cases.

---

## Maintenance & Monitoring

### Log Files:
- Location: `staffmanagement/logs/leave_system.log`
- Logs all leave request submissions
- Track rejection reasons

### Monthly Report Query:
```sql
SELECT 
    employee_id,
    COUNT(*) as request_count,
    DATE_FORMAT(start_date, '%Y-%m') as month
FROM employee_leaves
WHERE status IN ('pending', 'approved')
GROUP BY employee_id, DATE_FORMAT(start_date, '%Y-%m')
HAVING request_count >= 2
ORDER BY month DESC;
```

---

## Configuration

### To Change the Monthly Limit:

**Backend:**
Edit `staffmanagement/api/leave_request.php` around line ~115:
```php
if ($count_result['request_count'] >= 2) {  // Change 2 to desired limit
    throw new Exception('Monthly leave limit reached...');
}
```

**Frontend:**
Edit `staffmanagement/staffinfo.php` checkMonthlyLimit function:
```javascript
const remaining = 2 - approvedThisMonth.length;  // Change 2 here
```

### To Disable Pending Request Block:
Comment out RULE 1 in `leave_request.php` (lines ~95-105)

**Note:** Not recommended - may cause approval queue overflow

---

## Security Considerations

✅ **Triple validation** (pending block + monthly limit + date overlap)  
✅ **Server-side enforcement** prevents client bypass  
✅ **SQL injection protected** (prepared statements)  
✅ **Client-side check** is UX enhancement only  
✅ **Admin actions logged** for audit trail  
✅ **Status-based logic** prevents gaming the system  

---

## Key Differences from Previous Version

### OLD SYSTEM (v1.0):
- ❌ Allowed 2 pending + approved requests simultaneously
- ❌ Could submit multiple requests before approval
- ✅ Monthly limit of 2

### NEW SYSTEM (v2.0):
- ✅ Only 1 pending request at a time (sequential submission)
- ✅ Must wait for approval before next submission
- ✅ Monthly limit of 2 APPROVED leaves
- ✅ Stricter workflow control

**Why the Change:**
- Prevents overwhelming admin with multiple requests
- Forces employees to prioritize their leave needs
- Creates orderly, manageable approval queue
- Reduces confusion and conflicts

---

## Advantages of New System

1. **Orderly Queue:** Admin reviews one request per employee at a time
2. **Clear Status:** Employees know exactly where they stand
3. **No Gaming:** Can't submit multiple requests hoping one gets approved
4. **Better Planning:** Employees must think through their leave needs
5. **Reduced Conflicts:** Sequential approval prevents date overlaps
6. **Fair Distribution:** Everyone follows same strict rules

---

## Future Enhancements

1. **Emergency Override:** Admin can force-approve without counting toward limit
2. **Role-Based Limits:** Managers get 3 requests, staff get 2
3. **Department Quotas:** Ensure minimum staffing levels
4. **Carry-forward:** Unused monthly requests carry to next month
5. **Bulk Operations:** Admin can approve/reject multiple requests
6. **Email Notifications:** Auto-notify on approval/rejection
7. **Calendar View:** Visual date conflict checker

---

## Support & Troubleshooting

### Common Issues:

**Q: "I have no pending requests but still can't submit"**
- Check if you have 2 approved leaves this month already
- Verify you're not selecting overlapping dates
- Check system logs for detailed error

**Q: "My request was rejected, can I submit again?"**
- Yes! Rejected requests don't block new submissions
- You can submit immediately after rejection

**Q: "I need emergency leave but have pending request"**
- Contact admin to expedite your pending request
- Or ask admin to reject it so you can submit urgent one
- Admin can manually add leave if critical

**Q: "Request spans two months, does it count twice?"**
- Yes, if leave starts in Jan and ends in Feb, it counts for both months
- Best practice: Keep leaves within single month when possible

**Q: "Admin approved but I still can't submit"**
- System needs a few seconds to update status
- Refresh page and try again
- Check if you now have 2 approved leaves this month

---

## Implementation Date
**January 14, 2026**

**Version:** 2.0 (Stricter Rules)

**Previous Version:** 1.0 (Allowed simultaneous pending)

**Developer Notes:**
- Major policy change to sequential-only submission
- Three-tier validation (pending + limit + overlap)
- Enhanced frontend indicators
- Better user guidance and error messages
- Backward compatible with existing database
- No migration required
