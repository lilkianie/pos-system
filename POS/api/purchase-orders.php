<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth();
$auth->requireLogin();
$db = new Database();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'save':
            $auth->requirePermission('manage_purchase_orders');
            
            $id = intval($input['id'] ?? 0);
            $po_number = trim($input['po_number'] ?? '');
            $supplier_id = intval($input['supplier_id'] ?? 0);
            $order_date = $input['order_date'] ?? date('Y-m-d');
            $expected_delivery_date = $input['expected_delivery_date'] ?? null;
            $tax_amount = floatval($input['tax_amount'] ?? 0);
            $discount_amount = floatval($input['discount_amount'] ?? 0);
            $notes = trim($input['notes'] ?? '');
            $items = $input['items'] ?? [];

            if (!$po_number) {
                throw new Exception('PO number is required');
            }
            if (!$supplier_id) {
                throw new Exception('Supplier is required');
            }
            if (empty($items)) {
                throw new Exception('At least one item is required');
            }

            // Calculate totals
            $subtotal = 0;
            foreach ($items as $item) {
                $item_subtotal = ($item['quantity'] * $item['unit_cost']) - ($item['discount'] ?? 0);
                $subtotal += $item_subtotal;
            }
            $total_amount = $subtotal + $tax_amount - $discount_amount;

            $db->beginTransaction();
            try {
                if ($id > 0) {
                    // Update
                    $db->query(
                        "UPDATE purchase_orders SET 
                         po_number = ?, supplier_id = ?, order_date = ?, expected_delivery_date = ?,
                         subtotal = ?, tax_amount = ?, discount_amount = ?, total_amount = ?, notes = ?
                         WHERE id = ?",
                        [$po_number, $supplier_id, $order_date, $expected_delivery_date,
                         $subtotal, $tax_amount, $discount_amount, $total_amount, $notes, $id]
                    );
                    
                    // Delete old items
                    $db->query("DELETE FROM purchase_order_items WHERE purchase_order_id = ?", [$id]);
                } else {
                    // Insert
                    $db->query(
                        "INSERT INTO purchase_orders 
                         (po_number, supplier_id, order_date, expected_delivery_date, subtotal, 
                          tax_amount, discount_amount, total_amount, balance, notes, created_by) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$po_number, $supplier_id, $order_date, $expected_delivery_date,
                         $subtotal, $tax_amount, $discount_amount, $total_amount, $total_amount, $notes, $_SESSION['user_id']]
                    );
                    $id = $db->lastInsertId();
                }

                // Insert items
                foreach ($items as $item) {
                    $item_subtotal = ($item['quantity'] * $item['unit_cost']) - ($item['discount'] ?? 0);
                    $db->query(
                        "INSERT INTO purchase_order_items 
                         (purchase_order_id, product_id, quantity, unit_cost, discount, subtotal) 
                         VALUES (?, ?, ?, ?, ?, ?)",
                        [$id, $item['product_id'], $item['quantity'], $item['unit_cost'], 
                         $item['discount'] ?? 0, $item_subtotal]
                    );
                }

                // Create accounts payable if not exists
                $existing_ap = $db->fetch(
                    "SELECT id FROM accounts_payable WHERE purchase_order_id = ?",
                    [$id]
                );

                if (!$existing_ap) {
                    $supplier = $db->fetch("SELECT payment_terms FROM suppliers WHERE id = ?", [$supplier_id]);
                    $payment_terms = $supplier['payment_terms'] ?? 30;
                    $due_date = date('Y-m-d', strtotime($order_date . " + $payment_terms days"));

                    $db->query(
                        "INSERT INTO accounts_payable 
                         (purchase_order_id, supplier_id, invoice_date, due_date, amount, balance) 
                         VALUES (?, ?, ?, ?, ?, ?)",
                        [$id, $supplier_id, $order_date, $due_date, $total_amount, $total_amount]
                    );
                }

                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Purchase order saved successfully', 'id' => $id]);
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        case 'receive':
            $auth->requirePermission('manage_purchase_orders');
            
            $po_id = intval($input['purchase_order_id'] ?? 0);
            $received_quantities = $input['received_quantities'] ?? [];

            if (!$po_id) {
                throw new Exception('Purchase order ID is required');
            }

            $po = $db->fetch("SELECT * FROM purchase_orders WHERE id = ?", [$po_id]);
            if (!$po) {
                throw new Exception('Purchase order not found');
            }

            if ($po['status'] == 'received' || $po['status'] == 'cancelled') {
                throw new Exception('Cannot receive this purchase order');
            }

            $db->beginTransaction();
            try {
                $all_received = true;
                $partial_received = false;

                foreach ($received_quantities as $item_id => $received_qty) {
                    $item = $db->fetch("SELECT * FROM purchase_order_items WHERE id = ?", [$item_id]);
                    if (!$item || $item['purchase_order_id'] != $po_id) {
                        continue;
                    }

                    $received_qty = intval($received_qty);
                    if ($received_qty < 0 || $received_qty > $item['quantity']) {
                        throw new Exception('Invalid received quantity');
                    }

                    // Update received quantity
                    $db->query(
                        "UPDATE purchase_order_items SET received_quantity = ? WHERE id = ?",
                        [$received_qty, $item_id]
                    );

                    if ($received_qty < $item['quantity']) {
                        $all_received = false;
                    }
                    if ($received_qty > 0) {
                        $partial_received = true;
                    }

                    // Update inventory if received
                    if ($received_qty > 0) {
                        $product = $db->fetch("SELECT stock_quantity FROM products WHERE id = ?", [$item['product_id']]);
                        $previous_stock = $product['stock_quantity'] ?? 0;
                        $new_stock = $previous_stock + $received_qty;

                        // Update product stock
                        $db->query(
                            "UPDATE products SET stock_quantity = ? WHERE id = ?",
                            [$new_stock, $item['product_id']]
                        );

                        // Create inventory transaction
                        $db->query(
                            "INSERT INTO inventory_transactions 
                             (product_id, transaction_type, quantity, previous_stock, new_stock, 
                              reference_type, reference_id, notes, user_id) 
                             VALUES (?, 'stock_in', ?, ?, ?, 'purchase_order', ?, ?, ?)",
                            [$item['product_id'], $received_qty, $previous_stock, $new_stock,
                             $po_id, "PO {$po['po_number']} received", $_SESSION['user_id']]
                        );
                    }
                }

                // Update PO status
                $new_status = 'pending';
                if ($all_received) {
                    $new_status = 'received';
                } elseif ($partial_received) {
                    $new_status = 'partial';
                }

                $db->query(
                    "UPDATE purchase_orders SET status = ?, delivery_date = ? WHERE id = ?",
                    [$new_status, date('Y-m-d'), $po_id]
                );

                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Purchase order received successfully']);
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        case 'get':
            $id = intval($_GET['id'] ?? 0);
            $po = $db->fetch(
                "SELECT po.*, s.supplier_name FROM purchase_orders po 
                 JOIN suppliers s ON po.supplier_id = s.id WHERE po.id = ?",
                [$id]
            );
            if (!$po) {
                throw new Exception('Purchase order not found');
            }
            $po['items'] = $db->fetchAll(
                "SELECT poi.*, p.product_name, p.barcode 
                 FROM purchase_order_items poi 
                 JOIN products p ON poi.product_id = p.id 
                 WHERE poi.purchase_order_id = ?",
                [$id]
            );
            echo json_encode(['success' => true, 'data' => $po]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
