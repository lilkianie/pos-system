# Configuration Guide

## APP_URL Auto-Detection

The system now automatically detects the base URL from your server configuration. This works with:
- Virtual hosts (e.g., `possystem.com`)
- Localhost with subdirectory (e.g., `localhost/POS`)
- Any domain/subdomain setup

### How It Works

The `APP_URL` is automatically set based on:
- Protocol (http/https)
- Host name (from `$_SERVER['HTTP_HOST']`)
- Base path (from script location)

### Manual Override

If auto-detection doesn't work correctly, you can manually set it in `config/config.php`:

```php
// For virtual host
define('APP_URL', 'http://possystem.com');

// For localhost with subdirectory
define('APP_URL', 'http://localhost/POS');

// For HTTPS virtual host
define('APP_URL', 'https://possystem.com');
```

### Virtual Host Setup

If using `possystem.com`:
1. The system will auto-detect: `http://possystem.com`
2. All API calls will use the correct base URL
3. PWA features will work correctly
4. Service Worker will cache assets properly

### Testing

After configuration, verify:
- Login page loads correctly
- API calls work (check browser console)
- PWA installs correctly
- Offline mode functions properly
