<?php
$pageTitle = 'Purchase Orders';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('manage_purchase_orders');
$db = new Database();

$page = $_GET['page'] ?? 1;
$search = $_GET['search'] ?? '';
$supplier_id = $_GET['supplier_id'] ?? '';
$status = $_GET['status'] ?? '';
$offset = ($page - 1) * ITEMS_PER_PAGE;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (po.po_number LIKE ? OR s.supplier_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($supplier_id) {
    $where .= " AND po.supplier_id = ?";
    $params[] = $supplier_id;
}

if ($status) {
    $where .= " AND po.status = ?";
    $params[] = $status;
}

$orders = $db->fetchAll(
    "SELECT po.*, s.supplier_name, u.full_name as created_by_name
     FROM purchase_orders po
     JOIN suppliers s ON po.supplier_id = s.id
     JOIN users u ON po.created_by = u.id
     $where
     ORDER BY po.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [ITEMS_PER_PAGE, $offset])
);

$total = $db->fetch("SELECT COUNT(*) as count FROM purchase_orders po JOIN suppliers s ON po.supplier_id = s.id $where", $params)['count'];
$total_pages = ceil($total / ITEMS_PER_PAGE);

$suppliers = $db->fetchAll("SELECT id, supplier_name FROM suppliers WHERE is_active = 1 ORDER BY supplier_name");
$products = $db->fetchAll("SELECT id, product_name, barcode, stock_quantity FROM products WHERE is_active = 1 ORDER BY product_name");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-cart-check"></i> Purchase Orders</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#poModal" onclick="openPOModal()">
        <i class="bi bi-plus-circle"></i> New Purchase Order
    </button>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" class="form-control" name="search" placeholder="Search PO number..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <select class="form-select" name="supplier_id">
                    <option value="">All Suppliers</option>
                    <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?php echo $supplier['id']; ?>" <?php echo $supplier_id == $supplier['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="ordered" <?php echo $status == 'ordered' ? 'selected' : ''; ?>>Ordered</option>
                    <option value="partial" <?php echo $status == 'partial' ? 'selected' : ''; ?>>Partial</option>
                    <option value="received" <?php echo $status == 'received' ? 'selected' : ''; ?>>Received</option>
                    <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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
                        <th>PO Number</th>
                        <th>Supplier</th>
                        <th>Order Date</th>
                        <th>Expected Delivery</th>
                        <th>Status</th>
                        <th>Total Amount</th>
                        <th>Balance</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($order['po_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($order['supplier_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                        <td><?php echo $order['expected_delivery_date'] ? date('M d, Y', strtotime($order['expected_delivery_date'])) : '-'; ?></td>
                        <td>
                            <?php
                            $statusColors = [
                                'pending' => 'bg-secondary',
                                'ordered' => 'bg-info',
                                'partial' => 'bg-warning',
                                'received' => 'bg-success',
                                'cancelled' => 'bg-danger'
                            ];
                            $color = $statusColors[$order['status']] ?? 'bg-secondary';
                            ?>
                            <span class="badge <?php echo $color; ?>"><?php echo ucfirst($order['status']); ?></span>
                        </td>
                        <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td>
                            <span class="<?php echo $order['balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                ₱<?php echo number_format($order['balance'], 2); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($order['created_by_name']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="viewPO(<?php echo $order['id']; ?>)">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if ($order['status'] == 'received' || $order['status'] == 'partial'): ?>
                            <button class="btn btn-sm btn-success" onclick="receivePO(<?php echo $order['id']; ?>)">
                                <i class="bi bi-check-circle"></i> Receive
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
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&supplier_id=<?php echo $supplier_id; ?>&status=<?php echo $status; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- PO Modal -->
<div class="modal fade" id="poModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Purchase Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="poForm">
                <input type="hidden" id="po_id" name="id">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">PO Number *</label>
                            <input type="text" class="form-control" id="po_number" name="po_number" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Supplier *</label>
                            <select class="form-select" id="supplier_id" name="supplier_id" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['supplier_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Order Date *</label>
                            <input type="date" class="form-control" id="order_date" name="order_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Expected Delivery</label>
                            <input type="date" class="form-control" id="expected_delivery_date" name="expected_delivery_date">
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Items</h6>
                            <button type="button" class="btn btn-sm btn-primary" onclick="addPOItem()">
                                <i class="bi bi-plus"></i> Add Item
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm" id="poItemsTable">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Quantity</th>
                                            <th>Unit Cost</th>
                                            <th>Discount</th>
                                            <th>Subtotal</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="poItemsBody">
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                            <td><strong id="poSubtotal">₱0.00</strong></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="text-end"><strong>Tax:</strong></td>
                                            <td><input type="number" class="form-control form-control-sm" id="tax_amount" name="tax_amount" value="0" step="0.01" min="0" onchange="calculatePOTotal()"></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="text-end"><strong>Discount:</strong></td>
                                            <td><input type="number" class="form-control form-control-sm" id="discount_amount" name="discount_amount" value="0" step="0.01" min="0" onchange="calculatePOTotal()"></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="text-end"><strong>Total:</strong></td>
                                            <td><strong id="poTotal">₱0.00</strong></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="po_notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Purchase Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const products = <?php echo json_encode($products); ?>;
let poItems = [];
let itemIndex = 0;

function openPOModal() {
    document.getElementById('poForm').reset();
    document.getElementById('po_id').value = '';
    poItems = [];
    itemIndex = 0;
    renderPOItems();
    document.getElementById('order_date').value = '<?php echo date('Y-m-d'); ?>';
}

function addPOItem() {
    poItems.push({
        index: itemIndex++,
        product_id: '',
        quantity: 1,
        unit_cost: 0,
        discount: 0
    });
    renderPOItems();
}

function removePOItem(index) {
    poItems = poItems.filter(item => item.index !== index);
    renderPOItems();
}

function renderPOItems() {
    const tbody = document.getElementById('poItemsBody');
    tbody.innerHTML = '';
    
    poItems.forEach(item => {
        const product = products.find(p => p.id == item.product_id);
        const subtotal = (item.quantity * item.unit_cost) - (item.discount || 0);
        
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <select class="form-select form-select-sm" onchange="updatePOItem(${item.index}, 'product_id', this.value)">
                    <option value="">Select Product</option>
                    ${products.map(p => `<option value="${p.id}" ${p.id == item.product_id ? 'selected' : ''}>${p.product_name} (Stock: ${p.stock_quantity})</option>`).join('')}
                </select>
            </td>
            <td><input type="number" class="form-control form-control-sm" value="${item.quantity}" min="1" onchange="updatePOItem(${item.index}, 'quantity', this.value)"></td>
            <td><input type="number" class="form-control form-control-sm" value="${item.unit_cost}" step="0.01" min="0" onchange="updatePOItem(${item.index}, 'unit_cost', this.value)"></td>
            <td><input type="number" class="form-control form-control-sm" value="${item.discount || 0}" step="0.01" min="0" onchange="updatePOItem(${item.index}, 'discount', this.value)"></td>
            <td>₱${subtotal.toFixed(2)}</td>
            <td><button type="button" class="btn btn-sm btn-danger" onclick="removePOItem(${item.index})"><i class="bi bi-trash"></i></button></td>
        `;
        tbody.appendChild(row);
    });
    
    calculatePOTotal();
}

function updatePOItem(index, field, value) {
    const item = poItems.find(i => i.index === index);
    if (item) {
        item[field] = field === 'quantity' || field === 'product_id' ? parseInt(value) : parseFloat(value);
        renderPOItems();
    }
}

function calculatePOTotal() {
    const subtotal = poItems.reduce((sum, item) => {
        return sum + ((item.quantity * item.unit_cost) - (item.discount || 0));
    }, 0);
    
    const tax = parseFloat(document.getElementById('tax_amount').value) || 0;
    const discount = parseFloat(document.getElementById('discount_amount').value) || 0;
    const total = subtotal + tax - discount;
    
    document.getElementById('poSubtotal').textContent = '₱' + subtotal.toFixed(2);
    document.getElementById('poTotal').textContent = '₱' + total.toFixed(2);
}

function viewPO(id) {
    window.location.href = '<?php echo APP_URL; ?>/admin/purchase-order-details.php?id=' + id;
}

function receivePO(id) {
    window.location.href = '<?php echo APP_URL; ?>/admin/purchase-order-details.php?id=' + id + '&action=receive';
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
jQuery(document).ready(function($) {
    $('#poForm').on('submit', function(e) {
        e.preventDefault();
        
        if (poItems.length === 0) {
            showWarning('Please add at least one item');
            return;
        }
        
        const formData = {
            action: 'save',
            id: document.getElementById('po_id').value,
            po_number: document.getElementById('po_number').value,
            supplier_id: document.getElementById('supplier_id').value,
            order_date: document.getElementById('order_date').value,
            expected_delivery_date: document.getElementById('expected_delivery_date').value,
            tax_amount: document.getElementById('tax_amount').value,
            discount_amount: document.getElementById('discount_amount').value,
            notes: document.getElementById('po_notes').value,
            items: poItems
        };
        
        $.ajax({
            url: '<?php echo APP_URL; ?>/api/purchase-orders.php',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    bootstrap.Modal.getInstance(document.getElementById('poModal')).hide();
                    showSuccess('Purchase order saved successfully').then(() => location.reload());
                } else {
                    showError(response.message || 'Error saving purchase order');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                showError('Error saving purchase order: ' + (xhr.responseJSON?.message || error));
            }
        });
    });
});
</script>
