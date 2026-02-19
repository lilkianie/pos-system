<?php
$pageTitle = 'Transactions';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('view_reports');
$db = new Database();

$page = $_GET['page'] ?? 1;
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$offset = ($page - 1) * ITEMS_PER_PAGE;

$where = "WHERE DATE(t.created_at) BETWEEN ? AND ?";
$params = [$date_from, $date_to];

if ($search) {
    $where .= " AND (t.transaction_number LIKE ? OR u.full_name LIKE ? OR c.customer_code LIKE ? OR c.customer_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where .= " AND t.payment_status = ?";
    $params[] = $status_filter;
}

$transactions = $db->fetchAll(
    "SELECT t.*, u.full_name as cashier_name, u2.full_name as voided_by_name,
     c.customer_code, c.customer_name
     FROM transactions t 
     JOIN users u ON t.user_id = u.id 
     LEFT JOIN users u2 ON t.voided_by = u2.id
     LEFT JOIN customers c ON t.customer_id = c.id
     $where 
     ORDER BY t.created_at DESC 
     LIMIT ? OFFSET ?",
    array_merge($params, [ITEMS_PER_PAGE, $offset])
);

$total = $db->fetch(
    "SELECT COUNT(*) as count 
     FROM transactions t 
     JOIN users u ON t.user_id = u.id 
     LEFT JOIN customers c ON t.customer_id = c.id
     $where", 
    $params
)['count'];
$total_pages = ceil($total / ITEMS_PER_PAGE);

