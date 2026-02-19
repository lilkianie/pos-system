# Installation Guide

## Quick Start

### Step 1: Database Setup

1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Create a new database named `pos_system` (or import the schema.sql file)
3. Import the database schema:
   - Click on the `pos_system` database
   - Go to "Import" tab
   - Choose file: `database/schema.sql`
   - Click "Go"

Alternatively, use command line:
```bash
mysql -u root -p < database/schema.sql
```

### Step 2: Configure Database Connection

Edit `config/config.php` and update if needed:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Your MySQL password
define('DB_NAME', 'pos_system');
```

### Step 3: Set Permissions

Ensure the following directories are writable (if needed):
- `assets/images/` (for product images)

### Step 4: Access the System

1. Start your web server (XAMPP/WAMP/LAMP)
2. Open browser: `http://localhost/POS`
3. Login with default credentials:
   - Username: `admin`
   - Password: `admin123`

### Step 5: Create PWA Icons (Optional)

The system expects icon images in `assets/images/`:
- icon-72x72.png
- icon-96x96.png
- icon-128x128.png
- icon-144x144.png
- icon-152x152.png
- icon-192x192.png
- icon-384x384.png
- icon-512x512.png

You can create these using any image editor or online icon generator. The icons are used when installing the PWA.

## Troubleshooting

### Database Connection Error
- Check MySQL is running
- Verify database credentials in `config/config.php`
- Ensure database `pos_system` exists

### Service Worker Not Registering
- Access via `http://localhost` (not `file://`)
- Check browser console for errors
- Ensure HTTPS in production (or use localhost for development)

### Offline Mode Not Working
- Check browser supports Service Workers (Chrome, Edge, Firefox)
- Verify `sw.js` is accessible
- Check browser console for Service Worker errors

### Products Not Loading
- Check database has products
- Verify API endpoints are accessible
- Check browser console for AJAX errors

## Production Deployment

1. **Security**
   - Change default admin password
   - Use strong passwords
   - Enable HTTPS
   - Update `ENCRYPTION_KEY` in config.php

2. **Performance**
   - Enable PHP OPcache
   - Use MySQL query caching
   - Enable Gzip compression
   - Use CDN for static assets

3. **Backup**
   - Regular database backups
   - Backup uploaded files
   - Test restore procedures

4. **Monitoring**
   - Monitor error logs
   - Track offline transactions
   - Review sync status
