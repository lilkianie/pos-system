<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth();
$auth->requireLogin();
$db = new Database();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'adjust_stock':
            $auth->requirePermission('manage_inventory');
            
            $product_id = intval($_POST['product_id'] ?? 0);
            $transaction_type = $_POST['transaction_type'] ?? '';
            $quantity = intval($_POST['quantity'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');

            if (!$product_id) {
                throw new Exception('Product is required');
            }

            if ($quantity <= 0) {
                throw new Exception('Quantity must be greater than 0');
            }

            // Get current product stock
            $product = $db->fetch("SELECT * FROM products WHERE id = ?", [$product_id]);
            if (!$product) {
                throw new Exception('Product not found');
            }

            $previous_stock = $product['stock_quantity'];
            $adjustment_quantity = $quantity;

            // Calculate new stock based on transaction type
            if ($transaction_type === 'stock_in') {
                $new_stock = $previous_stock + $adjustment_quantity;
            } elseif ($transaction_type === 'adjustment') {
                $new_stock = $adjustment_quantity;
                $adjustment_quantity = $adjustment_quantity - $previous_stock; // Difference
            } else {
                // stock_out, damaged, expired
                $new_stock = max(0, $previous_stock - $adjustment_quantity);
                $adjustment_quantity = -$adjustment_quantity; // Negative for out
            }

            $db->beginTransaction();
            try {
                // Update product stock
                $db->query(
                    "UPDATE products SET stock_quantity = ? WHERE id = ?",
                    [$new_stock, $product_id]
                );

                // Create inventory transaction record
                $db->query(
                    "INSERT INTO inventory_transactions 
                     (product_id, transaction_type, quantity, previous_stock, new_stock, notes, user_id) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$product_id, $transaction_type, $adjustment_quantity, $previous_stock, $new_stock, $notes, $_SESSION['user_id']]
                );

                $db->commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'Stock adjusted successfully',
                    'previous_stock' => $previous_stock,
                    'new_stock' => $new_stock
                ]);
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        case 'get_product_history':
            $product_id = intval($_GET['product_id'] ?? 0);
            if (!$product_id) {
                throw new Exception('Product ID is required');
            }

            $transactions = $db->fetchAll(
                "SELECT it.*, u.full_name as user_name
                 FROM inventory_transactions it
                 JOIN users u ON it.user_id = u.id
                 WHERE it.product_id = ?
                 ORDER BY it.created_at DESC
                 LIMIT 50",
                [$product_id]
            );

            echo json_encode(['success' => true, 'data' => $transactions]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
