<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth();
$method = $_SERVER['REQUEST_METHOD'];
$db = new Database();

if ($method === 'GET') {
    // Allow unauthenticated access for POS product listing
    $category_id = $_GET['category_id'] ?? '';
    $where = "WHERE p.is_active = 1";
    $params = [];
    
    if ($category_id) {
        $where .= " AND p.category_id = ?";
        $params[] = $category_id;
    }
    
    $products = $db->fetchAll(
        "SELECT p.*, c.category_name 
         FROM products p 
         JOIN categories c ON p.category_id = c.id 
         $where 
         ORDER BY p.product_name",
        $params
    );
    
    echo json_encode(['success' => true, 'data' => $products]);
    exit;
}

$auth->requirePermission('manage_products');

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'save':
            $id = $_POST['id'] ?? null;
            $data = [
                'barcode' => $_POST['barcode'] ?? '',
                'product_name' => $_POST['product_name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'category_id' => $_POST['category_id'] ?? '',
                'price' => $_POST['price'] ?? 0,
                'cost' => $_POST['cost'] ?? 0,
                'stock_quantity' => $_POST['stock_quantity'] ?? 0,
                'min_stock_level' => $_POST['min_stock_level'] ?? 0,
                'unit' => $_POST['unit'] ?? 'pcs',
                'image_url' => $_POST['image_url'] ?? ''
            ];

            if ($id) {
                $db->query(
                    "UPDATE products SET barcode=?, product_name=?, description=?, category_id=?, 
                     price=?, cost=?, stock_quantity=?, min_stock_level=?, unit=?, image_url=? WHERE id=?",
                    [$data['barcode'], $data['product_name'], $data['description'], $data['category_id'],
                     $data['price'], $data['cost'], $data['stock_quantity'], $data['min_stock_level'],
                     $data['unit'], $data['image_url'], $id]
                );
            } else {
                $db->query(
                    "INSERT INTO products (barcode, product_name, description, category_id, price, cost, stock_quantity, min_stock_level, unit, image_url) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$data['barcode'], $data['product_name'], $data['description'], $data['category_id'],
                     $data['price'], $data['cost'], $data['stock_quantity'], $data['min_stock_level'], $data['unit'], $data['image_url']]
                );
            }
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = $_POST['id'] ?? 0;
            $db->query("UPDATE products SET is_active = 0 WHERE id = ?", [$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
