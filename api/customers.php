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
        case 'save':
            $auth->requirePermission('manage_customers');
            
            $id = intval($_POST['id'] ?? 0);
            $customer_code = trim($_POST['customer_code'] ?? '');
            $customer_name = trim($_POST['customer_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $zip_code = trim($_POST['zip_code'] ?? '');
            $customer_type = $_POST['customer_type'] ?? 'regular';
            $credit_limit = floatval($_POST['credit_limit'] ?? 0);
            $standing = $_POST['standing'] ?? 'good';
            $birth_date = $_POST['birth_date'] ?? null;
            $notes = trim($_POST['notes'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (!$customer_code) {
                throw new Exception('Customer code is required');
            }
            if (!$customer_name) {
                throw new Exception('Customer name is required');
            }

            // Check for duplicate customer code
            $existing = $db->fetch(
                "SELECT id FROM customers WHERE customer_code = ? AND id != ?",
                [$customer_code, $id]
            );
            if ($existing) {
                throw new Exception('Customer code already exists');
            }

            if ($id > 0) {
                // Update
                $db->query(
                    "UPDATE customers SET 
                     customer_code = ?, customer_name = ?, email = ?, phone = ?, address = ?,
                     city = ?, state = ?, zip_code = ?, customer_type = ?, credit_limit = ?,
                     standing = ?, birth_date = ?, notes = ?, is_active = ?
                     WHERE id = ?",
                    [$customer_code, $customer_name, $email, $phone, $address, $city, $state,
                     $zip_code, $customer_type, $credit_limit, $standing, $birth_date, $notes, $is_active, $id]
                );
            } else {
                // Insert
                $db->query(
                    "INSERT INTO customers 
                     (customer_code, customer_name, email, phone, address, city, state, zip_code, 
                      customer_type, credit_limit, standing, birth_date, notes, is_active) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$customer_code, $customer_name, $email, $phone, $address, $city, $state,
                     $zip_code, $customer_type, $credit_limit, $standing, $birth_date, $notes, $is_active]
                );
            }

            echo json_encode(['success' => true, 'message' => 'Customer saved successfully']);
            break;

        case 'delete':
            $auth->requirePermission('manage_customers');
            
            $id = intval($_POST['id'] ?? 0);
            
            // Check if customer has outstanding AR
            $ar_count = $db->fetch(
                "SELECT COUNT(*) as count FROM accounts_receivable WHERE customer_id = ? AND balance > 0",
                [$id]
            )['count'];
            if ($ar_count > 0) {
                throw new Exception('Cannot delete customer with outstanding balance');
            }

            $db->query("UPDATE customers SET is_active = 0 WHERE id = ?", [$id]);
            echo json_encode(['success' => true, 'message' => 'Customer deleted successfully']);
            break;

        case 'get':
            $id = intval($_GET['id'] ?? 0);
            $code = trim($_GET['code'] ?? '');
            
            if ($id > 0) {
                $customer = $db->fetch("SELECT * FROM customers WHERE id = ?", [$id]);
            } elseif ($code) {
                $customer = $db->fetch("SELECT * FROM customers WHERE customer_code = ?", [$code]);
            } else {
                throw new Exception('Customer ID or code is required');
            }
            
            if (!$customer) {
                throw new Exception('Customer not found');
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

        case 'search':
            $search = trim($_GET['search'] ?? '');
            $limit = intval($_GET['limit'] ?? 20);
            
            $customers = $db->fetchAll(
                "SELECT id, customer_code, customer_name, customer_type, credit_limit, standing, points_balance,
                 (SELECT SUM(balance) FROM accounts_receivable WHERE customer_id = customers.id AND status IN ('open', 'partial', 'overdue')) as outstanding_balance
                 FROM customers 
                 WHERE is_active = 1 
                 AND (customer_code LIKE ? OR customer_name LIKE ?)
                 ORDER BY customer_name
                 LIMIT ?",
                ["%$search%", "%$search%", $limit]
            );
            
            echo json_encode(['success' => true, 'data' => $customers]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
