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
        case 'collect_payment':
            $auth->requirePermission('manage_accounts_receivable');
            
            $ar_id = intval($input['ar_id'] ?? 0);
            $payment_date = $input['payment_date'] ?? date('Y-m-d');
            $payment_method = $input['payment_method'] ?? 'cash';
            $reference_number = trim($input['reference_number'] ?? '');
            $amount = floatval($input['amount'] ?? 0);
            $notes = trim($input['notes'] ?? '');

            if (!$ar_id) {
                throw new Exception('AR ID is required');
            }
            if ($amount <= 0) {
                throw new Exception('Payment amount must be greater than 0');
            }

            $ar = $db->fetch("SELECT * FROM accounts_receivable WHERE id = ?", [$ar_id]);
            if (!$ar) {
                throw new Exception('Accounts receivable not found');
            }

            if ($ar['balance'] < $amount) {
                throw new Exception('Payment amount cannot exceed balance');
            }

            $db->beginTransaction();
            try {
                // Record payment
                $db->query(
                    "INSERT INTO ar_payments 
                     (accounts_receivable_id, payment_date, payment_method, reference_number, amount, notes, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$ar_id, $payment_date, $payment_method, $reference_number, $amount, $notes, $_SESSION['user_id']]
                );

                // Update AR balance
                $new_paid = $ar['paid_amount'] + $amount;
                $new_balance = $ar['balance'] - $amount;
                $new_status = 'paid';
                if ($new_balance > 0) {
                    $new_status = $ar['paid_amount'] > 0 ? 'partial' : 'open';
                }

                // Check if overdue
                if ($new_balance > 0 && strtotime($ar['due_date']) < time()) {
                    $new_status = 'overdue';
                }

                $db->query(
                    "UPDATE accounts_receivable SET 
                     paid_amount = ?, balance = ?, status = ?, payment_date = ?
                     WHERE id = ?",
                    [$new_paid, $new_balance, $new_status, $new_balance == 0 ? date('Y-m-d') : null, $ar_id]
                );

                // Update customer balance
                $db->query(
                    "UPDATE customers SET balance = balance - ? WHERE id = ?",
                    [$amount, $ar['customer_id']]
                );

                // Update customer standing if needed
                $customer = $db->fetch("SELECT * FROM customers WHERE id = ?", [$ar['customer_id']]);
                $outstanding = $db->fetch(
                    "SELECT SUM(balance) as total, MAX(DATEDIFF(CURDATE(), due_date)) as max_days_overdue
                     FROM accounts_receivable 
                     WHERE customer_id = ? AND status IN ('open', 'partial', 'overdue')",
                    [$ar['customer_id']]
                );
                
                $new_standing = 'good';
                $bad_threshold = intval($db->fetch("SELECT setting_value FROM settings WHERE setting_key = 'bad_standing_threshold'")['setting_value'] ?? 90);
                $warning_threshold = intval($db->fetch("SELECT setting_value FROM settings WHERE setting_key = 'warning_standing_threshold'")['setting_value'] ?? 60);
                
                if ($outstanding['max_days_overdue'] >= $bad_threshold) {
                    $new_standing = 'bad';
                } elseif ($outstanding['max_days_overdue'] >= $warning_threshold) {
                    $new_standing = 'warning';
                }
                
                $db->query("UPDATE customers SET standing = ?, balance = ? WHERE id = ?", 
                    [$new_standing, $outstanding['total'] ?? 0, $ar['customer_id']]);

                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Payment collected successfully']);
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        case 'get':
            $id = intval($_GET['id'] ?? 0);
            $ar = $db->fetch(
                "SELECT ar.*, c.customer_code, c.customer_name, t.transaction_number 
                 FROM accounts_receivable ar 
                 JOIN customers c ON ar.customer_id = c.id
                 JOIN transactions t ON ar.transaction_id = t.id
                 WHERE ar.id = ?",
                [$id]
            );
            if (!$ar) {
                throw new Exception('Accounts receivable not found');
            }
            $ar['payments'] = $db->fetchAll(
                "SELECT ap.*, u.full_name as created_by_name 
                 FROM ar_payments ap 
                 JOIN users u ON ap.created_by = u.id 
                 WHERE ap.accounts_receivable_id = ? 
                 ORDER BY ap.created_at DESC",
                [$id]
            );
            echo json_encode(['success' => true, 'data' => $ar]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
