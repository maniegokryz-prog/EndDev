# Database Sync Guide - Localhost to IONOS

## Current Setup:
- **Localhost Database:** `database_records` (attendance_admin@localhost)
- **IONOS Database:** `dbs14970485` (dbu58088@localhost on IONOS)
- **Problem:** They are separate databases with no automatic sync

---

## ✅ Recommended Solution: Deploy to IONOS

The best approach is to **work directly on IONOS** instead of trying to sync:

### Step 1: Deploy Your Code to IONOS
1. Upload all your files to IONOS web hosting
2. Upload your database using phpMyAdmin:
   - Export from localhost: `mysqldump -u attendance_admin -p database_records > backup.sql`
   - Import to IONOS phpMyAdmin

### Step 2: Use IONOS as Your Primary System
- Make all changes directly on the IONOS server
- Use the live system for testing and development
- Keep localhost as a backup only

---

## 🔄 Alternative: Manual Sync When Needed

If you want to keep developing on localhost and sync occasionally:

### Method 1: Export/Import Database
```bash
# 1. Export localhost database
mysqldump -u attendance_admin -pConfirmp@ssword123 database_records > database_export.sql

# 2. Login to IONOS phpMyAdmin (from hosting control panel)
# 3. Select database: dbs14970485
# 4. Click "Import" tab
# 5. Upload database_export.sql
# 6. Click "Go"
```

### Method 2: Sync Specific Tables Only
```bash
# Export only employees table
mysqldump -u attendance_admin -pConfirmp@ssword123 database_records employees > employees_only.sql

# Import to IONOS via phpMyAdmin
```

---

## 🚫 Why Cloud Sync Module Won't Work from Localhost

**IONOS Security:** Most shared hosting providers (including IONOS) block external database connections for security. You can only access the database:
- From the IONOS server itself (via PHP scripts)
- Through phpMyAdmin (web interface)
- NOT from external computers (like your localhost)

**The sync module is designed for:**
- Syncing between two servers (both accessible)
- NOT for localhost → IONOS (blocked by firewall)

---

## 📋 Workflow Recommendation

### For Development:
1. **Develop on localhost** with your local database
2. **Test features** thoroughly
3. **Export database** when ready to deploy
4. **Upload to IONOS** via phpMyAdmin
5. **Upload code files** via FTP/File Manager

### For Production Updates:
1. Make changes on the **live IONOS system**
2. Use phpMyAdmin to manage data
3. Periodically **download backup** to localhost

---

## 🔧 Quick Commands

### Export entire database:
```bash
mysqldump -u attendance_admin -pConfirmp@ssword123 database_records > full_backup.sql
```

### Export with date:
```bash
mysqldump -u attendance_admin -pConfirmp@ssword123 database_records > backup_%date:~-4,4%%date:~-10,2%%date:~-7,2%.sql
```

### Export structure only (no data):
```bash
mysqldump -u attendance_admin -pConfirmp@ssword123 --no-data database_records > structure_only.sql
```

### Export data only (no structure):
```bash
mysqldump -u attendance_admin -pConfirmp@ssword123 --no-create-info database_records > data_only.sql
```

---

## ✅ Summary

**Short Answer:** You can't automatically sync localhost → IONOS in real-time due to IONOS security restrictions.

**Best Practice:** 
1. Develop on localhost
2. Export database when ready
3. Import to IONOS via phpMyAdmin
4. Use IONOS as your production system

The `db_cloud_sync.php` module is useful when you have TWO accessible servers, not for localhost → restricted cloud hosting.
