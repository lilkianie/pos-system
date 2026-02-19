<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth();
$auth->requirePermission('manage_roles');
$db = new Database();

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'save':
            $id = $_POST['id'] ?? null;
            $data = [
                'role_name' => $_POST['role_name'] ?? '',
                'description' => $_POST['description'] ?? ''
            ];

            if ($id) {
                $db->query(
                    "UPDATE roles SET role_name=?, description=? WHERE id=?",
                    [$data['role_name'], $data['description'], $id]
                );
            } else {
                $db->query(
                    "INSERT INTO roles (role_name, description) VALUES (?, ?)",
                    [$data['role_name'], $data['description']]
                );
            }
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = $_POST['id'] ?? 0;
            if ($id == 1) {
                throw new Exception('Cannot delete Super Admin role');
            }
            $db->query("DELETE FROM roles WHERE id = ?", [$id]);
            echo json_encode(['success' => true]);
            break;

        case 'update_permission':
            $role_id = $_POST['role_id'] ?? 0;
            $permission_id = $_POST['permission_id'] ?? 0;
            $checked = $_POST['checked'] ?? 0;

            if ($checked) {
                $db->query(
                    "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
                    [$role_id, $permission_id]
                );
            } else {
                $db->query(
                    "DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?",
                    [$role_id, $permission_id]
                );
            }
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
