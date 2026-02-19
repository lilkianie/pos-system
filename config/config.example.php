<?php
// ============================================================
// SETUP INSTRUCTIONS:
// 1. Copy this file to config/config.php
// 2. Fill in your actual database credentials below
// 3. NEVER commit config.php to GitHub
// ============================================================

// Database Configuration (Supabase / PostgreSQL)
define('DB_HOST', 'db.YOUR_PROJECT_ID.supabase.co');
define('DB_PORT', '5432');
define('DB_USER', 'postgres');
define('DB_PASS', 'YOUR_SUPABASE_DB_PASSWORD');
define('DB_NAME', 'postgres');

// Application Configuration
define('APP_NAME', 'POS Cashiering System');

// APP_URL — set to your actual domain or localhost path
define('APP_URL', 'http://localhost/POS');

define('APP_PATH', dirname(__DIR__));

// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour

// Security — change this to a random string in production!
define('ENCRYPTION_KEY', 'change-this-to-a-random-secret-key');

// Pagination
define('ITEMS_PER_PAGE', 20);

// Date/Time
date_default_timezone_set('Asia/Manila');

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
