<?php
$pageTitle = 'Accounts Receivable';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('manage_accounts_receivable');
$db = new Database();

$page = $_GET['page'] ?? 1;
$customer_id = $_GET['customer_id'] ?? '';
$status = $_GET['status'] ?? '';
$offset = ($page - 1) * ITEMS_PER_PAGE;

$where = "WHERE 1=1";
$params = [];

if ($customer_id) {
    $where .= " AND ar.customer_id = ?";
    $params[] = $customer_id;
}

if ($status) {
    $where .= " AND ar.status = ?";
    $params[] = $status;
}

$ar_list = $db->fetchAll(
    "SELECT ar.*, c.customer_code, c.customer_name, c.standing, t.transaction_number,
     DATEDIFF(CURDATE(), ar.due_date) as days_overdue
     FROM accounts_receivable ar
     JOIN customers c ON ar.customer_id = c.id
     JOIN transactions t ON ar.transaction_id = t.id
     $where
     ORDER BY ar.due_date ASC
     LIMIT ? OFFSET ?",
    array_merge($params, [ITEMS_PER_PAGE, $offset])
);

$total = $db->fetch("SELECT COUNT(*) as count FROM accounts_receivable ar $where", $params)['count'];
$total_pages = ceil($total / ITEMS_PER_PAGE);

$customers = $db->fetchAll("SELECT id, customer_code, customer_name FROM customers WHERE is_active = 1 ORDER BY customer_name");

// Summary
$summary = $db->fetch(
    "SELECT 
     SUM(CASE WHEN ar.status = 'open' THEN ar.balance ELSE 0 END) as open_balance,
     SUM(CASE WHEN ar.status = 'partial' THEN ar.balance ELSE 0 END) as partial_balance,
     SUM(CASE WHEN ar.status = 'overdue' THEN ar.balance ELSE 0 END) as overdue_balance,
     SUM(ar.balance) as total_balance
     FROM accounts_receivable ar
     $where",
    $params
);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-receipt-cutoff"></i> Accounts Receivable</h2>
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
                <select class="form-select" name="customer_id">
                    <option value="">All Customers</option>
                    <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo $customer['id']; ?>" <?php echo $customer_id == $customer['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($customer['customer_code'] . ' - ' . $customer['customer_name']); ?>
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
                        <th>Transaction</th>
                        <th>Customer</th>
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
                    <?php foreach ($ar_list as $ar): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ar['invoice_number'] ?? '-'); ?></td>
                        <td><a href="<?php echo APP_URL; ?>/admin/transactions.php?search=<?php echo urlencode($ar['transaction_number']); ?>"><?php echo htmlspecialchars($ar['transaction_number']); ?></a></td>
                        <td>
                            <?php echo htmlspecialchars($ar['customer_code'] . ' - ' . $ar['customer_name']); ?>
                            <?php if ($ar['standing'] != 'good'): ?>
                            <span class="badge bg-<?php echo $ar['standing'] == 'bad' ? 'danger' : 'warning'; ?>">
                                <?php echo ucfirst($ar['standing']); ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($ar['invoice_date'])); ?></td>
                        <td><?php echo date('M d, Y', strtotime($ar['due_date'])); ?></td>
                        <td>₱<?php echo number_format($ar['amount'], 2); ?></td>
                        <td>₱<?php echo number_format($ar['paid_amount'], 2); ?></td>
                        <td>
                            <span class="<?php echo $ar['balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                ₱<?php echo number_format($ar['balance'], 2); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $ar['status'] == 'paid' ? 'success' : 
                                    ($ar['status'] == 'overdue' ? 'danger' : 
                                    ($ar['status'] == 'partial' ? 'warning' : 'info')); 
                            ?>"><?php echo ucfirst($ar['status']); ?></span>
                        </td>
                        <td>
                            <?php if ($ar['days_overdue'] > 0 && $ar['status'] != 'paid'): ?>
                            <span class="text-danger"><?php echo $ar['days_overdue']; ?> days</span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="viewAR(<?php echo $ar['id']; ?>)">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if ($ar['balance'] > 0): ?>
                            <button class="btn btn-sm btn-success" onclick="collectPayment(<?php echo $ar['id']; ?>)">
                                <i class="bi bi-cash-coin"></i> Collect
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
                    <a class="page-link" href="?page=<?php echo $i; ?>&customer_id=<?php echo $customer_id; ?>&status=<?php echo $status; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Payment Collection Modal -->
<div class="modal fade" id="collectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Collect Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="collectionForm">
                <input type="hidden" id="ar_id" name="ar_id">
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
                            <option value="bank_transfer">Bank Transfer</option>
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
                        <small class="text-muted">Balance: <span id="ar_balance">₱0.00</span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="collection_notes" name="notes" rows="3"></textarea>
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
function viewAR(id) {
    window.location.href = '<?php echo APP_URL; ?>/admin/ar-details.php?id=' + id;
}

function collectPayment(id) {
    fetch('<?php echo APP_URL; ?>/api/accounts-receivable.php?action=get&id=' + id)
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('ar_id').value = id;
            document.getElementById('ar_balance').textContent = '₱' + parseFloat(data.data.balance).toFixed(2);
            document.getElementById('payment_amount').max = data.data.balance;
            document.getElementById('payment_amount').value = data.data.balance;
            document.getElementById('payment_date').value = '<?php echo date('Y-m-d'); ?>';
            document.getElementById('payment_method').value = 'cash';
            document.getElementById('reference_number').value = '';
            document.getElementById('collection_notes').value = '';
            
            const modal = new bootstrap.Modal(document.getElementById('collectionModal'));
            modal.show();
        } else {
            showError(data.message || 'Error loading AR details');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showError('Error loading AR details');
    });
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
jQuery(document).ready(function($) {
    $('#collectionForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            action: 'collect_payment',
            ar_id: document.getElementById('ar_id').value,
            payment_date: document.getElementById('payment_date').value,
            payment_method: document.getElementById('payment_method').value,
            reference_number: document.getElementById('reference_number').value,
            amount: parseFloat(document.getElementById('payment_amount').value),
            notes: document.getElementById('collection_notes').value
        };
        
        $.ajax({
            url: '<?php echo APP_URL; ?>/api/accounts-receivable.php',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    bootstrap.Modal.getInstance(document.getElementById('collectionModal')).hide();
                    showSuccess('Payment collected successfully').then(() => location.reload());
                } else {
                    showError(response.message || 'Error collecting payment');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                showError('Error collecting payment: ' + (xhr.responseJSON?.message || error));
            }
        });
    });
});
</script>
