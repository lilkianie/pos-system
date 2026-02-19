<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pos_system');

// Application Configuration
define('APP_NAME', 'POS Cashiering System');

// APP_URL Configuration
// For virtual hosts where document root = POS folder, use this:
define('APP_URL', 'http://possystem.com');

// For auto-detection (uncomment if manual setting doesn't work):
/*
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$documentRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
$scriptFile = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');

// Calculate base path
if ($documentRoot && $scriptFile && strpos($scriptFile, $documentRoot) === 0) {
    $relativePath = str_replace($documentRoot, '', dirname($scriptFile));
    $basePath = str_replace('\\', '/', trim($relativePath, '/'));
    $basePath = $basePath ? '/' . $basePath : '';
} else {
    $basePath = '';
}

define('APP_URL', $protocol . '://' . $host . $basePath);
*/

// For localhost with subdirectory, use:
// define('APP_URL', 'http://localhost/POS');

define('APP_PATH', dirname(__DIR__));

// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour

// Security
define('ENCRYPTION_KEY', 'your-secret-key-change-this-in-production');

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
