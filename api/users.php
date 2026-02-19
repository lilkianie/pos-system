<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth();
$auth->requirePermission('manage_users');
$db = new Database();

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'save':
            $id = $_POST['id'] ?? null;
            $data = [
                'username' => trim($_POST['username'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'full_name' => trim($_POST['full_name'] ?? ''),
                'role_id' => $_POST['role_id'] ?? '',
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'password' => $_POST['password'] ?? ''
            ];

            // Validate required fields
            if (empty($data['username'])) {
                throw new Exception('Username is required');
            }
            if (empty($data['email'])) {
                throw new Exception('Email is required');
            }
            if (empty($data['full_name'])) {
                throw new Exception('Full name is required');
            }
            if (empty($data['role_id'])) {
                throw new Exception('Role is required');
            }

            // Check for duplicate username
            $existing_username = $db->fetch(
                "SELECT id FROM users WHERE username = ? AND id != ?",
                [$data['username'], $id ?: 0]
            );
            if ($existing_username) {
                throw new Exception('Username already exists');
            }

            // Check for duplicate email
            $existing_email = $db->fetch(
                "SELECT id FROM users WHERE email = ? AND id != ?",
                [$data['email'], $id ?: 0]
            );
            if ($existing_email) {
                throw new Exception('Email already exists');
            }

            if ($id) {
                // Update existing user
                if ($data['password']) {
                    $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
                    $db->query(
                        "UPDATE users SET username=?, email=?, full_name=?, role_id=?, is_active=?, password=? WHERE id=?",
                        [$data['username'], $data['email'], $data['full_name'], $data['role_id'], 
                         $data['is_active'], $data['password'], $id]
                    );
                } else {
                    $db->query(
                        "UPDATE users SET username=?, email=?, full_name=?, role_id=?, is_active=? WHERE id=?",
                        [$data['username'], $data['email'], $data['full_name'], $data['role_id'], 
                         $data['is_active'], $id]
                    );
                }
            } else {
                // Create new user
                if (empty($data['password'])) {
                    throw new Exception('Password is required for new users');
                }
                if (strlen($data['password']) < 6) {
                    throw new Exception('Password must be at least 6 characters');
                }
                $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
                $db->query(
                    "INSERT INTO users (username, email, password, full_name, role_id, is_active) 
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$data['username'], $data['email'], $data['password'], $data['full_name'], 
                     $data['role_id'], $data['is_active']]
                );
            }
            echo json_encode(['success' => true, 'message' => $id ? 'User updated successfully' : 'User created successfully']);
            break;

        case 'delete':
            $id = $_POST['id'] ?? 0;
            if ($id == $_SESSION['user_id']) {
                throw new Exception('Cannot delete your own account');
            }
            $db->query("UPDATE users SET is_active = 0 WHERE id = ?", [$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
