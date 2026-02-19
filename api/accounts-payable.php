<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth();
$auth->requireLogin();
$db = new Database();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    switch ($action) {
        case 'record_payment':
            $auth->requirePermission('manage_accounts_payable');
            
            $ap_id = intval($input['ap_id'] ?? 0);
            $payment_date = $input['payment_date'] ?? date('Y-m-d');
            $payment_method = $input['payment_method'] ?? 'bank_transfer';
            $reference_number = trim($input['reference_number'] ?? '');
            $amount = floatval($input['amount'] ?? 0);
            $notes = trim($input['notes'] ?? '');

            if (!$ap_id) {
                throw new Exception('Accounts payable ID is required');
            }
            if ($amount <= 0) {
                throw new Exception('Payment amount must be greater than 0');
            }

            $ap = $db->fetch("SELECT * FROM accounts_payable WHERE id = ?", [$ap_id]);
            if (!$ap) {
                throw new Exception('Accounts payable not found');
            }

            if ($ap['balance'] < $amount) {
                throw new Exception('Payment amount cannot exceed balance');
            }

            $db->beginTransaction();
            try {
                // Record payment
                $db->query(
                    "INSERT INTO ap_payments 
                     (accounts_payable_id, payment_date, payment_method, reference_number, amount, notes, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$ap_id, $payment_date, $payment_method, $reference_number, $amount, $notes, $_SESSION['user_id']]
                );

                // Update AP balance
                $new_paid = $ap['paid_amount'] + $amount;
                $new_balance = $ap['balance'] - $amount;
                $new_status = 'paid';
                if ($new_balance > 0) {
                    $new_status = $ap['paid_amount'] > 0 ? 'partial' : 'open';
                }

                // Check if overdue
                if ($new_balance > 0 && strtotime($ap['due_date']) < time()) {
                    $new_status = 'overdue';
                }

                $db->query(
                    "UPDATE accounts_payable SET 
                     paid_amount = ?, balance = ?, status = ?, payment_date = ?
                     WHERE id = ?",
                    [$new_paid, $new_balance, $new_status, $new_balance == 0 ? date('Y-m-d') : null, $ap_id]
                );

                // Update PO payment status
                $po = $db->fetch("SELECT * FROM purchase_orders WHERE id = ?", [$ap['purchase_order_id']]);
                if ($po) {
                    $po_paid = $po['paid_amount'] + $amount;
                    $po_balance = $po['balance'] - $amount;
                    $po_payment_status = 'paid';
                    if ($po_balance > 0) {
                        $po_payment_status = $po['paid_amount'] > 0 ? 'partial' : 'unpaid';
                    }

                    $db->query(
                        "UPDATE purchase_orders SET 
                         paid_amount = ?, balance = ?, payment_status = ?
                         WHERE id = ?",
                        [$po_paid, $po_balance, $po_payment_status, $ap['purchase_order_id']]
                    );
                }

                // Update supplier balance
                $db->query(
                    "UPDATE suppliers SET balance = balance - ? WHERE id = ?",
                    [$amount, $ap['supplier_id']]
                );

                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Payment recorded successfully']);
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        case 'get':
            $id = intval($_GET['id'] ?? 0);
            $ap = $db->fetch(
                "SELECT ap.*, s.supplier_name, po.po_number 
                 FROM accounts_payable ap 
                 JOIN suppliers s ON ap.supplier_id = s.id
                 JOIN purchase_orders po ON ap.purchase_order_id = po.id
                 WHERE ap.id = ?",
                [$id]
            );
            if (!$ap) {
                throw new Exception('Accounts payable not found');
            }
            $ap['payments'] = $db->fetchAll(
                "SELECT ap.*, u.full_name as created_by_name 
                 FROM ap_payments ap 
                 JOIN users u ON ap.created_by = u.id 
                 WHERE ap.accounts_payable_id = ? 
                 ORDER BY ap.created_at DESC",
                [$id]
            );
            echo json_encode(['success' => true, 'data' => $ap]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
