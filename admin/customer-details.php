<?php
$pageTitle = 'Customer Details';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('manage_customers');
$db = new Database();

$customer_id = $_GET['id'] ?? 0;

$customer = $db->fetch(
    "SELECT * FROM customers WHERE id = ?",
    [$customer_id]
);

if (!$customer) {
    header('Location: ' . APP_URL . '/admin/customers.php');
    exit;
}

// Get outstanding balance
$outstanding = $db->fetch(
    "SELECT SUM(balance) as total FROM accounts_receivable 
     WHERE customer_id = ? AND status IN ('open', 'partial', 'overdue')",
    [$customer_id]
);
$customer['outstanding_balance'] = $outstanding['total'] ?? 0;

// Get AR invoices
$ar_invoices = $db->fetchAll(
    "SELECT ar.*, t.transaction_number,
     DATEDIFF(CURDATE(), ar.due_date) as days_overdue
     FROM accounts_receivable ar
     JOIN transactions t ON ar.transaction_id = t.id
     WHERE ar.customer_id = ?
     ORDER BY ar.created_at DESC
     LIMIT 20",
    [$customer_id]
);

// Get payment history
$payments = $db->fetchAll(
    "SELECT ap.*, ar.invoice_number, u.full_name as collected_by
     FROM ar_payments ap
     JOIN accounts_receivable ar ON ap.accounts_receivable_id = ar.id
     JOIN users u ON ap.created_by = u.id
     WHERE ar.customer_id = ?
     ORDER BY ap.created_at DESC
     LIMIT 20",
    [$customer_id]
);

// Get points transactions
$points_transactions = $db->fetchAll(
    "SELECT * FROM customer_points_transactions
     WHERE customer_id = ?
     ORDER BY created_at DESC
     LIMIT 20",
    [$customer_id]
);

