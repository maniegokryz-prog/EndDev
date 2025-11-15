# Authentication System Documentation

## Overview
Complete authentication system with role-based access control (Admin/User), session management, and OTP-based password recovery.

## Features Implemented

### 1. User Authentication
- ✅ Secure login with hashed passwords (bcrypt)
- ✅ Role detection (Admin vs Normal User)
- ✅ Session management with timeout (30 minutes)
- ✅ Login attempt logging
- ✅ CSRF protection via session tokens

### 2. Password Recovery
- ✅ OTP-based password reset
- ✅ Account verification (ID + Email/Phone)
- ✅ 6-digit OTP generation
- ✅ OTP expiration (10 minutes)
- ✅ Secure password update

### 3. Access Control
- ✅ Authentication guard for protected pages
- ✅ Automatic redirect to login if not authenticated
- ✅ Role-based restrictions (admin-only pages)
- ✅ Session timeout handling

## File Structure

```
EndDev/
├── auth_guard.php              # Include on protected pages
├── db_connection.php           # Database connection
├── migrate_passwords.php       # One-time password hashing script
├── login/
│   ├── login.php              # Login page
│   ├── login.js               # Login functionality
│   ├── auth.php               # Login/logout API
│   └── password_recovery.php  # Password reset API
└── dashboard/
    └── logout.php             # Logout handler
```

## Setup Instructions

### Step 1: Run Password Migration
Run this **ONCE** to hash existing passwords:
```
http://localhost/EndDev/migrate_passwords.php
```

### Step 2: Protect Your Pages
Add this to the top of any page that requires authentication:

```php
<?php
// Protect this page - require authentication
require_once '../auth_guard.php';

// Get current user info
$currentUser = getCurrentUser();
?>
```

For admin-only pages, add:
```php
<?php
require_once '../auth_guard.php';
requireAdmin(); // Only admins can access
$currentUser = getCurrentUser();
?>
```

### Step 3: Test Login
1. Go to: `http://localhost/EndDev/login/login.php`
2. Enter employee ID and password
3. System will:
   - Verify credentials
   - Detect role (admin/user)
   - Create session
   - Redirect to dashboard

## Database Tables

### Created Automatically:

**login_logs** - Login attempt tracking
```sql
- id (INT PRIMARY KEY)
- employee_id (VARCHAR)
- success (BOOLEAN)
- message (VARCHAR)
- ip_address (VARCHAR)
- user_agent (TEXT)
- created_at (DATETIME)
```

**password_reset_otp** - OTP storage
```sql
- id (INT PRIMARY KEY)
- employee_id (VARCHAR)
- otp (VARCHAR)
- contact (VARCHAR)
- expires_at (DATETIME)
- verified (BOOLEAN)
- created_at (DATETIME)
```

## User Roles

Roles are determined from the `roles` field in the `employees` table:
- Contains "admin" or "administrator" (case-insensitive) → **Admin**
- Otherwise → **Normal User**

## Session Variables

When logged in, these session variables are available:
```php
$_SESSION['user_id']         // Employee database ID
$_SESSION['employee_id']     // Employee ID number
$_SESSION['user_role']       // 'admin' or 'user'
$_SESSION['user_name']       // Full name
$_SESSION['user_email']      // Email address
$_SESSION['department']      // Department
$_SESSION['position']        // Job position
$_SESSION['profile_photo']   // Profile photo path
$_SESSION['logged_in']       // true
$_SESSION['login_time']      // Timestamp for timeout
```

## Helper Functions

### isAdmin()
Check if current user is admin:
```php
if (isAdmin()) {
    // Show admin features
}
```

### requireAdmin()
Restrict page to admins only:
```php
requireAdmin(); // Dies with 403 if not admin
```

### getCurrentUser()
Get current user information:
```php
$user = getCurrentUser();
echo $user['name'];
echo $user['role'];
echo $user['department'];
```

## Password Recovery Flow

1. **Step 1: Verify Account**
   - User enters Employee ID + Email/Phone
   - System verifies account exists
   - Generates 6-digit OTP
   - Sends to email/phone
   - OTP expires in 10 minutes

2. **Step 2: Verify OTP**
   - User enters OTP code
   - System validates and marks as verified

3. **Step 3: Reset Password**
   - User enters new password (min 6 characters)
   - System hashes and updates password
   - Deletes used OTP
   - Shows success message

## Security Features

- ✅ Password hashing (bcrypt)
- ✅ SQL injection prevention (prepared statements)
- ✅ Session fixation prevention (regenerate_id)
- ✅ Session timeout (30 minutes)
- ✅ CSRF token protection
- ✅ Login attempt logging
- ✅ OTP expiration
- ✅ XSS protection headers
- ✅ Secure session cookies

## API Endpoints

### Authentication API (`login/auth.php`)
- `?action=login` - Handle login
- `?action=logout` - Handle logout
- `?action=check_session` - Verify session

### Password Recovery API (`login/password_recovery.php`)
- `?action=verify_account` - Verify user and send OTP
- `?action=verify_otp` - Verify OTP code
- `?action=reset_password` - Update password

## Testing

### Test Login:
1. Create a test user in database
2. Run migrate_passwords.php to hash password
3. Login with employee_id and password
4. Check session variables

### Test Password Reset:
1. Click "Forgot Password?"
2. Enter Employee ID + Email/Phone
3. Enter OTP (shown in dev mode)
4. Set new password
5. Login with new password

## Production Notes

### Remove Development Features:
1. In `password_recovery.php`, remove this line:
```php
'otp' => $otp, // REMOVE THIS IN PRODUCTION
```

2. Implement actual OTP sending:
   - Email: PHPMailer, SendGrid
   - SMS: Twilio, Nexmo

### Security Enhancements:
- Enable HTTPS
- Set secure session cookies
- Implement rate limiting
- Add brute-force protection
- Enable 2FA for admins

## Troubleshooting

**Can't login:**
- Check if password is hashed (run migrate_passwords.php)
- Verify employee status is 'active'
- Check login_logs table for errors

**Session expires immediately:**
- Check server timezone matches 'Asia/Manila'
- Verify session_start() not called multiple times
- Check session cookie settings

**Redirected to login constantly:**
- Clear browser cookies
- Check auth_guard.php is included correctly
- Verify session variables are set

## Page Protection Status

Protected pages (require authentication):
- ✅ dashboard/dashboard.php
- ✅ staffmanagement/staff.php
- ✅ staffmanagement/staffinfo.php
- ✅ attendancerep/attendancerep.php
- ✅ settings/settings.php (Admin only)

## Support

For issues or questions, check:
- login_logs table for failed attempts
- PHP error logs
- Browser console for JavaScript errors
