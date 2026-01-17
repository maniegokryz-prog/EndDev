# Leave Request Rules - Account Analysis Report

**Generated:** January 14, 2026  
**Version:** 2.0 (Strict Mode)

---

## ⚠️ CRITICAL FINDING: Rules Apply to ALL Accounts

### Summary
The strict leave request rules (**1 pending at a time, 2 approved per month, no overlaps**) are applied **EQUALLY TO ALL ACCOUNTS**, including:

- ✅ Regular employees
- ✅ Admin users
- ✅ Managers
- ✅ All roles and positions

**There are NO exemptions or bypasses based on account type.**

---

## 🔍 Detailed Analysis

### 1. Validation Logic (Backend)

**File:** `staffmanagement/api/leave_request.php`  
**Function:** `submitLeaveRequest()`

#### RULE 1: Pending Request Check (Line ~100)
```php
$sql_pending = "SELECT id FROM employee_leaves 
                WHERE employee_id = ? 
                AND status = 'pending'";
```

**Analysis:**
- ❌ NO role checking
- ❌ NO admin bypass
- ❌ NO is_admin condition
- ✅ Applies to `employee_id` only

**Conclusion:** **ALL accounts** are blocked if they have a pending request.

---

#### RULE 2: Monthly Approved Limit (Line ~112)
```php
$sql_count = "SELECT COUNT(*) as request_count 
              FROM employee_leaves 
              WHERE employee_id = ? 
              AND status = 'approved'
              AND (DATE_FORMAT(start_date, '%Y-%m') = ? OR DATE_FORMAT(end_date, '%Y-%m') = ?)";

if ($count_result['request_count'] >= 2) {
    throw new Exception('Monthly leave limit reached...');
}
```

**Analysis:**
- ❌ NO role checking
- ❌ NO admin bypass
- ❌ NO conditional logic based on user type
- ✅ Applies to `employee_id` only

**Conclusion:** **ALL accounts** are limited to 2 approved leaves per month.

---

#### RULE 3: Date Overlap Check (Line ~125)
```php
$sql = "SELECT id FROM employee_leaves 
        WHERE employee_id = ? 
        AND status IN ('pending', 'approved')
        AND (overlapping date logic)";

if ($result->num_rows > 0) {
    throw new Exception('You cannot request leave for the same or overlapping dates...');
}
```

**Analysis:**
- ❌ NO role checking
- ❌ NO admin bypass
- ✅ Applies to `employee_id` only

**Conclusion:** **ALL accounts** cannot have overlapping leave dates.

---

### 2. Admin Special Feature Analysis

#### Auto-Approve Option
**Location:** Line 83, 149
```php
$is_admin = ($_POST['is_admin'] ?? '0') === '1';
$auto_approve = ($_POST['auto_approve'] ?? '0') === '1';
$initial_status = ($is_admin && $auto_approve) ? 'approved' : 'pending';
```

**What This Does:**
- Allows admin to **auto-approve** a leave request
- **DOES NOT bypass validation rules**
- Validation happens **BEFORE** this code is reached
- Auto-approve only changes initial status from 'pending' to 'approved'

**Important:**
```
Validation (Lines 100-140)
    ↓
THEN Auto-approve check (Line 149)
```

**Conclusion:** Admin's auto-approve feature **DOES NOT** bypass the 3 validation rules. Admin still cannot submit if they have:
1. A pending request
2. 2 approved leaves this month
3. Overlapping dates

---

### 3. Frontend Implementation Analysis

**File:** `staffmanagement/staffinfo.php`

#### Admin Check (Line 1075)
```javascript
const isAdminForLeave = <?php echo json_encode($isAdmin); ?>;
if (isAdminForLeave) {
    document.getElementById('adminOptionsDiv').style.display = 'block';
}
```

**What This Does:**
- Shows/hides the "Auto-approve" checkbox
- **ONLY UI difference for admins**
- Does not bypass validation

#### Validation Function (Lines 1086-1115)
```javascript
function checkMonthlyLimit() {
    // Checks pending requests
    // Checks approved monthly count
    // Updates UI accordingly
}
```

**Analysis:**
- No role-based conditions
- Same logic for all users
- No admin exemptions

**Conclusion:** Frontend validation applies **equally to all accounts**.

---

## 📊 Account Comparison Table

| Feature | Regular Employee | Admin User |
|---------|-----------------|------------|
| **Pending Block** | ✅ Applied | ✅ Applied |
| **Monthly Limit (2)** | ✅ Applied | ✅ Applied |
| **Overlap Check** | ✅ Applied | ✅ Applied |
| **Can Submit Multiple Pending** | ❌ No | ❌ No |
| **Can Exceed 2/month** | ❌ No | ❌ No |
| **Can Overlap Dates** | ❌ No | ❌ No |
| **Auto-Approve Option** | ❌ No | ✅ Yes* |
| **Bypass Validation** | ❌ No | ❌ No |

\* *Auto-approve does not bypass validation rules*

---

## 🎯 Real-World Scenarios

### Scenario 1: Admin Submitting Leave for Themselves

**Steps:**
1. Admin opens their own employee profile
2. Clicks "Add Scheduled Leave"
3. Has 1 pending request already

