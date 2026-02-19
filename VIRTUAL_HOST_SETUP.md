# Virtual Host Setup Guide

## For possystem.com Virtual Host

If you're using a virtual host like `possystem.com`, the system will auto-detect the URL. However, you may need to configure a few things:

### 1. Update manifest.json (if needed)

The `manifest.json` uses relative paths, so it should work automatically. But if you need to update it:

```json
{
  "start_url": "/pos/index.php",
  "scope": "/"
}
```

### 2. Service Worker Path

The service worker (`sw.js`) uses relative paths, so it should work automatically with your virtual host.

### 3. Virtual Host Configuration (Apache)

If you haven't set up the virtual host yet, add this to your `httpd-vhosts.conf`:

```apache
<VirtualHost *:80>
    ServerName possystem.com
    DocumentRoot "C:/xampp/htdocs/POS"
    <Directory "C:/xampp/htdocs/POS">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 4. Hosts File

Add to `C:\Windows\System32\drivers\etc\hosts`:

```
127.0.0.1    possystem.com
```

### 5. HTTPS Setup (Optional)

If you want HTTPS for your virtual host:

1. Generate SSL certificate (using XAMPP's makecert.bat or OpenSSL)
2. Update virtual host to use port 443
3. Update `config/config.php` if needed

### 6. PWA Installation

When using a virtual host, the PWA will install with your domain name (`possystem.com`), which is perfect for production use.

### 7. Testing

After setup, test:
- `http://possystem.com` - Should redirect to login
- `http://possystem.com/login.php` - Login page
- `http://possystem.com/pos/index.php` - POS interface
- `http://possystem.com/admin/dashboard.php` - Admin dashboard

### 8. API Endpoints

All API endpoints will work with the virtual host:
- `http://possystem.com/api/pos.php`
- `http://possystem.com/api/products.php`
- etc.

### Notes

- The system auto-detects the base URL from `$_SERVER['HTTP_HOST']`
- All paths are relative, so they work with any domain
- Service Worker and PWA features work with virtual hosts
- Offline mode works the same regardless of domain
