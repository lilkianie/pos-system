# Troubleshooting Guide

## 404 Errors

If you're getting 404 errors, follow these steps:

### Step 1: Check Basic Access
1. Open: `http://localhost/POS/check.php`
2. This will show you what's wrong

### Step 2: Common Issues

#### Issue: Database Not Found
**Symptoms:** Page shows "Database connection failed"

**Solution:**
1. Make sure MySQL is running in XAMPP Control Panel
2. Run: `http://localhost/POS/setup-database.php`
3. Or manually:
   - Open phpMyAdmin: `http://localhost/phpmyadmin`
   - Create database: `pos_system`
   - Import: `database/schema.sql`

#### Issue: Files Not Found
**Symptoms:** 404 error on specific pages

**Solution:**
1. Check if files exist in the correct locations
2. Verify file paths match the structure
3. Check `.htaccess` is not blocking access

#### Issue: Path Errors
**Symptoms:** CSS/JS not loading, images broken

**Solution:**
1. Check `config/config.php` - verify `APP_URL` is correct:
   ```php
   define('APP_URL', 'http://localhost/POS');
   ```
2. Make sure you're accessing via `http://localhost/POS` not `file://`

### Step 3: Quick Tests

Try these URLs in order:
1. `http://localhost/POS/test.php` - Basic PHP test
2. `http://localhost/POS/check.php` - Full diagnostic
3. `http://localhost/POS/setup-database.php` - Database setup helper
4. `http://localhost/POS/login.php` - Login page

### Step 4: XAMPP Specific Issues

#### MySQL Not Starting
1. Open XAMPP Control Panel
2. Check if MySQL port (3306) is in use
3. Stop other MySQL services if needed
4. Start MySQL from XAMPP

#### Apache Not Starting
1. Check if port 80/443 is in use
2. Change ports in XAMPP if needed
3. Update `APP_URL` in config if you change ports

#### mod_rewrite Not Working
1. Check `httpd.conf` in XAMPP
2. Find: `#LoadModule rewrite_module modules/mod_rewrite.so`
3. Remove the `#` to enable it
4. Restart Apache

### Step 5: Browser Console

Open browser Developer Tools (F12) and check:
- **Console tab:** JavaScript errors
- **Network tab:** Failed requests (404, 500, etc.)
- **Application tab:** Service Worker status (for PWA)

### Step 6: PHP Error Logs

Check XAMPP error logs:
- Location: `C:\xampp\apache\logs\error.log`
- Or: `C:\xampp\php\logs\php_error_log`

## Common Error Messages

### "Database connection failed"
- MySQL not running
- Wrong credentials in `config/config.php`
- Database `pos_system` doesn't exist

### "Call to undefined function"
- PHP extension missing
- Wrong PHP version (need 7.4+)

### "Class not found"
- Autoloader issue
- Missing require/include statements

### "404 Not Found"
- File doesn't exist
- Wrong URL path
- .htaccess blocking access

## Still Having Issues?

1. Run `check.php` and share the output
2. Check browser console for errors
3. Check PHP error logs
4. Verify database is imported correctly
5. Test with `test.php` first

## Quick Reset

If nothing works, try this:

1. **Stop Apache and MySQL** in XAMPP
2. **Delete** the `pos_system` database in phpMyAdmin
3. **Restart** Apache and MySQL
4. **Import** `database/schema.sql` again
5. **Clear** browser cache
6. **Access** `http://localhost/POS/login.php`
