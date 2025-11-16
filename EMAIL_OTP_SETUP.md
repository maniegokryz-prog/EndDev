# Email OTP Setup Guide for Password Recovery

## ✅ What Has Been Implemented

The password recovery system has been updated with the following features:

1. **Email + Employee ID Verification Only**
   - Removed contact number option
   - Users must provide their Employee ID and registered Email
   - Both must match records in the database for verification

2. **OTP Code Generation**
   - 6-digit random OTP code generated
   - 10-minute expiration time
   - Stored securely in `password_reset_otp` database table

3. **Database Verification**
   - Checks `employees` table for matching `employee_id` AND `email`
   - Only active employees can reset passwords (`status = 'active'`)

4. **Email Delivery System**
   - Professional HTML email template with OTP
   - Uses PHPMailer library for reliable email sending
   - Currently configured for development mode (OTP logged to error_log)

---

## 🔧 Requirements to Make Email OTP Functional

### 1. **Install PHPMailer Library** (REQUIRED)

You need to install PHPMailer using Composer. Run this command in your project root:

```powershell
cd C:\inetpub\wwwroot\EndDev
composer require phpmailer/phpmailer
```

If you don't have Composer installed:
- Download from: https://getcomposer.org/download/
- Install Composer for Windows
- Then run the command above

---

### 2. **Set Up Email Account for SMTP** (REQUIRED)

You need an email account to send OTP emails. Here are the options:

#### **Option A: Gmail (Recommended for Testing)**

1. **Enable 2-Factor Authentication** on your Gmail account
   - Go to: https://myaccount.google.com/security
   - Enable "2-Step Verification"

2. **Generate App Password**
   - Go to: https://myaccount.google.com/apppasswords
   - Select "Mail" and "Windows Computer"
   - Copy the 16-character password

3. **Update Configuration**
   Open `login/password_recovery.php` and update lines 275-278:
   ```php
   $mail->Host       = 'smtp.gmail.com';
   $mail->Username   = 'your-actual-email@gmail.com';  // Your Gmail address
   $mail->Password   = 'xxxx xxxx xxxx xxxx';  // Your 16-char app password
   $mail->Port       = 587;
   ```

#### **Option B: Outlook/Hotmail**

```php
$mail->Host       = 'smtp.office365.com';
$mail->Username   = 'your-email@outlook.com';
$mail->Password   = 'your-password';
$mail->Port       = 587;
```

#### **Option C: Other Email Providers**

Common SMTP settings:
- **Yahoo**: `smtp.mail.yahoo.com`, Port 587 or 465
- **Zoho**: `smtp.zoho.com`, Port 587
- **SendGrid**: `smtp.sendgrid.net`, Port 587 (requires API key)

---

### 3. **Update Email Sender Information** (REQUIRED)

In `login/password_recovery.php`, line 282, update:
```php
$mail->setFrom('your-actual-email@gmail.com', 'Attendance System');
```

Use the same email as your SMTP username.

---

### 4. **Database Table Creation** (AUTOMATIC)

The system automatically creates the `password_reset_otp` table on first use with this structure:

