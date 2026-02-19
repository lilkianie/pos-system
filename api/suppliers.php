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
            $auth->requirePermission('manage_suppliers');
            
            $id = intval($_POST['id'] ?? 0);
            $supplier_name = trim($_POST['supplier_name'] ?? '');
            $contact_person = trim($_POST['contact_person'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $zip_code = trim($_POST['zip_code'] ?? '');
            $payment_terms = intval($_POST['payment_terms'] ?? 30);
            $credit_limit = floatval($_POST['credit_limit'] ?? 0);
            $tax_id = trim($_POST['tax_id'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (!$supplier_name) {
                throw new Exception('Supplier name is required');
            }

            if ($id > 0) {
                // Update
                $db->query(
                    "UPDATE suppliers SET 
                     supplier_name = ?, contact_person = ?, email = ?, phone = ?, address = ?,
                     city = ?, state = ?, zip_code = ?, payment_terms = ?, credit_limit = ?,
                     tax_id = ?, notes = ?, is_active = ?
                     WHERE id = ?",
                    [$supplier_name, $contact_person, $email, $phone, $address, $city, $state, 
                     $zip_code, $payment_terms, $credit_limit, $tax_id, $notes, $is_active, $id]
                );
            } else {
                // Insert
                $db->query(
                    "INSERT INTO suppliers 
                     (supplier_name, contact_person, email, phone, address, city, state, zip_code, 
                      payment_terms, credit_limit, tax_id, notes, is_active) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$supplier_name, $contact_person, $email, $phone, $address, $city, $state,
                     $zip_code, $payment_terms, $credit_limit, $tax_id, $notes, $is_active]
                );
            }

            echo json_encode(['success' => true, 'message' => 'Supplier saved successfully']);
            break;

        case 'delete':
            $auth->requirePermission('manage_suppliers');
            
            $id = intval($_POST['id'] ?? 0);
            
            // Check if supplier has purchase orders
            $po_count = $db->fetch("SELECT COUNT(*) as count FROM purchase_orders WHERE supplier_id = ?", [$id])['count'];
            if ($po_count > 0) {
                throw new Exception('Cannot delete supplier with existing purchase orders');
            }

            $db->query("UPDATE suppliers SET is_active = 0 WHERE id = ?", [$id]);
            echo json_encode(['success' => true, 'message' => 'Supplier deleted successfully']);
            break;

        case 'get':
            $id = intval($_GET['id'] ?? 0);
            $supplier = $db->fetch("SELECT * FROM suppliers WHERE id = ?", [$id]);
            if (!$supplier) {
                throw new Exception('Supplier not found');
            }
            echo json_encode(['success' => true, 'data' => $supplier]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
