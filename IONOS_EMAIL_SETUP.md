# IONOS Email OTP Configuration - READY TO USE

## ✅ Email Configuration Complete!

Your IONOS email has been configured in the system:
- **Email**: account@bpcfaceid.com
- **SMTP Server**: smtp.ionos.com
- **Port**: 587
- **Sender Name**: BPC FaceID Attendance System

---

## 📦 Install PHPMailer (Required)

### Option 1: Download PHPMailer Manually (Easier)

1. **Download PHPMailer**
   - Go to: https://github.com/PHPMailer/PHPMailer/releases
   - Download the latest `PHPMailer-6.x.x.zip`

2. **Extract to Your Project**
   - Extract the ZIP file
   - Copy the `src` folder to: `C:\inetpub\wwwroot\EndDev\PHPMailer\`
   
3. **Update password_recovery.php**
   - Change line 253 from:
   ```php
   $phpmailerPath = __DIR__ . '/../vendor/autoload.php';
   ```
   - To:
   ```php
   $phpmailerPath = __DIR__ . '/../PHPMailer/autoload.php';
   ```

4. **Create autoload.php**
   - Create file: `C:\inetpub\wwwroot\EndDev\PHPMailer\autoload.php`
   - Add this content:
   ```php
   <?php
   spl_autoload_register(function ($class) {
       $prefix = 'PHPMailer\\PHPMailer\\';
       $base_dir = __DIR__ . '/src/';
       $len = strlen($prefix);
       if (strncmp($prefix, $class, $len) !== 0) {
           return;
       }
       $relative_class = substr($class, $len);
       $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
       if (file_exists($file)) {
           require $file;
       }
   });
   ?>
   ```

### Option 2: Install Composer (For Advanced Users)

1. **Download Composer**
   - Go to: https://getcomposer.org/Composer-Setup.exe
   - Run the installer
   - Follow installation wizard

2. **Restart PowerShell/Terminal**

3. **Run Command**
   ```powershell
   cd C:\inetpub\wwwroot\EndDev
   composer require phpmailer/phpmailer
   ```

---

## 🧪 Test the System

### Without PHPMailer (Development Mode):

1. Go to: http://localhost/EndDev/login/login.php
2. Click "Forgot Password?"
3. Enter Employee ID and Email
4. Click "Send OTP"
5. Check: `C:\xampp\apache\logs\error.log` for the OTP code
6. Enter OTP and reset password

### With PHPMailer Installed:

1. Go to: http://localhost/EndDev/login/login.php
2. Click "Forgot Password?"
3. Enter Employee ID and Email  
4. Click "Send OTP"
5. Check your inbox at the email you entered
6. Enter OTP from email
7. Set new password
8. Login!

---

## 🔍 IONOS SMTP Troubleshooting

If emails don't send, try these alternative IONOS settings:

### Alternative 1: SSL Port 465
```php
$mail->Host       = 'smtp.ionos.com';
$mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
$mail->Port       = 465;
```

### Alternative 2: Different IONOS SMTP Host
```php
$mail->Host       = 'smtp.ionos.de'; // or smtp.ionos.co.uk
```

### Alternative 3: Enable Debug Mode
In `password_recovery.php`, add after line 257:
```php
$mail->SMTPDebug = 2; // Enable verbose debug output
$mail->Debugoutput = 'error_log';
```

---

## 📧 Email Preview

Users will receive:

```
From: BPC FaceID Attendance System <account@bpcfaceid.com>
Subject: Password Reset OTP - Attendance System

Password Reset Request

Hello [User Name],

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

## ✅ Current Status

- ✅ Email configured: account@bpcfaceid.com
- ✅ SMTP settings: smtp.ionos.com:587
- ✅ Password recovery flow updated
- ✅ Database table auto-created on first use
- ⏳ **Next Step**: Install PHPMailer (see options above)

---

## 🚀 Ready for Production

Once PHPMailer is installed:

1. **Change Development Mode**
   In `password_recovery.php`, line 268:
   ```php
   // Change from:
   return true;  // Development mode
   
   // To:
   return false;  // Production mode (fail if email doesn't send)
   ```

2. **Test on Live Server**
   Upload to your IONOS hosting and test

3. **Monitor Error Logs**
   Check IONOS error logs for any email issues

---

**Configuration Date**: November 16, 2025  
**Email Provider**: IONOS Web Hosting  
**Domain**: bpcfaceid.com
