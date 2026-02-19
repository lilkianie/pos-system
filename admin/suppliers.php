<?php
$pageTitle = 'Suppliers';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('manage_suppliers');
$db = new Database();

$page = $_GET['page'] ?? 1;
$search = $_GET['search'] ?? '';
$offset = ($page - 1) * ITEMS_PER_PAGE;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (supplier_name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$suppliers = $db->fetchAll(
    "SELECT s.*, 
     COUNT(DISTINCT po.id) as total_orders,
     SUM(CASE WHEN ap.status IN ('open', 'partial', 'overdue') THEN ap.balance ELSE 0 END) as outstanding_balance
     FROM suppliers s
     LEFT JOIN purchase_orders po ON s.id = po.supplier_id
     LEFT JOIN accounts_payable ap ON s.id = ap.supplier_id
     $where
     GROUP BY s.id
     ORDER BY s.supplier_name
     LIMIT ? OFFSET ?",
    array_merge($params, [ITEMS_PER_PAGE, $offset])
);

$total = $db->fetch("SELECT COUNT(*) as count FROM suppliers $where", $params)['count'];
$total_pages = ceil($total / ITEMS_PER_PAGE);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-truck"></i> Suppliers</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="openSupplierModal()">
        <i class="bi bi-plus-circle"></i> Add Supplier
    </button>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-10">
                <input type="text" class="form-control" name="search" placeholder="Search suppliers..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Search</button>
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
                        <th>Supplier Name</th>
                        <th>Contact Person</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Payment Terms</th>
                        <th>Credit Limit</th>
                        <th>Outstanding</th>
                        <th>Orders</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $supplier): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($supplier['contact_person'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($supplier['email'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($supplier['phone'] ?? '-'); ?></td>
                        <td><?php echo $supplier['payment_terms']; ?> days</td>
                        <td>₱<?php echo number_format($supplier['credit_limit'], 2); ?></td>
                        <td>
                            <span class="<?php echo $supplier['outstanding_balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                ₱<?php echo number_format($supplier['outstanding_balance'] ?? 0, 2); ?>
                            </span>
                        </td>
                        <td><?php echo $supplier['total_orders'] ?? 0; ?></td>
                        <td>
                            <span class="badge <?php echo $supplier['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo $supplier['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="editSupplier(<?php echo htmlspecialchars(json_encode($supplier)); ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteSupplier(<?php echo $supplier['id']; ?>)">
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
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Supplier Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="supplierForm">
                <input type="hidden" id="supplier_id" name="id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier Name *</label>
                            <input type="text" class="form-control" id="supplier_name" name="supplier_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" class="form-control" id="contact_person" name="contact_person">
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
                            <label class="form-label">Payment Terms (days) *</label>
                            <input type="number" class="form-control" id="payment_terms" name="payment_terms" value="30" required min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Credit Limit</label>
                            <input type="number" class="form-control" id="credit_limit" name="credit_limit" value="0" step="0.01" min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tax ID</label>
                            <input type="text" class="form-control" id="tax_id" name="tax_id">
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
                    <button type="submit" class="btn btn-primary">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openSupplierModal() {
    document.getElementById('supplierForm').reset();
    document.getElementById('supplier_id').value = '';
    document.getElementById('is_active').checked = true;
}

function editSupplier(supplier) {
    document.getElementById('supplier_id').value = supplier.id;
    document.getElementById('supplier_name').value = supplier.supplier_name || '';
    document.getElementById('contact_person').value = supplier.contact_person || '';
    document.getElementById('email').value = supplier.email || '';
    document.getElementById('phone').value = supplier.phone || '';
    document.getElementById('address').value = supplier.address || '';
    document.getElementById('city').value = supplier.city || '';
    document.getElementById('state').value = supplier.state || '';
    document.getElementById('zip_code').value = supplier.zip_code || '';
    document.getElementById('payment_terms').value = supplier.payment_terms || 30;
    document.getElementById('credit_limit').value = supplier.credit_limit || 0;
    document.getElementById('tax_id').value = supplier.tax_id || '';
    document.getElementById('notes').value = supplier.notes || '';
    document.getElementById('is_active').checked = supplier.is_active == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('supplierModal'));
    modal.show();
}

function deleteSupplier(id) {
    confirmDelete('Are you sure you want to delete this supplier?').then((result) => {
        if (result.isConfirmed) {
            fetch('<?php echo APP_URL; ?>/api/suppliers.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete&id=${id}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Supplier deleted successfully').then(() => location.reload());
                } else {
                    showError(data.message || 'Error deleting supplier');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                showError('Error deleting supplier');
            });
        }
    });
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
jQuery(document).ready(function($) {
    $('#supplierForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize() + '&action=save';
        
        $.ajax({
            url: '<?php echo APP_URL; ?>/api/suppliers.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    bootstrap.Modal.getInstance(document.getElementById('supplierModal')).hide();
                    showSuccess('Supplier saved successfully').then(() => location.reload());
                } else {
                    showError(response.message || 'Error saving supplier');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                showError('Error saving supplier: ' + (xhr.responseJSON?.message || error));
            }
        });
    });
});
</script>
