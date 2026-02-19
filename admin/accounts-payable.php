<?php
$pageTitle = 'Accounts Payable';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('manage_accounts_payable');
$db = new Database();

$page = $_GET['page'] ?? 1;
$supplier_id = $_GET['supplier_id'] ?? '';
$status = $_GET['status'] ?? '';
$po_id = $_GET['po_id'] ?? '';
$offset = ($page - 1) * ITEMS_PER_PAGE;

$where = "WHERE 1=1";
$params = [];

if ($supplier_id) {
    $where .= " AND ap.supplier_id = ?";
    $params[] = $supplier_id;
}

if ($status) {
    $where .= " AND ap.status = ?";
    $params[] = $status;
}

if ($po_id) {
    $where .= " AND ap.purchase_order_id = ?";
    $params[] = $po_id;
}

$ap_list = $db->fetchAll(
    "SELECT ap.*, s.supplier_name, po.po_number, po.order_date,
     DATEDIFF(CURDATE(), ap.due_date) as days_overdue
     FROM accounts_payable ap
     JOIN suppliers s ON ap.supplier_id = s.id
     JOIN purchase_orders po ON ap.purchase_order_id = po.id
     $where
     ORDER BY ap.due_date ASC
     LIMIT ? OFFSET ?",
    array_merge($params, [ITEMS_PER_PAGE, $offset])
);

$total = $db->fetch("SELECT COUNT(*) as count FROM accounts_payable ap $where", $params)['count'];
$total_pages = ceil($total / ITEMS_PER_PAGE);

$suppliers = $db->fetchAll("SELECT id, supplier_name FROM suppliers WHERE is_active = 1 ORDER BY supplier_name");

// Summary
$summary = $db->fetch(
    "SELECT 
     SUM(CASE WHEN ap.status = 'open' THEN ap.balance ELSE 0 END) as open_balance,
     SUM(CASE WHEN ap.status = 'partial' THEN ap.balance ELSE 0 END) as partial_balance,
     SUM(CASE WHEN ap.status = 'overdue' THEN ap.balance ELSE 0 END) as overdue_balance,
     SUM(ap.balance) as total_balance
     FROM accounts_payable ap
     $where",
    $params
);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-receipt"></i> Accounts Payable</h2>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted">Open</h6>
                <h4 class="text-info">₱<?php echo number_format($summary['open_balance'] ?? 0, 2); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted">Partial</h6>
                <h4 class="text-warning">₱<?php echo number_format($summary['partial_balance'] ?? 0, 2); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted">Overdue</h6>
                <h4 class="text-danger">₱<?php echo number_format($summary['overdue_balance'] ?? 0, 2); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted">Total Balance</h6>
                <h4 class="text-primary">₱<?php echo number_format($summary['total_balance'] ?? 0, 2); ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <select class="form-select" name="supplier_id">
                    <option value="">All Suppliers</option>
                    <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?php echo $supplier['id']; ?>" <?php echo $supplier_id == $supplier['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="open" <?php echo $status == 'open' ? 'selected' : ''; ?>>Open</option>
                    <option value="partial" <?php echo $status == 'partial' ? 'selected' : ''; ?>>Partial</option>
                    <option value="paid" <?php echo $status == 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="overdue" <?php echo $status == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>PO Number</th>
                        <th>Supplier</th>
                        <th>Invoice Date</th>
                        <th>Due Date</th>
                        <th>Amount</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Days Overdue</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ap_list as $ap): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ap['invoice_number'] ?? '-'); ?></td>
                        <td><a href="<?php echo APP_URL; ?>/admin/purchase-order-details.php?id=<?php echo $ap['purchase_order_id']; ?>"><?php echo htmlspecialchars($ap['po_number']); ?></a></td>
                        <td><?php echo htmlspecialchars($ap['supplier_name']); ?></td>
                        <td><?php echo $ap['invoice_date'] ? date('M d, Y', strtotime($ap['invoice_date'])) : '-'; ?></td>
                        <td><?php echo date('M d, Y', strtotime($ap['due_date'])); ?></td>
                        <td>₱<?php echo number_format($ap['amount'], 2); ?></td>
                        <td>₱<?php echo number_format($ap['paid_amount'], 2); ?></td>
                        <td>
                            <span class="<?php echo $ap['balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                ₱<?php echo number_format($ap['balance'], 2); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $ap['status'] == 'paid' ? 'success' : 
                                    ($ap['status'] == 'overdue' ? 'danger' : 
                                    ($ap['status'] == 'partial' ? 'warning' : 'info')); 
                            ?>"><?php echo ucfirst($ap['status']); ?></span>
                        </td>
                        <td>
                            <?php if ($ap['days_overdue'] > 0 && $ap['status'] != 'paid'): ?>
                            <span class="text-danger"><?php echo $ap['days_overdue']; ?> days</span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="viewAP(<?php echo $ap['id']; ?>)">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if ($ap['balance'] > 0): ?>
                            <button class="btn btn-sm btn-success" onclick="recordPayment(<?php echo $ap['id']; ?>)">
                                <i class="bi bi-cash-coin"></i> Pay
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <nav>
            <ul class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&supplier_id=<?php echo $supplier_id; ?>&status=<?php echo $status; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="paymentForm">
                <input type="hidden" id="ap_id" name="ap_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Payment Date *</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method *</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="check">Check</option>
                            <option value="bank_transfer" selected>Bank Transfer</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Check/Reference Number</label>
                        <input type="text" class="form-control" id="reference_number" name="reference_number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount *</label>
                        <input type="number" class="form-control" id="payment_amount" name="amount" step="0.01" min="0.01" required>
                        <small class="text-muted">Balance: <span id="ap_balance">₱0.00</span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="payment_notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewAP(id) {
    window.location.href = '<?php echo APP_URL; ?>/admin/ap-details.php?id=' + id;
}

function recordPayment(id) {
    fetch('<?php echo APP_URL; ?>/api/accounts-payable.php?action=get&id=' + id)
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('ap_id').value = id;
            document.getElementById('ap_balance').textContent = '₱' + parseFloat(data.data.balance).toFixed(2);
            document.getElementById('payment_amount').max = data.data.balance;
            document.getElementById('payment_amount').value = data.data.balance;
            document.getElementById('payment_date').value = '<?php echo date('Y-m-d'); ?>';
            document.getElementById('payment_method').value = 'bank_transfer';
            document.getElementById('reference_number').value = '';
            document.getElementById('payment_notes').value = '';
            
            const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
            modal.show();
        } else {
            showError(data.message || 'Error loading AP details');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showError('Error loading AP details');
    });
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
jQuery(document).ready(function($) {
    $('#paymentForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            action: 'record_payment',
            ap_id: document.getElementById('ap_id').value,
            payment_date: document.getElementById('payment_date').value,
            payment_method: document.getElementById('payment_method').value,
            reference_number: document.getElementById('reference_number').value,
            amount: parseFloat(document.getElementById('payment_amount').value),
            notes: document.getElementById('payment_notes').value
        };
        
        $.ajax({
            url: '<?php echo APP_URL; ?>/api/accounts-payable.php',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
                    showSuccess('Payment recorded successfully').then(() => location.reload());
                } else {
                    showError(response.message || 'Error recording payment');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                showError('Error recording payment: ' + (xhr.responseJSON?.message || error));
            }
        });
    });
});
</script>
