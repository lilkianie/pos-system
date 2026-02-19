<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth();
$auth->requireLogin();
$db = new Database();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'get':
            $user = $db->fetch(
                "SELECT id, username, email, full_name, avatar_url, role_id, created_at 
                 FROM users WHERE id = ?",
                [$user_id]
            );
            if (!$user) {
                throw new Exception('User not found');
            }
            $role = $db->fetch("SELECT role_name FROM roles WHERE id = ?", [$user['role_id']]);
            $user['role_name'] = $role['role_name'] ?? '';
            echo json_encode(['success' => true, 'data' => $user]);
            break;

        case 'update':
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (empty($full_name)) {
                throw new Exception('Full name is required');
            }
            if (empty($email)) {
                throw new Exception('Email is required');
            }

            $existing = $db->fetch(
                "SELECT id FROM users WHERE email = ? AND id != ?",
                [$email, $user_id]
            );
            if ($existing) {
                throw new Exception('Email already in use');
            }

            $db->query(
                "UPDATE users SET full_name = ?, email = ? WHERE id = ?",
                [$full_name, $email, $user_id]
            );

            $_SESSION['full_name'] = $full_name;
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            break;

        case 'change_password':
            $current = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (empty($current) || empty($new_password) || empty($confirm)) {
                throw new Exception('All password fields are required');
            }
            if ($new_password !== $confirm) {
                throw new Exception('New passwords do not match');
            }
            if (strlen($new_password) < 6) {
                throw new Exception('New password must be at least 6 characters');
            }

            $user = $db->fetch("SELECT password FROM users WHERE id = ?", [$user_id]);
            if (!password_verify($current, $user['password'])) {
                throw new Exception('Current password is incorrect');
            }

            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $db->query("UPDATE users SET password = ? WHERE id = ?", [$hash, $user_id]);

            echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
            break;

        case 'upload_avatar':
            if (empty($_FILES['avatar']['tmp_name']) || !is_uploaded_file($_FILES['avatar']['tmp_name'])) {
                throw new Exception('Please select an image to upload');
            }
            $file = $_FILES['avatar'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (!in_array($mime, $allowed, true)) {
                throw new Exception('Invalid file type. Use JPG, PNG, GIF or WebP.');
            }
            if ($file['size'] > 2 * 1024 * 1024) {
                throw new Exception('Image must be 2 MB or smaller.');
            }
            $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            $ext = isset($extMap[$mime]) ? $extMap[$mime] : 'jpg';
            $uploadDir = dirname(__DIR__) . '/assets/uploads/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $filename = $user_id . '_' . time() . '.' . $ext;
            $path = $uploadDir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $path)) {
                throw new Exception('Failed to save image');
            }
            $relativeUrl = 'avatars/' . $filename;
            $db->query("UPDATE users SET avatar_url = ? WHERE id = ?", [$relativeUrl, $user_id]);
            $_SESSION['avatar_url'] = $relativeUrl;
            echo json_encode([
                'success' => true,
                'message' => 'Avatar updated',
                'avatar_url' => APP_URL . '/assets/uploads/' . $relativeUrl
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