// Get transaction history
$transactions = $db->fetchAll(
    "SELECT t.*, COUNT(ti.id) as item_count
     FROM transactions t
     LEFT JOIN transaction_items ti ON t.id = ti.transaction_id
     WHERE t.customer_id = ?
     GROUP BY t.id
     ORDER BY t.created_at DESC
     LIMIT 20",
    [$customer_id]
);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-person-circle"></i> Customer Details</h2>
    <div>
        <button class="btn btn-primary" onclick="editCustomer(<?php echo htmlspecialchars(json_encode($customer)); ?>)">
            <i class="bi bi-pencil"></i> Edit Customer
        </button>
        <a href="<?php echo APP_URL; ?>/admin/customers.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header">
                <h5>Customer Information</h5>
            </div>
            <div class="card-body">
                <p><strong>Code:</strong> <?php echo htmlspecialchars($customer['customer_code']); ?></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($customer['customer_name']); ?></p>
                <p><strong>Type:</strong> 
                    <span class="badge <?php echo $customer['customer_type'] == 'member' ? 'bg-info' : 'bg-secondary'; ?>">
                        <?php echo ucfirst($customer['customer_type']); ?>
                    </span>
                </p>
                <?php if ($customer['email']): ?>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($customer['email']); ?></p>
                <?php endif; ?>
                <?php if ($customer['phone']): ?>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($customer['phone']); ?></p>
                <?php endif; ?>
                <?php if ($customer['address']): ?>
                <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($customer['address'])); ?></p>
                <?php endif; ?>
                <p><strong>Standing:</strong> 
                    <?php
                    $standingColors = [
                        'good' => 'bg-success',
                        'warning' => 'bg-warning',
                        'bad' => 'bg-danger'
                    ];
                    $color = $standingColors[$customer['standing']] ?? 'bg-secondary';
                    ?>
                    <span class="badge <?php echo $color; ?>"><?php echo ucfirst($customer['standing']); ?></span>
                </p>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5>Account Summary</h5>
            </div>
            <div class="card-body">
                <p><strong>Credit Limit:</strong> ₱<?php echo number_format($customer['credit_limit'], 2); ?></p>
                <p><strong>Outstanding Balance:</strong> 
                    <span class="<?php echo $customer['outstanding_balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                        ₱<?php echo number_format($customer['outstanding_balance'], 2); ?>
                    </span>
                </p>
                <p><strong>Available Credit:</strong> 
                    <span class="text-success">
                        ₱<?php echo number_format(max(0, $customer['credit_limit'] - $customer['outstanding_balance']), 2); ?>
                    </span>
                </p>
                <?php if ($customer['customer_type'] == 'member'): ?>
                <hr>
                <p><strong>Points Balance:</strong> 
                    <span class="badge bg-warning text-dark"><?php echo number_format($customer['points_balance'] ?? 0); ?> pts</span>
                </p>
                <p><strong>Total Points Earned:</strong> <?php echo number_format($customer['points_earned_total'] ?? 0); ?> pts</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" id="customerTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="ar-tab" data-bs-toggle="tab" data-bs-target="#ar" type="button">
                    Accounts Receivable
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button">
                    Payment History
                </button>
            </li>
            <?php if ($customer['customer_type'] == 'member'): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="points-tab" data-bs-toggle="tab" data-bs-target="#points" type="button">
                    Points History
                </button>
            </li>
            <?php endif; ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button">
                    Transactions
                </button>
            </li>
        </ul>

        <div class="tab-content" id="customerTabContent">
            <!-- AR Tab -->
            <div class="tab-pane fade show active" id="ar" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Transaction</th>
                                        <th>Date</th>
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
                                    <?php foreach ($ar_invoices as $ar): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($ar['invoice_number'] ?? '-'); ?></td>
                                        <td><a href="<?php echo APP_URL; ?>/admin/transactions.php?search=<?php echo urlencode($ar['transaction_number']); ?>"><?php echo htmlspecialchars($ar['transaction_number']); ?></a></td>
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
                                            <?php if ($ar['balance'] > 0): ?>
                                            <button class="btn btn-sm btn-success" onclick="collectPayment(<?php echo $ar['id']; ?>)">
                                                <i class="bi bi-cash-coin"></i>
                                            </button>
                                            <?php endif; ?>
                                            <a href="<?php echo APP_URL; ?>/admin/ar-details.php?id=<?php echo $ar['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payments Tab -->
            <div class="tab-pane fade" id="payments" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Invoice #</th>
                                        <th>Method</th>
                                        <th>Reference</th>
                                        <th>Amount</th>
                                        <th>Collected By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($payment['invoice_number'] ?? '-'); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                        <td><?php echo htmlspecialchars($payment['reference_number'] ?? '-'); ?></td>
                                        <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($payment['collected_by']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Points Tab -->
            <?php if ($customer['customer_type'] == 'member'): ?>
            <div class="tab-pane fade" id="points" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Points</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($points_transactions as $pt): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y h:i A', strtotime($pt['created_at'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $pt['transaction_type'] == 'earned' ? 'success' : 
                                                    ($pt['transaction_type'] == 'redeemed' ? 'danger' : 'secondary'); 
                                            ?>"><?php echo ucfirst($pt['transaction_type']); ?></span>
                                        </td>
                                        <td>
                                            <span class="<?php echo $pt['points'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $pt['points'] > 0 ? '+' : ''; ?><?php echo $pt['points']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($pt['description'] ?? '-'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Transactions Tab -->
            <div class="tab-pane fade" id="transactions" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Transaction #</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $trans): ?>
                                    <tr>
                                        <td><a href="<?php echo APP_URL; ?>/admin/transactions.php?search=<?php echo urlencode($trans['transaction_number']); ?>"><?php echo htmlspecialchars($trans['transaction_number']); ?></a></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($trans['created_at'])); ?></td>
                                        <td><?php echo $trans['item_count']; ?> items</td>
                                        <td>₱<?php echo number_format($trans['final_amount'], 2); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $trans['payment_method'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $trans['payment_status'] == 'completed' ? 'success' : 
                                                    ($trans['payment_status'] == 'voided' ? 'danger' : 'warning'); 
                                            ?>"><?php echo ucfirst($trans['payment_status']); ?></span>
                                        </td>
                                        <td>
                                            <a href="<?php echo APP_URL; ?>/admin/transactions.php?search=<?php echo urlencode($trans['transaction_number']); ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Customer Modal (reuse from customers.php) -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="customerForm">
                <input type="hidden" id="customer_id" name="id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer Code *</label>
                            <input type="text" class="form-control" id="customer_code" name="customer_code" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer Name *</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Customer Type *</label>
                            <select class="form-select" id="customer_type" name="customer_type" required>
                                <option value="regular">Regular</option>
                                <option value="member">Member</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Credit Limit</label>
                            <input type="number" class="form-control" id="credit_limit" name="credit_limit" value="0" step="0.01" min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Standing</label>
                            <select class="form-select" id="standing" name="standing">
                                <option value="good">Good</option>
                                <option value="warning">Warning</option>
                                <option value="bad">Bad</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCustomer(customer) {
    document.getElementById('customer_id').value = customer.id;
    document.getElementById('customer_code').value = customer.customer_code || '';
    document.getElementById('customer_name').value = customer.customer_name || '';
    document.getElementById('email').value = customer.email || '';
    document.getElementById('phone').value = customer.phone || '';
    document.getElementById('address').value = customer.address || '';
    document.getElementById('customer_type').value = customer.customer_type || 'regular';
    document.getElementById('credit_limit').value = customer.credit_limit || 0;
    document.getElementById('standing').value = customer.standing || 'good';
    document.getElementById('notes').value = customer.notes || '';
    document.getElementById('is_active').checked = customer.is_active == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('customerModal'));
    modal.show();
}

function collectPayment(arId) {
    window.location.href = '<?php echo APP_URL; ?>/admin/accounts-receivable.php?ar_id=' + arId + '#collection';
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
jQuery(document).ready(function($) {
    $('#customerForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize() + '&action=save';
        
        $.ajax({
            url: '<?php echo APP_URL; ?>/api/customers.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    bootstrap.Modal.getInstance(document.getElementById('customerModal')).hide();
                    showSuccess('Customer updated successfully').then(() => location.reload());
                } else {
                    showError(response.message || 'Error updating customer');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                showError('Error updating customer: ' + (xhr.responseJSON?.message || error));
            }
        });
    });
});
</script>
