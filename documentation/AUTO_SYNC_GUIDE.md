# Auto-Sync Script Setup Guide

## 📋 What It Does

The `auto_sync.py` script automatically syncs your local database changes to IONOS every 60 seconds:
- ✅ New employees
- ✅ Updated employee records
- ✅ Attendance logs
- ✅ Daily attendance records

---

## 🚀 Setup Instructions

### Step 1: Install Required Python Package

```bash
pip install requests mysql-connector-python
```

### Step 2: Configure the Script

Edit `auto_sync.py` lines 14-15:

```python
API_URL = "https://your-domain.com/api/sync_endpoint.php"  # Your IONOS domain
API_KEY = "your_api_key_here"  # Same API key as in sync_endpoint.php
```

### Step 3: Test the Script

```bash
python auto_sync.py
```

You should see:
```
🚀 Auto-Sync Script Started
📡 Syncing to: https://your-domain.com/api/sync_endpoint.php
⏱️ Sync interval: 60 seconds
------------------------------------------------------------
🔄 Starting attendance sync...
✅ Sync completed: 0 records synced
⏳ Waiting 60 seconds until next sync...
```

### Step 4: Run Automatically (Windows)

**Option A: Double-click to run**
- Just double-click `start_auto_sync.bat`
- Keep the window open while syncing

**Option B: Run as background service**

1. Install NSSM (Non-Sucking Service Manager):
   - Download from: https://nssm.cc/download
   - Extract to `C:\nssm\`

2. Install as Windows service:
   ```cmd
   cd C:\nssm
   nssm install AutoSync "C:\Python\python.exe" "C:\inetpub\wwwroot\EndDev\auto_sync.py"
   nssm start AutoSync
   ```

3. Service will start automatically on Windows boot

---

## 📊 Monitoring

### Check Logs
```bash
type logs\auto_sync.log
```

### Recent activity:
```bash
Get-Content logs\auto_sync.log -Tail 20
```

### Watch live:
```bash
Get-Content logs\auto_sync.log -Wait -Tail 10
```

---

## ⚙️ Configuration Options

Edit `auto_sync.py` to customize:

### Change sync interval:
```python
SYNC_INTERVAL = 60  # Change to 30 for 30 seconds, 300 for 5 minutes, etc.
```

### Change time range for syncing:
```python
# Current: syncs last 1 hour of data
WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)

# To sync last 24 hours:
WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
```

---

## 🛠️ Troubleshooting

### "Module not found" error
```bash
pip install requests mysql-connector-python
```

### Connection refused
- Check API_URL is correct
- Verify API_KEY matches sync_endpoint.php
- Test endpoint manually: `curl https://your-domain.com/api/sync_endpoint.php`

### Database connection error
- Check LOCAL_DB_CONFIG credentials
- Verify MySQL is running: `mysql -u attendance_admin -p`

### No records syncing
- Check that records were created/updated in last hour
- Verify tables have `created_at` or `updated_at` columns
- Look for errors in logs/auto_sync.log

---

## 🔄 How It Works

```
Every 60 seconds:
┌─────────────────────────────────────────────────────┐
│ 1. Query local database for recent changes         │
│    - Employees updated in last hour                │
│    - Attendance logs from last hour                │
│    - Daily attendance from last hour               │
├─────────────────────────────────────────────────────┤
│ 2. For each record:                                │
│    - Convert to JSON                               │
│    - Send to IONOS API                             │
│    - Wait for confirmation                         │
├─────────────────────────────────────────────────────┤
│ 3. Log results                                     │
│    - Success: ✅ Record synced                     │
│    - Failed: ❌ Error logged                       │
├─────────────────────────────────────────────────────┤
│ 4. Sleep 60 seconds, repeat                        │
└─────────────────────────────────────────────────────┘
```

---

## 📝 Manual Sync Integration

The auto-sync works alongside your manual syncs:

### When you add employee in staffmanagement:
1. Record saved to local database
2. `syncToCloud()` immediately syncs to IONOS (instant)
3. Auto-sync will also catch it in next 60-second cycle (backup)

### Benefits:
- **Instant sync** via manual `syncToCloud()` calls
- **Backup sync** via auto-sync every 60 seconds
- **Catches missed records** if manual sync fails

---

## 🎯 Summary

✅ **Automatic** - Runs continuously in background
✅ **Reliable** - Retries on failure
✅ **Logged** - All activity tracked in logs/auto_sync.log
✅ **Configurable** - Adjust sync interval and data range
✅ **Redundant** - Works alongside manual sync calls

Your database now auto-syncs to IONOS every 60 seconds! 🚀
