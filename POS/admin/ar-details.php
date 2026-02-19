<?php
$pageTitle = 'AR Details';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('manage_accounts_receivable');
$db = new Database();

$ar_id = $_GET['id'] ?? 0;

$ar = $db->fetch(
    "SELECT ar.*, c.customer_code, c.customer_name, c.standing, t.transaction_number, t.created_at as transaction_date
     FROM accounts_receivable ar
     JOIN customers c ON ar.customer_id = c.id
     JOIN transactions t ON ar.transaction_id = t.id
     WHERE ar.id = ?",
    [$ar_id]
);

if (!$ar) {
    header('Location: ' . APP_URL . '/admin/accounts-receivable.php');
    exit;
}

$payments = $db->fetchAll(
    "SELECT ap.*, u.full_name as created_by_name
     FROM ar_payments ap
     JOIN users u ON ap.created_by = u.id
     WHERE ap.accounts_receivable_id = ?
     ORDER BY ap.created_at DESC",
    [$ar_id]
);

$transaction_items = $db->fetchAll(
    "SELECT ti.*, p.product_name, p.barcode
     FROM transaction_items ti
     JOIN products p ON ti.product_id = p.id
     WHERE ti.transaction_id = ?
     ORDER BY ti.id",
    [$ar['transaction_id']]
);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-receipt-cutoff"></i> Accounts Receivable Details</h2>
    <a href="<?php echo APP_URL; ?>/admin/accounts-receivable.php" class="btn btn-secondary">
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
                        <p><strong>Invoice Number:</strong> <?php echo htmlspecialchars($ar['invoice_number'] ?? '-'); ?></p>
                        <p><strong>Transaction:</strong> <a href="<?php echo APP_URL; ?>/admin/transactions.php?search=<?php echo urlencode($ar['transaction_number']); ?>"><?php echo htmlspecialchars($ar['transaction_number']); ?></a></p>
                        <p><strong>Customer:</strong> <a href="<?php echo APP_URL; ?>/admin/customer-details.php?id=<?php echo $ar['customer_id']; ?>"><?php echo htmlspecialchars($ar['customer_code'] . ' - ' . $ar['customer_name']); ?></a></p>
                        <?php if ($ar['standing'] != 'good'): ?>
                        <p><strong>Standing:</strong> 
                            <span class="badge bg-<?php echo $ar['standing'] == 'bad' ? 'danger' : 'warning'; ?>">
                                <?php echo ucfirst($ar['standing']); ?>
                            </span>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Invoice Date:</strong> <?php echo date('M d, Y', strtotime($ar['invoice_date'])); ?></p>
                        <p><strong>Due Date:</strong> <?php echo date('M d, Y', strtotime($ar['due_date'])); ?></p>
                        <p><strong>Transaction Date:</strong> <?php echo date('M d, Y h:i A', strtotime($ar['transaction_date'])); ?></p>
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?php 
                                echo $ar['status'] == 'paid' ? 'success' : 
                                    ($ar['status'] == 'overdue' ? 'danger' : 
                                    ($ar['status'] == 'partial' ? 'warning' : 'info')); 
                            ?>"><?php echo ucfirst($ar['status']); ?></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5>Transaction Items</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transaction_items as $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($item['barcode']); ?></small>
                                </td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td>₱<?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                <td><strong>₱<?php echo number_format($ar['amount'], 2); ?></strong></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Paid:</strong></td>
                                <td>₱<?php echo number_format($ar['paid_amount'], 2); ?></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Balance:</strong></td>
                                <td>
                                    <span class="<?php echo $ar['balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <strong>₱<?php echo number_format($ar['balance'], 2); ?></strong>
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($ar['points_earned'] > 0): ?>
        <div class="card mb-3">
            <div class="card-body">
                <p class="mb-0"><strong>Points Earned:</strong> <span class="badge bg-warning text-dark"><?php echo $ar['points_earned']; ?> pts</span></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($ar['notes']): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h5>Notes</h5>
            </div>
            <div class="card-body">
                <p><?php echo nl2br(htmlspecialchars($ar['notes'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-md-4">
        <?php if ($ar['balance'] > 0): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h5>Collect Payment</h5>
            </div>
            <div class="card-body">
                <button class="btn btn-success w-100" onclick="collectPayment(<?php echo $ar_id; ?>)">
                    <i class="bi bi-cash-coin"></i> Record Payment
                </button>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5>Payment History</h5>
            </div>
            <div class="card-body">
                <?php if (empty($payments)): ?>
                <p class="text-muted">No payments recorded yet.</p>
                <?php else: ?>
                <div class="list-group">
                    <?php foreach ($payments as $payment): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>₱<?php echo number_format($payment['amount'], 2); ?></strong><br>
                                <small class="text-muted">
                                    <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?><br>
                                    <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                    <?php if ($payment['reference_number']): ?>
                                    <br>Ref: <?php echo htmlspecialchars($payment['reference_number']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                        <?php if ($payment['notes']): ?>
                        <small class="text-muted"><?php echo htmlspecialchars($payment['notes']); ?></small>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function collectPayment(id) {
    window.location.href = '<?php echo APP_URL; ?>/admin/accounts-receivable.php';
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
