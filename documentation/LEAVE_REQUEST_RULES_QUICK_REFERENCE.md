# Leave Request System - Quick Reference Guide

## 🚦 Submission Rules (v2.0 - Strict Mode)

### ❌ You CANNOT submit a leave request if:

1. **You have ANY pending request**
   - Must wait for admin to approve or reject
   - Only 1 pending request allowed at a time

2. **You have 2 approved leaves this month**
   - Monthly limit: 2 approved leaves per calendar month
   - Resets on the 1st of each month

3. **Dates overlap with existing leave**
   - Cannot request same dates twice
   - No overlapping date ranges allowed

---

## ✅ You CAN submit if:

- ✅ No pending requests
- ✅ Less than 2 approved leaves this month
- ✅ Dates don't overlap with existing leaves

---

## 📝 Request Status Flow

```
SUBMIT REQUEST
    ↓
⏳ PENDING (Waiting for admin)
    ↓
Admin Reviews
    ↓
    ├─→ ✅ APPROVED (Counts toward monthly limit)
    └─→ ❌ REJECTED (Doesn't count - can submit again)
```

---

## 💡 Common Scenarios

### Scenario 1: First Request
```
Status: No pending, 0 approved this month
Result: ✅ CAN SUBMIT
```

### Scenario 2: Waiting for Approval
```
Status: 1 pending, 0 approved
Result: ❌ CANNOT SUBMIT (Must wait for admin)
```

### Scenario 3: First Request Approved
```
Status: No pending, 1 approved this month
Result: ✅ CAN SUBMIT (1 more available)
```

### Scenario 4: Request Rejected
```
Status: Request was rejected
Result: ✅ CAN SUBMIT IMMEDIATELY (Rejections don't count)
```

### Scenario 5: Monthly Limit Reached
```
Status: No pending, 2 approved this month
Result: ❌ CANNOT SUBMIT (Wait until next month)
```

### Scenario 6: Has Pending + 1 Approved
```
Status: 1 pending, 1 approved
Result: ❌ CANNOT SUBMIT (Pending blocks new submissions)
```

---

## 🔔 What to Do If Blocked

### "You have a pending request"
- **Wait** for admin to approve/reject
- **Contact admin** if urgent
- Admin can expedite review

### "Monthly limit reached"
- **Wait** until next month (resets on 1st)
- **Review** your planned dates
- **Consolidate** leaves if possible

### "Overlapping dates"
- **Choose different dates**
- **Cancel** existing request if needed
- **Check** your scheduled leaves

---

## 📅 Month Calculation

- Based on **start_date** of leave
- Format: Calendar month (Jan, Feb, etc.)
- **Cross-month leaves**: Count in BOTH months
  - Example: Jan 30 - Feb 2 = 1 leave in Jan + 1 in Feb

---

## 👤 For Employees

### Best Practices:
1. **Plan ahead** - Submit early for important dates
2. **One at a time** - Don't try to submit multiple requests
3. **Check status** - Wait for approval before next request
4. **Choose wisely** - You get 2 approved leaves per month
5. **Avoid overlaps** - Review existing leaves first

### Tips:
- Rejected requests don't count toward limit
- Can resubmit immediately after rejection
- Contact admin for urgent/emergency situations

---

## 👨‍💼 For Admins

### Review Guidelines:
1. **Approve/Reject promptly** - Unblocks employee for next request
2. **Check staffing** - Ensure minimum coverage
3. **Review dates** - Avoid conflicts
4. **Communicate** - Explain rejections when needed

### Admin Actions:
- Can approve requests from employee profile
- Can reject with reason
- Approval immediately counts toward employee's monthly limit
- Can manually add leaves if system rules need override

---

## 🆘 Emergency Leave

If you need **urgent/emergency leave** but are blocked:

1. **Contact admin immediately**
2. Admin can:
   - Expedite pending request review
   - Reject old pending to allow new submission
   - Manually add emergency leave
3. Explain the emergency situation

---

## 📊 Check Your Status

### Via System:
1. Open "Add Scheduled Leave" modal
2. Check the info banner at top:
   - 🟢 Green = Available to submit
   - 🟡 Yellow = Pending request blocks you
   - 🔴 Red = Monthly limit reached

### Via Test Page:
- Go to: `staffmanagement/test_monthly_limit.php`
- View complete status breakdown

---

## 🔧 Technical Details

**Backend:** `staffmanagement/api/leave_request.php`  
**Frontend:** `staffmanagement/staffinfo.php`  
**Documentation:** `documentation/MONTHLY_LEAVE_LIMIT.md`

---

## 📞 Support

**Issues with leave requests?**
- Check your current status first
- Review this guide
- Contact your admin
- Check system logs if admin

---

## Version History

- **v2.0** (Jan 14, 2026) - Sequential submission only, stricter rules
- **v1.0** (Jan 14, 2026) - Initial implementation with monthly limits

---

*Last Updated: January 14, 2026*