```sql
CREATE TABLE password_reset_otp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(255) NOT NULL,
    otp VARCHAR(10) NOT NULL,
    email VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    verified BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

No manual action needed!

---

## 📋 Testing Checklist

### Before Testing:
- [ ] PHPMailer installed (`vendor/` folder exists)
- [ ] SMTP credentials updated in `password_recovery.php`
- [ ] Email sender address updated
- [ ] Employee has valid `email` in database

### Test Procedure:

1. **Go to Login Page**
   ```
   http://localhost/EndDev/login/login.php
   ```

2. **Click "Forgot Password?"**

3. **Enter Employee ID and Email**
   - Must match exactly what's in the database
   - Email must be valid format

4. **Check for OTP**
   - If PHPMailer is configured: Check email inbox (and spam folder)
   - If PHPMailer NOT installed: Check `C:\xampp\apache\logs\error.log` for OTP

5. **Enter OTP Code**
   - Must be entered within 10 minutes
   - Code expires after that

6. **Set New Password**
   - Minimum 6 characters
   - Must match confirmation

7. **Login with New Password**

---

## 🛠️ Development Mode

Currently, if PHPMailer is NOT installed, the system:
- ✅ Still generates OTP
- ✅ Stores it in database
- ✅ Logs it to `error.log` file
- ✅ Returns success to user
- ⚠️ **Does NOT send actual email**

This allows testing without email setup.

**Location of logged OTP:**
```
C:\xampp\apache\logs\error.log
```

Look for lines like:
```
OTP for John Doe (john@example.com): 123456
```

---

## 🚀 Production Deployment

When moving to production:

1. **Change Development Mode**
   In `password_recovery.php`, line 268, change:
   ```php
   // FROM:
   return true;  // Return true for development
   
   // TO:
   return false;  // Return false in production
   ```

2. **Remove OTP Logging**
   Remove or comment out line 265:
   ```php
   // error_log("OTP for $firstName $lastName ($email): $otp");
   ```

3. **Consider Rate Limiting**
   Add protection against OTP spam (limit requests per email/IP)

4. **Use Professional Email Service**
   Consider using:
   - SendGrid (free tier: 100 emails/day)
   - Mailgun (free tier: 1,000 emails/month)
   - Amazon SES (very cheap)

---

## 🔍 Troubleshooting

### Issue: "Failed to send OTP email"

**Check:**
1. PHPMailer installed? (`vendor/autoload.php` exists)
2. SMTP credentials correct?
3. Internet connection active?
4. Gmail app password (not regular password)?
5. Error logs: `C:\xampp\apache\logs\error.log`

### Issue: "No account found with this Employee ID and Email"

**Check:**
1. Employee exists in `employees` table?
2. Email exactly matches database record?
3. Employee status is 'active'?
4. Run this query to verify:
   ```sql
   SELECT employee_id, email, status FROM employees 
   WHERE employee_id = 'YOUR_ID' AND email = 'YOUR_EMAIL';
   ```

### Issue: "OTP has expired"

**Solution:**
- OTP is valid for 10 minutes only
- Request a new OTP

### Issue: "Invalid OTP code"

**Check:**
1. Typed OTP correctly (6 digits)?
2. OTP not expired?
3. Using the most recent OTP? (old ones are deleted)

---

## 📧 Email Template Preview

When users receive the OTP email, it looks like this:

```
Subject: Password Reset OTP - Attendance System

Password Reset Request

Hello [FirstName],

You requested to reset your password. Use the OTP code below to continue:

    ┌─────────────┐
    │   123456    │
    └─────────────┘

This OTP will expire in 10 minutes.

If you didn't request this, please ignore this email.

─────────────────────────────────────────
Automated Attendance System with Facial Recognition
```

---

## 🔐 Security Features

- ✅ Email verification required (not just employee ID)
- ✅ OTP expires in 10 minutes
- ✅ Old OTPs automatically deleted when new one requested
- ✅ OTP deleted after password reset
- ✅ Password hashed with `password_hash()` (bcrypt)
- ✅ Must verify OTP before changing password
- ✅ Only active employees can reset passwords

---

## 📝 Modified Files

1. **login/login.php** - Updated modal UI (removed contact number)
2. **login/login.js** - Updated validation to use email only
3. **login/password_recovery.php** - Complete OTP system with email sending

---

## 💡 Quick Start for Testing (Without Email)

1. Open login page
2. Click "Forgot Password?"
3. Enter any valid Employee ID and Email from database
4. Click "Send OTP"
5. Open `C:\xampp\apache\logs\error.log`
6. Find your OTP (last line): `OTP for ... : 123456`
7. Enter OTP in modal
8. Set new password
9. Login!

---

## Need Help?

If you encounter issues:
1. Check error logs: `C:\xampp\apache\logs\error.log`
2. Enable PHP error display temporarily in `password_recovery.php`:
   ```php
   ini_set('display_errors', 1);
   ```
3. Test database connection
4. Verify employee exists with: `SELECT * FROM employees WHERE employee_id = 'YOUR_ID';`

---

**Created:** November 16, 2025  
**System:** Automated Attendance System with Facial Recognition
