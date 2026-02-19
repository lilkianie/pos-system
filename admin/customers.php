<?php
$pageTitle = 'Customers';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('manage_customers');
$db = new Database();

$page = $_GET['page'] ?? 1;
$search = $_GET['search'] ?? '';
$customer_type = $_GET['customer_type'] ?? '';
$standing = $_GET['standing'] ?? '';
$offset = ($page - 1) * ITEMS_PER_PAGE;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (customer_code LIKE ? OR customer_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($customer_type) {
    $where .= " AND customer_type = ?";
    $params[] = $customer_type;
}

if ($standing) {
    $where .= " AND standing = ?";
    $params[] = $standing;
}

$customers = $db->fetchAll(
    "SELECT c.*, 
     COUNT(DISTINCT ar.id) as total_invoices,
     SUM(CASE WHEN ar.status IN ('open', 'partial', 'overdue') THEN ar.balance ELSE 0 END) as outstanding_balance
     FROM customers c
     LEFT JOIN accounts_receivable ar ON c.id = ar.customer_id
     $where
     GROUP BY c.id
     ORDER BY c.customer_name
     LIMIT ? OFFSET ?",
    array_merge($params, [ITEMS_PER_PAGE, $offset])
);

$total = $db->fetch("SELECT COUNT(*) as count FROM customers $where", $params)['count'];
$total_pages = ceil($total / ITEMS_PER_PAGE);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-people-fill"></i> Customers</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="openCustomerModal()">
        <i class="bi bi-plus-circle"></i> Add Customer
    </button>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Search customers..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <select class="form-select" name="customer_type">
                    <option value="">All Types</option>
                    <option value="regular" <?php echo $customer_type == 'regular' ? 'selected' : ''; ?>>Regular</option>
                    <option value="member" <?php echo $customer_type == 'member' ? 'selected' : ''; ?>>Member</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="standing">
                    <option value="">All Standing</option>
                    <option value="good" <?php echo $standing == 'good' ? 'selected' : ''; ?>>Good</option>
                    <option value="warning" <?php echo $standing == 'warning' ? 'selected' : ''; ?>>Warning</option>
                    <option value="bad" <?php echo $standing == 'bad' ? 'selected' : ''; ?>>Bad</option>
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
                        <th>Code</th>
                        <th>Customer Name</th>
                        <th>Type</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Credit Limit</th>
                        <th>Outstanding</th>
                        <th>Points</th>
                        <th>Standing</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($customer['customer_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                        <td>
                            <span class="badge <?php echo $customer['customer_type'] == 'member' ? 'bg-info' : 'bg-secondary'; ?>">
                                <?php echo ucfirst($customer['customer_type']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($customer['email'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($customer['phone'] ?? '-'); ?></td>
                        <td>₱<?php echo number_format($customer['credit_limit'], 2); ?></td>
                        <td>
                            <span class="<?php echo $customer['outstanding_balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                ₱<?php echo number_format($customer['outstanding_balance'] ?? 0, 2); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($customer['customer_type'] == 'member'): ?>
                            <span class="badge bg-warning text-dark"><?php echo number_format($customer['points_balance'] ?? 0); ?> pts</span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $standingColors = [
                                'good' => 'bg-success',
                                'warning' => 'bg-warning',
                                'bad' => 'bg-danger'
                            ];
                            $color = $standingColors[$customer['standing']] ?? 'bg-secondary';
                            ?>
                            <span class="badge <?php echo $color; ?>"><?php echo ucfirst($customer['standing']); ?></span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="viewCustomer(<?php echo $customer['id']; ?>)">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-primary" onclick="editCustomer(<?php echo htmlspecialchars(json_encode($customer)); ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteCustomer(<?php echo $customer['id']; ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
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
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&customer_type=<?php echo $customer_type; ?>&standing=<?php echo $standing; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Customer</h5>
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
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" id="city" name="city">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">State/Province</label>
                            <input type="text" class="form-control" id="state" name="state">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Zip Code</label>
                            <input type="text" class="form-control" id="zip_code" name="zip_code">
                        </div>
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
                        <label class="form-label">Birth Date</label>
                        <input type="date" class="form-control" id="birth_date" name="birth_date">
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
function openCustomerModal() {
    document.getElementById('customerForm').reset();
    document.getElementById('customer_id').value = '';
    document.getElementById('is_active').checked = true;
    document.getElementById('standing').value = 'good';
    document.getElementById('customer_type').value = 'regular';
}

function editCustomer(customer) {
    document.getElementById('customer_id').value = customer.id;
    document.getElementById('customer_code').value = customer.customer_code || '';
    document.getElementById('customer_name').value = customer.customer_name || '';
    document.getElementById('email').value = customer.email || '';
    document.getElementById('phone').value = customer.phone || '';
    document.getElementById('address').value = customer.address || '';
    document.getElementById('city').value = customer.city || '';
    document.getElementById('state').value = customer.state || '';
    document.getElementById('zip_code').value = customer.zip_code || '';
    document.getElementById('customer_type').value = customer.customer_type || 'regular';
    document.getElementById('credit_limit').value = customer.credit_limit || 0;
    document.getElementById('standing').value = customer.standing || 'good';
    document.getElementById('birth_date').value = customer.birth_date || '';
    document.getElementById('notes').value = customer.notes || '';
    document.getElementById('is_active').checked = customer.is_active == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('customerModal'));
    modal.show();
}

function viewCustomer(id) {
    window.location.href = '<?php echo APP_URL; ?>/admin/customer-details.php?id=' + id;
}

function deleteCustomer(id) {
    confirmDelete('Are you sure you want to delete this customer?').then((result) => {
        if (result.isConfirmed) {
            fetch('<?php echo APP_URL; ?>/api/customers.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete&id=${id}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Customer deleted successfully').then(() => location.reload());
                } else {
                    showError(data.message || 'Error deleting customer');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                showError('Error deleting customer');
            });
        }
    });
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
                    showSuccess('Customer saved successfully').then(() => location.reload());
                } else {
                    showError(response.message || 'Error saving customer');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                showError('Error saving customer: ' + (xhr.responseJSON?.message || error));
            }
        });
    });
});
</script>