**Result:**
```
❌ BLOCKED
Error: "You already have a pending leave request. 
Please wait for admin approval before submitting another request."
```

**Why:** Admin submitting for themselves goes through same validation.

---

### Scenario 2: Admin Has 2 Approved Leaves This Month

**Steps:**
1. Admin has 2 approved leaves in January
2. Tries to submit 3rd leave request
3. Uses auto-approve checkbox

**Result:**
```
❌ BLOCKED
Error: "Monthly leave limit reached. 
You have already used 2 approved leave requests this month."
```

**Why:** Validation happens BEFORE auto-approve logic.

---

### Scenario 3: Admin Submitting Leave for Another Employee

**Steps:**
1. Admin opens another employee's profile
2. That employee has 1 pending request
3. Admin tries to submit another leave

**Result:**
```
❌ BLOCKED
Error: "You already have a pending leave request..."
```

**Why:** Rules check `employee_id`, not who is submitting.

---

## 🔐 Code Flow Diagram

```
User submits leave request
    ↓
API receives: employee_id, is_admin, auto_approve
    ↓
┌─────────────────────────────────┐
│ VALIDATION (ALL ACCOUNTS)       │
│                                  │
│ 1. Check pending for employee_id│ ← No role check
│ 2. Check monthly limit          │ ← No role check
│ 3. Check date overlaps          │ ← No role check
└─────────────────────────────────┘
    ↓
Any rule fails? → ❌ REJECT (throw exception)
    ↓
All rules pass? → ✅ Continue
    ↓
Determine initial status:
    - If is_admin AND auto_approve → 'approved'
    - Else → 'pending'
    ↓
Insert into database
```

---

## 💡 Why This Design?

### Fairness
- Same rules for everyone prevents favoritism
- Transparent and auditable
- No special privileges based on role

### System Integrity
- Prevents admin from abusing auto-approve
- Maintains orderly workflow
- Ensures data consistency

### Accountability
- All requests logged equally
- Admin actions trackable
- Clear audit trail

---

## ⚠️ Potential Issues

### Issue 1: Admin Cannot Help Employees Urgently
**Problem:** If admin has pending/maxed out leaves, they can't use auto-approve for urgent employee needs.

**Workaround:** Admin can:
- Directly approve the employee's existing pending request
- Use manual database entry (not recommended)
- Wait for their own request to be processed

### Issue 2: Admin Testing/Demo Scenarios
**Problem:** Admins testing the system face same limits.

**Workaround:**
- Use test employee accounts
- Reset test data between demos
- Use staging/development environment

### Issue 3: Emergency Situations
**Problem:** Admin needs emergency leave but has 2 approved + 1 pending.

**Workaround:**
- Contact another admin to approve pending
- Cancel existing request and resubmit
- Manual database intervention (last resort)

---

## 🔧 Recommendations

### Option 1: Keep Current System (Recommended)
**Pros:**
- Fair and equal treatment
- Simple and transparent
- No role-based complexity

**Cons:**
- Admin has no special override
- May cause bottlenecks in emergencies

---

### Option 2: Add Admin Bypass Flag
**Implementation:**
```php
// Add before validation
if ($is_admin && ($_POST['admin_override'] ?? '0') === '1') {
    // Skip validation rules
    // Log override action
}
```

**Pros:**
- Admin can handle emergencies
- Flexibility for special cases

**Cons:**
- Opens door to abuse
- Harder to audit
- Breaks fairness principle
- Requires additional logging

**Recommendation:** ⚠️ Only implement if business requires it

---

### Option 3: Role-Based Limits
**Implementation:**
```php
// Different limits based on role
$monthly_limit = match($employee_role) {
    'admin' => 3,
    'manager' => 3,
    'employee' => 2,
    default => 2
};
```

**Pros:**
- Different rules for different roles
- Can prioritize senior staff

**Cons:**
- Complex to maintain
- May cause resentment
- Harder to explain to users

**Recommendation:** ⚠️ Only if business policy requires it

---

## ✅ Final Conclusion

### Current State
**ALL ACCOUNTS ARE SUBJECT TO THE SAME RULES:**
1. ✅ 1 pending request at a time
2. ✅ 2 approved leaves per month
3. ✅ No overlapping dates

### Admin Special Features
- ✅ Can auto-approve their requests (still follows validation)
- ✅ Can approve other employees' requests
- ✅ Can see pending requests from all employees
- ❌ CANNOT bypass validation rules for themselves
- ❌ CANNOT submit for employees who violate rules

### Is This Correct?
**YES** - This is the intended behavior for a fair, transparent system.

**NO** - If business requires admin override capability, code modifications needed.

---

## 📝 Testing Verification

To verify this analysis, run:
```
staffmanagement/test_monthly_limit.php
```

Test with:
1. Regular employee account → Rules applied
2. Admin employee account → Same rules applied
3. Admin with auto-approve → Still blocked if rules violated

---

## 📞 Decision Required

**Question for stakeholders:**

Should admins have a bypass/override capability for:
- Emergency situations?
- Special cases?
- System testing?

If YES → Code modifications required  
If NO → Current implementation is correct

---

*Report compiled by analyzing source code in `staffmanagement/api/leave_request.php` and `staffmanagement/staffinfo.php`*
