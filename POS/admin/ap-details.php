<?php
$pageTitle = 'AP Details';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('manage_accounts_payable');
$db = new Database();

$ap_id = $_GET['id'] ?? 0;

$ap = $db->fetch(
    "SELECT ap.*, s.supplier_name, po.po_number, po.order_date
     FROM accounts_payable ap
     JOIN suppliers s ON ap.supplier_id = s.id
     JOIN purchase_orders po ON ap.purchase_order_id = po.id
     WHERE ap.id = ?",
    [$ap_id]
);

if (!$ap) {
    header('Location: ' . APP_URL . '/admin/accounts-payable.php');
    exit;
}

$payments = $db->fetchAll(
    "SELECT ap.*, u.full_name as created_by_name
     FROM ap_payments ap
     JOIN users u ON ap.created_by = u.id
     WHERE ap.accounts_payable_id = ?
     ORDER BY ap.created_at DESC",
    [$ap_id]
);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-receipt-cutoff"></i> Accounts Payable Details</h2>
    <a href="<?php echo APP_URL; ?>/admin/accounts-payable.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header">
                <h5>Invoice Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Invoice Number:</strong> <?php echo htmlspecialchars($ap['invoice_number'] ?? '-'); ?></p>
                        <p><strong>PO Number:</strong> <a href="<?php echo APP_URL; ?>/admin/purchase-order-details.php?id=<?php echo $ap['purchase_order_id']; ?>"><?php echo htmlspecialchars($ap['po_number']); ?></a></p>
                        <p><strong>Supplier:</strong> <?php echo htmlspecialchars($ap['supplier_name']); ?></p>
                        <p><strong>Invoice Date:</strong> <?php echo $ap['invoice_date'] ? date('M d, Y', strtotime($ap['invoice_date'])) : '-'; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Due Date:</strong> <?php echo date('M d, Y', strtotime($ap['due_date'])); ?></p>
                        <p><strong>Amount:</strong> ₱<?php echo number_format($ap['amount'], 2); ?></p>
                        <p><strong>Paid:</strong> ₱<?php echo number_format($ap['paid_amount'], 2); ?></p>
                        <p><strong>Balance:</strong> 
                            <span class="<?php echo $ap['balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                ₱<?php echo number_format($ap['balance'], 2); ?>
                            </span>
                        </p>
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?php 
                                echo $ap['status'] == 'paid' ? 'success' : 
                                    ($ap['status'] == 'overdue' ? 'danger' : 
                                    ($ap['status'] == 'partial' ? 'warning' : 'info')); 
                            ?>"><?php echo ucfirst($ap['status']); ?></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5>Payment History</h5>
            </div>
            <div class="card-body">
                <?php if (empty($payments)): ?>
                <p class="text-muted">No payments recorded yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Recorded By</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                <td><?php echo htmlspecialchars($payment['reference_number'] ?? '-'); ?></td>
                                <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($payment['created_by_name']); ?></td>
                                <td><?php echo htmlspecialchars($payment['notes'] ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <?php if ($ap['balance'] > 0): ?>
        <div class="card">
            <div class="card-header">
                <h5>Record Payment</h5>
            </div>
            <div class="card-body">
                <button class="btn btn-success w-100" onclick="recordPayment(<?php echo $ap_id; ?>)">
                    <i class="bi bi-cash-coin"></i> Record Payment
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function recordPayment(id) {
    window.location.href = '<?php echo APP_URL; ?>/admin/accounts-payable.php?po_id=' + id + '#payment';
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
