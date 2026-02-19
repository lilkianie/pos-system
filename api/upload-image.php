<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth();
$auth->requirePermission('manage_products');

// Upload directory
$uploadDir = __DIR__ . '/../assets/uploads/products/';

// Create directory if it doesn't exist
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        throw new Exception('Failed to create upload directory. Please create assets/uploads/products/ directory manually.');
    }
}

// Check if directory is writable
if (!is_writable($uploadDir)) {
    throw new Exception('Upload directory is not writable. Please check permissions on assets/uploads/products/');
}

// Allowed file types
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$maxFileSize = 5 * 1024 * 1024; // 5MB

try {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $file = $_FILES['image'];
    
    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.');
    }
    
    // Validate file size
    if ($file['size'] > $maxFileSize) {
        throw new Exception('File size exceeds 5MB limit.');
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'product_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save uploaded file.');
    }
    
    // Return relative URL
    $imageUrl = APP_URL . '/assets/uploads/products/' . $filename;
    
    echo json_encode([
        'success' => true,
        'image_url' => $imageUrl,
        'filename' => $filename
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
