# POS Cashiering System

A complete Point of Sale (POS) Cashiering System built with PHP/MySQL, jQuery, AJAX, Bootstrap, and Progressive Web App (PWA) capabilities with offline support.

## Features

### Core Features
- **Complete POS Cashiering Interface** - Modern, responsive cashiering system
- **Product Management** - Add, edit, delete products with barcode support
- **Category Management** - Organize products by categories
- **User Management** - Create and manage users with different roles
- **Role-Based Access Control** - Granular permissions system
- **Sales Reports** - Comprehensive sales and inventory reports
- **Dashboard** - Real-time statistics and analytics

### Payment Methods
- **Cash** - Traditional cash payments with change calculation
- **Credit Card** - Credit card payment processing
- **E-Wallet** - Support for GCash, PayMaya, GrabPay, and other e-wallets

### Offline & PWA Features
- **Progressive Web App** - Installable on mobile and desktop
- **Offline Mode** - Full functionality without internet connection
- **LocalStorage** - Transactions stored locally when offline
- **Auto-Sync** - Automatic synchronization when connection is restored
- **Service Worker** - Caches assets for offline use

## Installation

### Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- XAMPP/WAMP/LAMP (recommended for local development)

### Setup Steps

1. **Database Setup**
   ```sql
   -- Import the database schema
   mysql -u root -p < database/schema.sql
   ```
   Or use phpMyAdmin to import `database/schema.sql`

2. **Configuration**
   - Edit `config/config.php` and update database credentials if needed
   - Default database settings:
     - Host: localhost
     - User: root
     - Password: (empty)
     - Database: pos_system

3. **Web Server**
   - Place files in your web server directory (e.g., `htdocs/POS`)
   - Ensure Apache mod_rewrite is enabled
   - Access via: `http://localhost/POS`

4. **Default Login**
   - Username: `admin`
   - Password: `admin123`
   - **Important**: Change the default password after first login!

## Project Structure

```
POS/
├── admin/              # Admin panel pages
│   ├── dashboard.php
│   ├── products.php
│   ├── categories.php
│   ├── users.php
│   ├── roles.php
│   ├── reports.php
│   └── settings.php
├── api/                # API endpoints
│   ├── products.php
│   ├── categories.php
│   ├── users.php
│   ├── roles.php
│   ├── settings.php
│   └── pos.php
├── assets/             # Static assets
│   ├── css/
│   ├── js/
│   └── images/
├── config/             # Configuration files
│   ├── config.php
│   ├── database.php
│   └── auth.php
├── database/           # Database files
│   └── schema.sql
├── pos/                # POS cashiering interface
│   └── index.php
├── manifest.json       # PWA manifest
├── sw.js              # Service worker
└── README.md
```

## Usage

### Admin Panel
1. Login with admin credentials
2. Navigate to different sections:
   - **Dashboard**: View statistics and recent transactions
   - **Products**: Manage product inventory
   - **Categories**: Organize products
   - **Users**: Manage user accounts
   - **Roles & Permissions**: Configure access control
   - **Reports**: View sales reports and analytics
   - **Settings**: Configure system settings

### POS Cashiering
1. Login as Cashier or Admin
2. Search/scan products by barcode or name
3. Add products to cart
4. Apply discounts if needed
5. Select payment method (Cash/Credit Card/E-Wallet)
6. Process payment and print receipt

### Offline Mode
- The system automatically detects internet connectivity
- When offline:
  - Transactions are saved to localStorage
  - Products are loaded from cache
  - All POS functions work normally
- When online:
  - Offline transactions are automatically synced
  - Product data is refreshed

## PWA Installation

### Desktop (Chrome/Edge)
1. Open the POS system in your browser
2. Click the install icon in the address bar
3. Or go to Settings > Apps > Install this site as an app

### Mobile (Android/iOS)
1. Open the POS system in your mobile browser
2. Tap the menu (three dots)
3. Select "Add to Home Screen" or "Install App"

## API Endpoints

### Products
- `GET /api/products.php` - Get all products
- `POST /api/products.php?action=save` - Save product
- `POST /api/products.php?action=delete` - Delete product

### POS
- `GET /api/pos.php?action=search_product&q={query}` - Search products
- `GET /api/pos.php?action=get_product&barcode={barcode}` - Get product by barcode
- `POST /api/pos.php?action=process_transaction` - Process transaction
- `POST /api/pos.php?action=sync_offline_transactions` - Sync offline transactions
- `GET /api/pos.php?action=get_settings` - Get system settings

## Security Notes

- Change default admin password immediately
- Use strong passwords for all users
- Keep PHP and MySQL updated
- Use HTTPS in production
- Regularly backup database
- Review and adjust permissions as needed

## Browser Support

- Chrome/Edge (recommended)
- Firefox
- Safari
- Opera

## License

This project is provided as-is for educational and commercial use.

## Support

For issues or questions, please check:
- Database connection settings
- PHP error logs
- Browser console for JavaScript errors
- Service worker registration status

## Future Enhancements

- Receipt printer integration
- Barcode scanner hardware support
- Inventory alerts
- Customer management
- Loyalty programs
- Multi-store support
- Advanced analytics
- Export reports to PDF/Excel
