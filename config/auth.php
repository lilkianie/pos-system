<?php
require_once __DIR__ . '/database.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function login($username, $password) {
        // Use LEFT JOIN in case role doesn't exist yet
        $user = $this->db->fetch(
            "SELECT u.*, r.role_name FROM users u 
             LEFT JOIN roles r ON u.role_id = r.id 
             WHERE u.username = ?",
            [$username]
        );

        if (!$user) {
            return false; // User not found
        }

        if (!$user['is_active']) {
            return false; // User is inactive
        }

        if (!password_verify($password, $user['password'])) {
            return false; // Password incorrect
        }

        // Login successful
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['role_name'] = $user['role_name'] ?? 'Unknown';
        $_SESSION['avatar_url'] = $user['avatar_url'] ?? null;
        $_SESSION['login_time'] = time();
        return true;
    }

    public function logout() {
        session_unset();
        session_destroy();
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && 
               (time() - $_SESSION['login_time']) < SESSION_LIFETIME;
    }

    public function hasPermission($permission_name) {
        if (!isset($_SESSION['role_id'])) {
            return false;
        }

        $permission = $this->db->fetch(
            "SELECT rp.id FROM role_permissions rp
             JOIN permissions p ON rp.permission_id = p.id
             WHERE rp.role_id = ? AND p.permission_name = ?",
            [$_SESSION['role_id'], $permission_name]
        );

        return $permission !== false;
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . APP_URL . '/login.php');
            exit;
        }
    }

    public function requirePermission($permission_name) {
        $this->requireLogin();
        if (!$this->hasPermission($permission_name)) {
            header('Location: ' . APP_URL . '/admin/unauthorized.php');
            exit;
        }
    }

    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'role_id' => $_SESSION['role_id'],
            'role_name' => $_SESSION['role_name'],
            'avatar_url' => $_SESSION['avatar_url'] ?? null
        ];
    }
}