$can_void = $auth->hasPermission('void_transactions');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-receipt"></i> Transactions</h2>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" class="form-control" name="search" placeholder="Search transaction number, cashier, or customer..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="voided" <?php echo $status_filter == 'voided' ? 'selected' : ''; ?>>Voided</option>
                    <option value="refunded" <?php echo $status_filter == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="transactions.php" class="btn btn-outline-secondary">Reset</a>
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
                        <th>Transaction #</th>
                        <th>Date & Time</th>
                        <th>Cashier</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $trans): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($trans['transaction_number']); ?></strong></td>
                        <td><?php echo date('M d, Y h:i A', strtotime($trans['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($trans['cashier_name']); ?></td>
                        <td>
                            <?php if ($trans['customer_code']): ?>
                            <a href="<?php echo APP_URL; ?>/admin/customer-details.php?id=<?php echo $trans['customer_id']; ?>">
                                <?php echo htmlspecialchars($trans['customer_code']); ?><br>
                                <small><?php echo htmlspecialchars($trans['customer_name']); ?></small>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">Walk-in</span>
                            <?php endif; ?>
                        </td>
                        <td><strong>₱<?php echo number_format($trans['final_amount'], 2); ?></strong></td>
                        <td>
                            <span class="badge bg-info">
                                <?php echo ucfirst(str_replace('_', ' ', $trans['payment_method'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $status_class = [
                                'completed' => 'bg-success',
                                'voided' => 'bg-danger',
                                'refunded' => 'bg-warning',
                                'pending' => 'bg-secondary'
                            ];
                            $class = $status_class[$trans['payment_status']] ?? 'bg-secondary';
                            ?>
                            <span class="badge <?php echo $class; ?>">
                                <?php echo ucfirst($trans['payment_status']); ?>
                            </span>
                            <?php if ($trans['voided_at']): ?>
                                <br><small class="text-muted">Voided: <?php echo date('M d, Y h:i A', strtotime($trans['voided_at'])); ?></small>
                                <?php if ($trans['voided_by_name']): ?>
                                    <br><small class="text-muted">By: <?php echo htmlspecialchars($trans['voided_by_name']); ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="viewTransaction(<?php echo $trans['id']; ?>)">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <?php if ($can_void && $trans['payment_status'] == 'completed'): ?>
                            <button class="btn btn-sm btn-danger" onclick="voidTransaction(<?php echo $trans['id']; ?>, '<?php echo htmlspecialchars($trans['transaction_number']); ?>')">
                                <i class="bi bi-x-circle"></i> Void
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
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- View Transaction Modal -->
<div class="modal fade" id="viewTransactionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="transactionDetails">
                <p class="text-center">Loading...</p>
            </div>
        </div>
    </div>
</div>

<!-- Void Transaction Modal -->
<div class="modal fade" id="voidTransactionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Void Transaction</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="voidTransactionForm">
                <div class="modal-body">
                    <input type="hidden" id="void_transaction_id" name="transaction_id">
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> This action cannot be undone. The transaction will be marked as voided and inventory will be restored.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Transaction Number</label>
                        <input type="text" class="form-control" id="void_transaction_number" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason for Void <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="void_reason" name="reason" rows="3" required placeholder="Enter reason for voiding this transaction..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle"></i> Confirm Void
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewTransaction(id) {
    fetch('<?php echo APP_URL; ?>/api/transactions.php?action=get&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const trans = data.transaction;
                const items = data.items || [];
                
                let html = `
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Transaction Number:</strong> ${trans.transaction_number}<br>
                            <strong>Date:</strong> ${new Date(trans.created_at).toLocaleString()}<br>
                            <strong>Cashier:</strong> ${trans.cashier_name}<br>
                            <strong>Payment Method:</strong> ${trans.payment_method.replace('_', ' ').toUpperCase()}
                        </div>
                        <div class="col-md-6">
                            <strong>Subtotal:</strong> ₱${parseFloat(trans.total_amount).toFixed(2)}<br>
                            <strong>Discount:</strong> ₱${parseFloat(trans.discount_amount).toFixed(2)}<br>
                            <strong>Tax:</strong> ₱${parseFloat(trans.tax_amount).toFixed(2)}<br>
                            <strong>Total:</strong> <span class="h5">₱${parseFloat(trans.final_amount).toFixed(2)}</span>
                        </div>
                    </div>
                    <hr>
                    <h6>Items:</h6>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                items.forEach(item => {
                    html += `
                        <tr>
                            <td>${item.product_name}</td>
                            <td>${item.quantity}</td>
                            <td>₱${parseFloat(item.unit_price).toFixed(2)}</td>
                            <td>₱${parseFloat(item.subtotal).toFixed(2)}</td>
                        </tr>
                    `;
                });
                
                html += `
                        </tbody>
                    </table>
                `;
                
                if (trans.voided_at) {
                    html += `
                        <hr>
                        <div class="alert alert-danger">
                            <strong>Voided:</strong> ${new Date(trans.voided_at).toLocaleString()}<br>
                            ${trans.voided_by_name ? '<strong>By:</strong> ' + trans.voided_by_name + '<br>' : ''}
                            ${trans.void_reason ? '<strong>Reason:</strong> ' + trans.void_reason : ''}
                        </div>
                    `;
                }
                
                document.getElementById('transactionDetails').innerHTML = html;
                new bootstrap.Modal(document.getElementById('viewTransactionModal')).show();
            } else {
                showError('Error loading transaction details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error loading transaction details');
        });
}

function voidTransaction(id, transactionNumber) {
    document.getElementById('void_transaction_id').value = id;
    document.getElementById('void_transaction_number').value = transactionNumber;
    document.getElementById('void_reason').value = '';
    new bootstrap.Modal(document.getElementById('voidTransactionModal')).show();
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
jQuery(document).ready(function($) {
    $('#voidTransactionForm').on('submit', function(e) {
        e.preventDefault();
        
        const transactionId = document.getElementById('void_transaction_id').value;
        const reason = document.getElementById('void_reason').value;
        
        if (!reason.trim()) {
            showWarning('Please enter a reason for voiding this transaction');
            return;
        }
        
        showConfirm(
            'Are you sure you want to void this transaction? This action cannot be undone.',
            'Void Transaction',
            'Yes, void it',
            'Cancel'
        ).then((result) => {
            if (!result.isConfirmed) {
                return;
            }
        
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Processing...');
            
            $.ajax({
                url: '<?php echo APP_URL; ?>/api/transactions.php',
                method: 'POST',
                data: {
                    action: 'void',
                    transaction_id: transactionId,
                    reason: reason
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        bootstrap.Modal.getInstance(document.getElementById('voidTransactionModal')).hide();
                        showSuccess('Transaction voided successfully').then(() => {
                            location.reload();
                        });
                    } else {
                        showError(response.message || 'Error voiding transaction');
                        submitBtn.prop('disabled', false).html(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    console.error('Response:', xhr.responseText);
                    showError('Error voiding transaction: ' + (xhr.responseJSON?.message || error));
                    submitBtn.prop('disabled', false).html(originalText);
                }
            });
        });
    });
});
</script>
