<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth();
$auth->requireLogin();
$db = new Database();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            // Get transaction details
            $id = $_GET['id'] ?? 0;
            $transaction = $db->fetch(
                "SELECT t.*, u.full_name as cashier_name, u2.full_name as voided_by_name
                 FROM transactions t 
                 JOIN users u ON t.user_id = u.id 
                 LEFT JOIN users u2 ON t.voided_by = u2.id
                 WHERE t.id = ?",
                [$id]
            );
            
            if (!$transaction) {
                throw new Exception('Transaction not found');
            }
            
            $items = $db->fetchAll(
                "SELECT ti.*, p.product_name 
                 FROM transaction_items ti 
                 JOIN products p ON ti.product_id = p.id 
                 WHERE ti.transaction_id = ?",
                [$id]
            );
            
            echo json_encode(['success' => true, 'transaction' => $transaction, 'items' => $items]);
            break;
            
        case 'void':
            // Void transaction
            $auth->requirePermission('void_transactions');
            
            $transaction_id = $_POST['transaction_id'] ?? 0;
            $reason = trim($_POST['reason'] ?? '');
            
            if (!$transaction_id) {
                throw new Exception('Transaction ID is required');
            }
            
            if (empty($reason)) {
                throw new Exception('Reason is required for voiding transaction');
            }
            
            // Get transaction
            $transaction = $db->fetch(
                "SELECT * FROM transactions WHERE id = ?",
                [$transaction_id]
            );
            
            if (!$transaction) {
                throw new Exception('Transaction not found');
            }
            
            if ($transaction['payment_status'] == 'voided') {
                throw new Exception('Transaction is already voided');
            }
            
            if ($transaction['payment_status'] != 'completed') {
                throw new Exception('Only completed transactions can be voided');
            }
            
            $db->beginTransaction();
            try {
                // Update transaction status
                $db->query(
                    "UPDATE transactions 
                     SET payment_status = 'voided', 
                         voided_at = NOW(), 
                         voided_by = ?,
                         void_reason = ?
                     WHERE id = ?",
                    [$_SESSION['user_id'], $reason, $transaction_id]
                );
                
                // Restore inventory
                $items = $db->fetchAll(
                    "SELECT product_id, quantity FROM transaction_items WHERE transaction_id = ?",
                    [$transaction_id]
                );
                
                foreach ($items as $item) {
                    // Get current stock before update
                    $currentProduct = $db->fetch("SELECT stock_quantity FROM products WHERE id = ?", [$item['product_id']]);
                    $previous_stock = $currentProduct['stock_quantity'] ?? 0;
                    
                    // Update stock
                    $db->query(
                        "UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?",
                        [$item['quantity'], $item['product_id']]
                    );
                    
                    // Get new stock
                    $updatedProduct = $db->fetch("SELECT stock_quantity FROM products WHERE id = ?", [$item['product_id']]);
                    $new_stock = $updatedProduct['stock_quantity'] ?? 0;
                    
                    // Create inventory transaction record
                    $db->query(
                        "INSERT INTO inventory_transactions 
                         (product_id, transaction_type, quantity, previous_stock, new_stock, reference_type, reference_id, notes, user_id) 
                         VALUES (?, 'return', ?, ?, ?, 'transaction', ?, ?, ?)",
                        [$item['product_id'], $item['quantity'], $previous_stock, $new_stock, 'transaction', $transaction_id, 'Transaction voided - stock restored', $_SESSION['user_id']]
                    );
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Transaction voided successfully']);
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
