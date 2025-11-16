# API-Based Database Sync Setup Guide

## 🎯 This allows syncing from Localhost → IONOS in real-time!

---

## Step 1: Upload API Endpoint to IONOS

1. **Upload `api/sync_endpoint.php` to your IONOS server**
   - Location: `https://yourdomain.com/api/sync_endpoint.php`

2. **Generate a secure API key:**
   ```bash
   # In PowerShell
   -join ((65..90) + (97..122) + (48..57) | Get-Random -Count 32 | % {[char]$_})
   ```
   Example output: `aB3xK9mP2qR7sT1vW4yZ8cD6fG0hJ5nL`

3. **Edit `sync_endpoint.php` line 16:**
   ```php
   define('API_KEY', 'aB3xK9mP2qR7sT1vW4yZ8cD6fG0hJ5nL'); // Your generated key
   ```

4. **Test the endpoint:**
   Visit: `https://yourdomain.com/api/sync_endpoint.php`
   Should show: `{"success":false,"error":"Invalid API key"}` ✅

---

## Step 2: Configure db_cloud_sync.php

Edit `db_cloud_sync.php` lines 21-32:

```php
private static $cloudConfig = [
    'method' => 'api',  // Use 'api' for localhost sync
    
    // API connection (works from anywhere)
    'api_url' => 'https://yourdomain.com/api/sync_endpoint.php',  // Your actual domain!
    'api_key' => 'aB3xK9mP2qR7sT1vW4yZ8cD6fG0hJ5nL'              // Same as sync_endpoint.php
];
```

---

## Step 3: Test the Sync

1. **Open test page:**
   http://localhost/EndDev/test_cloud_sync.php

2. **Click "Test Connection"**
   - Should show: ✅ "Using API sync mode"

3. **Test actual sync:**
   ```php
   // Add this to any file (e.g., test_api_sync.php)
   require_once 'db_cloud_sync.php';
   
   syncToCloud('employees', [
       'employee_id' => 'TEST001',
       'first_name' => 'Test',
       'last_name' => 'User',
       'email' => 'test@example.com',
       'status' => 'active'
   ], 'insert');
   
   echo "Sync completed! Check IONOS database.";
   ```

---

## Step 4: Add Sync to Your Application

### Example 1: After Adding Employee
```php
// In staffmanagement/newstaff.php
require_once '../db_cloud_sync.php';

if ($stmt->execute()) {
    // Sync to IONOS
    syncToCloud('employees', [
        'employee_id' => $employeeId,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'status' => 'active'
    ], 'insert');
}
```

### Example 2: After Updating Employee
```php
// In staffmanagement/api/update_employee.php
require_once '../../db_cloud_sync.php';

if ($stmt->execute()) {
    syncToCloud('employees', [
        'email' => $newEmail,
        'position' => $newPosition
    ], 'update', "employee_id = '$employeeId'");
}
```

### Example 3: After Deleting Employee
```php
// In staffmanagement/processes/delete_employee.php
require_once '../../db_cloud_sync.php';

if ($conn->query($deleteSQL)) {
    syncToCloud('employees', [], 'delete', "employee_id = '$employeeId'");
}
```

---

## How It Works

```
┌─────────────┐                    ┌──────────────┐                   ┌─────────────┐
│  Localhost  │                    │ IONOS Server │                   │   IONOS DB  │
│  Database   │                    │              │                   │ dbs14970485 │
└──────┬──────┘                    └──────┬───────┘                   └──────┬──────┘
       │                                   │                                  │
       │  1. Insert user locally           │                                  │
       ├──────────────────────────────────►│                                  │
       │                                   │                                  │
       │  2. syncToCloud() API call        │                                  │
       ├──────────────────────────────────►│                                  │
       │     with user data + API key      │                                  │
       │                                   │  3. Validate API key             │
       │                                   ├─────────────────────────────────►│
       │                                   │  4. Execute INSERT               │
       │                                   ├─────────────────────────────────►│
       │                                   │                                  │
       │  5. Success response              │  6. Data now in cloud ✅         │
       │◄──────────────────────────────────┤                                  │
       │                                   │                                  │
```

---

## Security Notes

✅ **API Key Authentication** - Only requests with valid key are accepted
✅ **HTTPS Required** - Use HTTPS for your domain (IONOS provides free SSL)
✅ **No Direct DB Exposure** - Database credentials stay on IONOS server
✅ **Prepared Statements** - Protects against SQL injection

---

## Troubleshooting

### Test API endpoint directly:
```bash
curl -X POST "https://yourdomain.com/api/sync_endpoint.php" \
  -H "X-API-KEY: your_api_key_here" \
  -d "action=insert&table=test&data={\"id\":1}"
```

### Check logs:
- Local: `logs/cloud_sync.log`
- IONOS: Check via phpMyAdmin or hosting error logs

### Common Issues:
1. **401 Unauthorized** → API key mismatch
2. **Connection timeout** → Check IONOS server is online
3. **CURL error** → Enable PHP curl extension
4. **Database error** → Check IONOS DB credentials in sync_endpoint.php

---

## Files to Upload to IONOS

Upload these files to your IONOS hosting:
- ✅ `api/sync_endpoint.php` (the API endpoint)
- ✅ `db_connection.php` (database configuration)
- ✅ All your application files

Do NOT upload:
- ❌ `test_cloud_sync.php` (keep on localhost only)
- ❌ `logs/` folder (will be created automatically)

---

## Summary

✅ **Works from localhost** - No need to deploy to test
✅ **Real-time sync** - Changes reflect immediately
✅ **Secure** - API key authentication
✅ **Automatic** - Just call `syncToCloud()` after database operations
✅ **Logs everything** - Check `logs/cloud_sync.log` for activity

You now have a professional database synchronization system! 🚀
