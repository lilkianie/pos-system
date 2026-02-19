<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth();
$auth->requirePermission('process_sales');
$db = new Database();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'search_product':
            $query = $_GET['q'] ?? '';
            $products = $db->fetchAll(
                "SELECT p.*, c.category_name 
                 FROM products p 
                 JOIN categories c ON p.category_id = c.id 
                 WHERE p.is_active = 1 
                 AND (p.product_name LIKE ? OR p.barcode LIKE ?)
                 LIMIT 20",
                ["%$query%", "%$query%"]
            );
            echo json_encode(['success' => true, 'data' => $products]);
            break;

        case 'get_product':
            $barcode = $_GET['barcode'] ?? '';
            $product = $db->fetch(
                "SELECT p.*, c.category_name 
                 FROM products p 
                 JOIN categories c ON p.category_id = c.id 
                 WHERE p.barcode = ? AND p.is_active = 1",
                [$barcode]
            );
            if ($product) {
                echo json_encode(['success' => true, 'data' => $product]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
            }
            break;

        case 'process_transaction':
            $transaction_data = json_decode($_POST['transaction_data'], true);
            
            if (!$transaction_data || !isset($transaction_data['items']) || empty($transaction_data['items'])) {
                throw new Exception('Invalid transaction data');
            }

            $db->beginTransaction();
            try {
                $transaction_number = 'TXN-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Check if transaction number exists
                while ($db->fetch("SELECT id FROM transactions WHERE transaction_number = ?", [$transaction_number])) {
                    $transaction_number = 'TXN-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                }

                $user_id = $_SESSION['user_id'];
                $total_amount = $transaction_data['total_amount'] ?? 0;
                $discount_amount = $transaction_data['discount_amount'] ?? 0;
                $tax_amount = $transaction_data['tax_amount'] ?? 0;
                $final_amount = $transaction_data['final_amount'] ?? 0;
                $payment_method = $transaction_data['payment_method'] ?? 'cash';
                $customer_id = intval($transaction_data['customer_id'] ?? 0);
                $points_redeemed = intval($transaction_data['points_redeemed'] ?? 0);

                // Validate customer if credit sale
                if ($payment_method == 'credit') {
                    if (!$customer_id) {
                        throw new Exception('Customer is required for credit sales');
                    }
                    
                    $customer = $db->fetch("SELECT * FROM customers WHERE id = ? AND is_active = 1", [$customer_id]);
                    if (!$customer) {
                        throw new Exception('Customer not found or inactive');
                    }
                    
                    // Check customer standing
                    if ($customer['standing'] == 'bad') {
                        throw new Exception('Customer has bad standing. Cannot process credit sale.');
                    }
                    
                    // Check credit limit
                    $outstanding = $db->fetch(
                        "SELECT SUM(balance) as total FROM accounts_receivable 
                         WHERE customer_id = ? AND status IN ('open', 'partial', 'overdue')",
                        [$customer_id]
                    );
                    $current_balance = floatval($outstanding['total'] ?? 0);
                    
                    if (($current_balance + $final_amount) > $customer['credit_limit']) {
                        throw new Exception('Transaction exceeds credit limit. Available: ₱' . number_format($customer['credit_limit'] - $current_balance, 2));
                    }
                }

                // Validate points redemption if member
                if ($points_redeemed > 0) {
                    if (!$customer_id) {
                        throw new Exception('Customer is required for points redemption');
                    }
                    
                    $customer = $db->fetch("SELECT * FROM customers WHERE id = ?", [$customer_id]);
                    if (!$customer || $customer['customer_type'] != 'member') {
                        throw new Exception('Only member customers can redeem points');
                    }
                    
                    if ($customer['points_balance'] < $points_redeemed) {
                        throw new Exception('Insufficient points balance');
                    }
                }

                // Insert transaction
                $payment_status = ($payment_method == 'credit') ? 'pending' : 'completed';
                $db->query(
                    "INSERT INTO transactions (transaction_number, user_id, customer_id, total_amount, discount_amount, tax_amount, final_amount, payment_method, payment_status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$transaction_number, $user_id, $customer_id > 0 ? $customer_id : null, $total_amount, $discount_amount, $tax_amount, $final_amount, $payment_method, $payment_status]
                );

                $transaction_id = $db->lastInsertId();

                // Handle points redemption
                if ($points_redeemed > 0 && $customer_id > 0) {
                    $customer = $db->fetch("SELECT * FROM customers WHERE id = ?", [$customer_id]);
                    $new_points_balance = $customer['points_balance'] - $points_redeemed;
                    
                    $db->query(
                        "UPDATE customers SET points_balance = ? WHERE id = ?",
                        [$new_points_balance, $customer_id]
                    );
                    
                    // Record points transaction
                    $db->query(
                        "INSERT INTO customer_points_transactions 
                         (customer_id, transaction_type, points, reference_type, reference_id, description) 
                         VALUES (?, 'redeemed', ?, 'transaction', ?, ?)",
                        [$customer_id, -$points_redeemed, $transaction_id, "Points redeemed for transaction $transaction_number"]
                    );
                }

                // Insert transaction items and update stock
                foreach ($transaction_data['items'] as $item) {
                    $db->query(
                        "INSERT INTO transaction_items (transaction_id, product_id, quantity, unit_price, subtotal, discount) 
                         VALUES (?, ?, ?, ?, ?, ?)",
                        [$transaction_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['subtotal'], $item['discount'] ?? 0]
                    );

                    // Get current stock before update
                    $currentProduct = $db->fetch("SELECT stock_quantity FROM products WHERE id = ?", [$item['product_id']]);
                    $previous_stock = $currentProduct['stock_quantity'] ?? 0;
                    
                    // Update stock
                    $db->query(
                        "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?",
                        [$item['quantity'], $item['product_id']]
                    );
                    
                    // Get new stock
                    $updatedProduct = $db->fetch("SELECT stock_quantity FROM products WHERE id = ?", [$item['product_id']]);
                    $new_stock = $updatedProduct['stock_quantity'] ?? 0;
                    
                    // Create inventory transaction record
                    $db->query(
                        "INSERT INTO inventory_transactions 
                         (product_id, transaction_type, quantity, previous_stock, new_stock, reference_type, reference_id, user_id) 
                         VALUES (?, 'sale', ?, ?, ?, 'transaction', ?, ?)",
                        [$item['product_id'], -$item['quantity'], $previous_stock, $new_stock, $transaction_id, $user_id]
                    );
                }

                // Create accounts receivable if credit sale
                $points_earned = 0;
                if ($payment_method == 'credit' && $customer_id > 0) {
                    $payment_terms = intval($db->fetch("SELECT setting_value FROM settings WHERE setting_key = 'credit_payment_terms'")['setting_value'] ?? 30);
                    $due_date = date('Y-m-d', strtotime("+$payment_terms days"));
                    $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad($transaction_id, 6, '0', STR_PAD_LEFT);
                    
                    // Get customer info for points calculation
                    $customer = $db->fetch("SELECT * FROM customers WHERE id = ?", [$customer_id]);
                    
                    // Calculate points earned (for member customers)
                    if ($customer && $customer['customer_type'] == 'member') {
                        $points_per_peso = floatval($db->fetch("SELECT setting_value FROM settings WHERE setting_key = 'points_per_peso'")['setting_value'] ?? 1);
                        $points_earned = intval($final_amount * $points_per_peso);
                    }
                    
                    // Create AR record
                    try {
                        $db->query(
                            "INSERT INTO accounts_receivable 
                             (transaction_id, customer_id, invoice_number, invoice_date, due_date, amount, balance, points_earned) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                            [$transaction_id, $customer_id, $invoice_number, date('Y-m-d'), $due_date, $final_amount, $final_amount, $points_earned]
                        );
                        
                        // Update customer balance
                        $db->query(
                            "UPDATE customers SET balance = balance + ? WHERE id = ?",
                            [$final_amount, $customer_id]
                        );
                        
                        // Update transaction status to completed after AR is created
                        $db->query(
                            "UPDATE transactions SET payment_status = 'completed' WHERE id = ?",
                            [$transaction_id]
                        );
                    } catch (Exception $e) {
                        error_log("Error creating AR: " . $e->getMessage());
                        throw new Exception("Error creating accounts receivable: " . $e->getMessage());
                    }
                    
                    // Award points if member
                    if ($points_earned > 0) {
                        $db->query(
                            "UPDATE customers SET 
                             points_balance = points_balance + ?,
                             points_earned_total = points_earned_total + ?
                             WHERE id = ?",
                            [$points_earned, $points_earned, $customer_id]
                        );
                        
                        // Record points transaction
                        $db->query(
                            "INSERT INTO customer_points_transactions 
                             (customer_id, transaction_type, points, reference_type, reference_id, description) 
                             VALUES (?, 'earned', ?, 'transaction', ?, ?)",
                            [$customer_id, $points_earned, $transaction_id, "Points earned from transaction $transaction_number"]
                        );
                    }
                }

                $db->commit();
                echo json_encode([
                    'success' => true, 
                    'transaction_number' => $transaction_number, 
                    'transaction_id' => $transaction_id,
                    'points_earned' => $points_earned
                ]);
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        case 'sync_offline_transactions':
            $transactions = json_decode($_POST['transactions'], true);
            $synced_ids = [];

            foreach ($transactions as $local_id => $transaction_data) {
                try {
                    $db->beginTransaction();
                    
                    $transaction_number = $transaction_data['transaction_number'] ?? ('TXN-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT));
                    
                    // Check if already exists
                    $existing = $db->fetch("SELECT id FROM transactions WHERE transaction_number = ?", [$transaction_number]);
                    if ($existing) {
                        $synced_ids[] = $local_id;
                        $db->commit();
                        continue;
                    }

                    $user_id = $transaction_data['user_id'] ?? $_SESSION['user_id'];
                    $total_amount = $transaction_data['total_amount'] ?? 0;
                    $discount_amount = $transaction_data['discount_amount'] ?? 0;
                    $tax_amount = $transaction_data['tax_amount'] ?? 0;
                    $final_amount = $transaction_data['final_amount'] ?? 0;
                    $payment_method = $transaction_data['payment_method'] ?? 'cash';

                    $db->query(
                        "INSERT INTO transactions (transaction_number, user_id, total_amount, discount_amount, tax_amount, final_amount, payment_method, is_synced, synced_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
                        [$transaction_number, $user_id, $total_amount, $discount_amount, $tax_amount, $final_amount, $payment_method]
                    );

                    $transaction_id = $db->lastInsertId();

                    foreach ($transaction_data['items'] as $item) {
                        $db->query(
                            "INSERT INTO transaction_items (transaction_id, product_id, quantity, unit_price, subtotal, discount) 
                             VALUES (?, ?, ?, ?, ?, ?)",
                            [$transaction_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['subtotal'], $item['discount'] ?? 0]
                        );

                        // Get current stock before update
                        $currentProduct = $db->fetch("SELECT stock_quantity FROM products WHERE id = ?", [$item['product_id']]);
                        $previous_stock = $currentProduct['stock_quantity'] ?? 0;
                        
                        // Update stock
                        $db->query(
                            "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?",
                            [$item['quantity'], $item['product_id']]
                        );
                        
                        // Get new stock
                        $updatedProduct = $db->fetch("SELECT stock_quantity FROM products WHERE id = ?", [$item['product_id']]);
                        $new_stock = $updatedProduct['stock_quantity'] ?? 0;
                        
                        // Create inventory transaction record
                        $db->query(
                            "INSERT INTO inventory_transactions 
                             (product_id, transaction_type, quantity, previous_stock, new_stock, reference_type, reference_id, user_id) 
                             VALUES (?, 'sale', ?, ?, ?, 'transaction', ?, ?)",
                            [$item['product_id'], -$item['quantity'], $previous_stock, $new_stock, $transaction_id, $user_id]
                        );
                    }

                    $db->commit();
                    $synced_ids[] = $local_id;
                } catch (Exception $e) {
                    $db->rollback();
                    error_log("Error syncing transaction $local_id: " . $e->getMessage());
                }
            }

            echo json_encode(['success' => true, 'synced_ids' => $synced_ids]);
            break;

        case 'search_customer':
            $query = $_GET['q'] ?? '';
            $customers = $db->fetchAll(
                "SELECT id, customer_code, customer_name, customer_type, credit_limit, standing, points_balance,
                 (SELECT SUM(balance) FROM accounts_receivable WHERE customer_id = customers.id AND status IN ('open', 'partial', 'overdue')) as outstanding_balance
                 FROM customers 
                 WHERE is_active = 1 
                 AND (customer_code LIKE ? OR customer_name LIKE ?)
                 ORDER BY customer_name
                 LIMIT 20",
                ["%$query%", "%$query%"]
            );
            echo json_encode(['success' => true, 'data' => $customers]);
            break;

        case 'get_customer':
            $id = intval($_GET['id'] ?? 0);
            $code = trim($_GET['code'] ?? '');
            
            if ($id > 0) {
                $customer = $db->fetch("SELECT * FROM customers WHERE id = ? AND is_active = 1", [$id]);
            } elseif ($code) {
                $customer = $db->fetch("SELECT * FROM customers WHERE customer_code = ? AND is_active = 1", [$code]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Customer ID or code is required']);
                exit;
            }
            
            if (!$customer) {
                echo json_encode(['success' => false, 'message' => 'Customer not found']);
                exit;
            }
            
            // Get outstanding balance
            $outstanding = $db->fetch(
                "SELECT SUM(balance) as total FROM accounts_receivable 
                 WHERE customer_id = ? AND status IN ('open', 'partial', 'overdue')",
                [$customer['id']]
            );
            $customer['outstanding_balance'] = $outstanding['total'] ?? 0;
            
            echo json_encode(['success' => true, 'data' => $customer]);
            break;

        case 'get_settings':
            $settings = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
            $settings_map = [];
            foreach ($settings as $setting) {
                $settings_map[$setting['setting_key']] = $setting['setting_value'];
            }
            echo json_encode(['success' => true, 'data' => $settings_map]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
