# Quick Email Configuration

## Step 1: Install PHPMailer

```powershell
cd C:\inetpub\wwwroot\EndDev
composer require phpmailer/phpmailer
```

## Step 2: Get Gmail App Password

1. Go to: https://myaccount.google.com/security
2. Enable "2-Step Verification"
3. Go to: https://myaccount.google.com/apppasswords
4. Generate app password for "Mail"
5. Copy the 16-character password

## Step 3: Update Configuration

Edit `login/password_recovery.php` (lines 275-282):

```php
$mail->Host       = 'smtp.gmail.com';
$mail->Username   = 'YOUR_EMAIL@gmail.com';     // ← Change this
$mail->Password   = 'xxxx xxxx xxxx xxxx';      // ← Change this (app password)
$mail->Port       = 587;

$mail->setFrom('YOUR_EMAIL@gmail.com', 'Attendance System');  // ← Change this
```

## Step 4: Test

1. Go to login page
2. Click "Forgot Password"
3. Enter Employee ID and Email
4. Check your email for OTP!

---

## For Development (No Email Setup)

OTP will be logged to:
```
C:\xampp\apache\logs\error.log
```

Search for: `OTP for`
