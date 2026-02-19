<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Allow unauthenticated access for POS (products need categories)
    $db = new Database();
    $categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY category_name");
    echo json_encode(['success' => true, 'data' => $categories]);
    exit;
}

$auth->requirePermission('manage_categories');
$db = new Database();

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'save':
            $id = $_POST['id'] ?? null;
            $data = [
                'category_name' => $_POST['category_name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];

            if ($id) {
                $db->query(
                    "UPDATE categories SET category_name=?, description=?, is_active=? WHERE id=?",
                    [$data['category_name'], $data['description'], $data['is_active'], $id]
                );
            } else {
                $db->query(
                    "INSERT INTO categories (category_name, description, is_active) VALUES (?, ?, ?)",
                    [$data['category_name'], $data['description'], $data['is_active']]
                );
            }
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = $_POST['id'] ?? 0;
            $db->query("UPDATE categories SET is_active = 0 WHERE id = ?", [$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
