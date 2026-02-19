<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth();
$auth->requireLogin();
// Cashiers can manage their own cash counts
if (!$auth->hasPermission('manage_cash_count')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}
$db = new Database();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    switch ($action ?: ($input['action'] ?? '')) {
        case 'start_shift':
            $user_id = $_SESSION['user_id'];
            $shift_date = date('Y-m-d');
            $shift_type = $input['shift_type'] ?? 'full_day';
            $allowed_types = ['morning', 'afternoon', 'night', 'full_day'];
            if (!in_array($shift_type, $allowed_types, true)) {
                $shift_type = 'full_day';
            }
            $beginning_cash = floatval($input['beginning_cash'] ?? 0);
            $beginning_notes = $input['beginning_notes'] ?? '';
            $denominations = $input['denominations'] ?? [];

            if ($beginning_cash <= 0) {
                throw new Exception('Beginning cash amount must be greater than 0');
            }

            // Check if this shift type is already open for today
            $existing = $db->fetch(
                "SELECT * FROM cash_counts 
                 WHERE user_id = ? AND shift_date = ? AND shift_type = ? AND status = 'open'",
                [$user_id, $shift_date, $shift_type]
            );

            if ($existing) {
                throw new Exception('You already have an open ' . $shift_type . ' shift for today');
            }

            $db->beginTransaction();
            try {
                // Insert cash count with shift_type
                $db->query(
                    "INSERT INTO cash_counts (user_id, shift_date, shift_type, beginning_cash, beginning_notes, status) 
                     VALUES (?, ?, ?, ?, ?, 'open')",
                    [$user_id, $shift_date, $shift_type, $beginning_cash, $beginning_notes]
                );

                $cash_count_id = $db->lastInsertId();

                // Insert denominations
                foreach ($denominations as $denom) {
                    $db->query(
                        "INSERT INTO cash_count_items (cash_count_id, denomination, quantity, total_amount, count_type) 
                         VALUES (?, ?, ?, ?, 'beginning')",
                        [$cash_count_id, $denom['denomination'], $denom['quantity'], $denom['total_amount']]
                    );
                }

                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Shift started successfully']);
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        case 'end_shift':
            $cash_count_id = intval($input['cash_count_id'] ?? 0);
            $ending_cash = floatval($input['ending_cash'] ?? 0);
            $ending_notes = $input['ending_notes'] ?? '';
            $denominations = $input['denominations'] ?? [];

            if ($ending_cash <= 0) {
                throw new Exception('Ending cash amount must be greater than 0');
            }

            // Get cash count
            $cashCount = $db->fetch(
                "SELECT * FROM cash_counts WHERE id = ? AND user_id = ?",
                [$cash_count_id, $_SESSION['user_id']]
            );

            if (!$cashCount) {
                throw new Exception('Cash count not found');
            }

            if ($cashCount['status'] == 'closed') {
                throw new Exception('Shift is already closed');
            }

            // Calculate expected cash
            $todaySales = $db->fetch(
                "SELECT COALESCE(SUM(final_amount), 0) as total 
                 FROM transactions 
                 WHERE user_id = ? 
                 AND DATE(created_at) = ? 
                 AND payment_method = 'cash'
                 AND payment_status != 'voided'",
                [$cashCount['user_id'], $cashCount['shift_date']]
            )['total'] ?? 0;

            $expected_cash = $cashCount['beginning_cash'] + $todaySales;
            $difference = $ending_cash - $expected_cash;

            $db->beginTransaction();
            try {
                // Update cash count
                $db->query(
                    "UPDATE cash_counts 
                     SET ending_cash = ?, expected_cash = ?, actual_cash = ?, difference = ?, 
                         ending_notes = ?, status = 'closed', ended_at = NOW() 
                     WHERE id = ?",
                    [$ending_cash, $expected_cash, $ending_cash, $difference, $ending_notes, $cash_count_id]
                );

                // Insert ending denominations
                foreach ($denominations as $denom) {
                    $db->query(
                        "INSERT INTO cash_count_items (cash_count_id, denomination, quantity, total_amount, count_type) 
                         VALUES (?, ?, ?, ?, 'ending')",
                        [$cash_count_id, $denom['denomination'], $denom['quantity'], $denom['total_amount']]
                    );
                }

                $db->commit();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Shift ended successfully',
                    'difference' => $difference
                ]);
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        case 'get_cash_count':
            $id = $_GET['id'] ?? 0;
            $cashCount = $db->fetch(
                "SELECT cc.*, u.full_name as cashier_name 
                 FROM cash_counts cc 
                 JOIN users u ON cc.user_id = u.id 
                 WHERE cc.id = ?",
                [$id]
            );

            if (!$cashCount) {
                throw new Exception('Cash count not found');
            }

            $beginningItems = $db->fetchAll(
                "SELECT * FROM cash_count_items 
                 WHERE cash_count_id = ? AND count_type = 'beginning'",
                [$id]
            );

            $endingItems = $db->fetchAll(
                "SELECT * FROM cash_count_items 
                 WHERE cash_count_id = ? AND count_type = 'ending'",
                [$id]
            );

            echo json_encode([
                'success' => true,
                'cash_count' => $cashCount,
                'beginning_items' => $beginningItems,
                'ending_items' => $endingItems
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
