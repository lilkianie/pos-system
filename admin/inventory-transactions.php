<?php
$pageTitle = 'Inventory Transactions';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('manage_inventory');
$db = new Database();

$page = $_GET['page'] ?? 1;
$search = $_GET['search'] ?? '';
$product_id = $_GET['product_id'] ?? '';
$transaction_type = $_GET['transaction_type'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$offset = ($page - 1) * ITEMS_PER_PAGE;

$where = "WHERE DATE(it.created_at) BETWEEN ? AND ?";
$params = [$date_from, $date_to];

if ($search) {
    $where .= " AND (p.product_name LIKE ? OR p.barcode LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($product_id) {
    $where .= " AND it.product_id = ?";
    $params[] = $product_id;
}

if ($transaction_type) {
    $where .= " AND it.transaction_type = ?";
    $params[] = $transaction_type;
}

$transactions = $db->fetchAll(
    "SELECT it.*, p.product_name, p.barcode, u.full_name as user_name
     FROM inventory_transactions it
     JOIN products p ON it.product_id = p.id
     JOIN users u ON it.user_id = u.id
     $where
     ORDER BY it.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [ITEMS_PER_PAGE, $offset])
);

$total = $db->fetch("SELECT COUNT(*) as count FROM inventory_transactions it JOIN products p ON it.product_id = p.id $where", $params)['count'];
$total_pages = ceil($total / ITEMS_PER_PAGE);

$products = $db->fetchAll("SELECT id, product_name, barcode FROM products WHERE is_active = 1 ORDER BY product_name");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-box-arrow-up-down"></i> Inventory Transactions</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adjustmentModal" onclick="openAdjustmentModal()">
        <i class="bi bi-plus-circle"></i> Stock Adjustment
    </button>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" class="form-control" name="search" placeholder="Search product..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <select class="form-select" name="product_id">
                    <option value="">All Products</option>
                    <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['id']; ?>" <?php echo $product_id == $product['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($product['product_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="transaction_type">
                    <option value="">All Types</option>
                    <option value="stock_in" <?php echo $transaction_type == 'stock_in' ? 'selected' : ''; ?>>Stock In</option>
                    <option value="stock_out" <?php echo $transaction_type == 'stock_out' ? 'selected' : ''; ?>>Stock Out</option>
                    <option value="adjustment" <?php echo $transaction_type == 'adjustment' ? 'selected' : ''; ?>>Adjustment</option>
                    <option value="sale" <?php echo $transaction_type == 'sale' ? 'selected' : ''; ?>>Sale</option>
                    <option value="return" <?php echo $transaction_type == 'return' ? 'selected' : ''; ?>>Return</option>
                    <option value="damaged" <?php echo $transaction_type == 'damaged' ? 'selected' : ''; ?>>Damaged</option>
                    <option value="expired" <?php echo $transaction_type == 'expired' ? 'selected' : ''; ?>>Expired</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
            </div>
            <div class="col-md-1">
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
                        <th>Date & Time</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Previous Stock</th>
                        <th>New Stock</th>
                        <th>Reference</th>
                        <th>User</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $trans): ?>
                    <tr>
                        <td><?php echo date('M d, Y h:i A', strtotime($trans['created_at'])); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($trans['product_name']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($trans['barcode']); ?></small>
                        </td>
                        <td>
                            <?php
                            $typeColors = [
                                'stock_in' => 'bg-success',
                                'stock_out' => 'bg-danger',
                                'adjustment' => 'bg-warning',
                                'sale' => 'bg-primary',
                                'return' => 'bg-info',
                                'damaged' => 'bg-dark',
                                'expired' => 'bg-secondary'
                            ];
                            $color = $typeColors[$trans['transaction_type']] ?? 'bg-secondary';
                            ?>
                            <span class="badge <?php echo $color; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $trans['transaction_type'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="<?php echo $trans['quantity'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $trans['quantity'] > 0 ? '+' : ''; ?><?php echo $trans['quantity']; ?>
                            </span>
                        </td>
                        <td><?php echo $trans['previous_stock']; ?></td>
                        <td><strong><?php echo $trans['new_stock']; ?></strong></td>
                        <td>
                            <?php if ($trans['reference_type'] && $trans['reference_id']): ?>
                                <?php if ($trans['reference_type'] == 'transaction'): ?>
                                    <a href="<?php echo APP_URL; ?>/admin/transactions.php?search=<?php echo urlencode('TXN-' . $trans['reference_id']); ?>">
                                        TXN-<?php echo $trans['reference_id']; ?>
                                    </a>
                                <?php else: ?>
                                    <?php echo ucfirst($trans['reference_type']); ?> #<?php echo $trans['reference_id']; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($trans['user_name']); ?></td>
                        <td>
                            <?php if ($trans['notes']): ?>
                                <small class="text-muted" title="<?php echo htmlspecialchars($trans['notes']); ?>">
                                    <?php echo htmlspecialchars(substr($trans['notes'], 0, 30)); ?><?php echo strlen($trans['notes']) > 30 ? '...' : ''; ?>
                                </small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
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
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&product_id=<?php echo $product_id; ?>&transaction_type=<?php echo $transaction_type; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div class="modal fade" id="adjustmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Stock Adjustment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="adjustmentForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <select class="form-select" id="adjust_product_id" name="product_id" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" data-stock="<?php echo $product['stock_quantity'] ?? 0; ?>">
                                <?php echo htmlspecialchars($product['product_name']); ?> (Stock: <?php echo $product['stock_quantity'] ?? 0; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Current Stock: <span id="currentStock">0</span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adjustment Type</label>
                        <select class="form-select" id="adjustment_type" name="adjustment_type" required>
                            <option value="stock_in">Stock In (Add)</option>
                            <option value="stock_out">Stock Out (Remove)</option>
                            <option value="adjustment">Adjustment (Set)</option>
                            <option value="damaged">Damaged</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="adjust_quantity" name="quantity" required min="1">
                        <small class="text-muted" id="adjustmentHelp"></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="adjust_notes" name="notes" rows="3"></textarea>
                    </div>
                    <div class="alert alert-info" id="adjustmentPreview" style="display: none;">
                        <strong>New Stock:</strong> <span id="newStockPreview">0</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAdjustmentModal() {
    document.getElementById('adjustmentForm').reset();
    document.getElementById('currentStock').textContent = '0';
    document.getElementById('adjustmentPreview').style.display = 'none';
}

document.getElementById('adjust_product_id')?.addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    const stock = option.getAttribute('data-stock') || 0;
    document.getElementById('currentStock').textContent = stock;
    updateAdjustmentPreview();
});

document.getElementById('adjustment_type')?.addEventListener('change', updateAdjustmentPreview);
document.getElementById('adjust_quantity')?.addEventListener('input', updateAdjustmentPreview);

function updateAdjustmentPreview() {
    const productSelect = document.getElementById('adjust_product_id');
    const type = document.getElementById('adjustment_type').value;
    const quantity = parseFloat(document.getElementById('adjust_quantity').value) || 0;
    const currentStock = parseFloat(productSelect.options[productSelect.selectedIndex]?.getAttribute('data-stock') || 0);
    
    if (productSelect.value && quantity > 0) {
        let newStock = currentStock;
        if (type === 'stock_in') {
            newStock = currentStock + quantity;
        } else if (type === 'adjustment') {
            newStock = quantity;
        } else {
            newStock = currentStock - quantity;
            if (newStock < 0) newStock = 0;
        }
        
        document.getElementById('newStockPreview').textContent = newStock;
        document.getElementById('adjustmentPreview').style.display = 'block';
        
        // Update help text
        const helpText = {
            'stock_in': `Will add ${quantity} to current stock`,
            'stock_out': `Will remove ${quantity} from current stock`,
            'adjustment': `Will set stock to ${quantity}`,
            'damaged': `Will remove ${quantity} as damaged`,
            'expired': `Will remove ${quantity} as expired`
        };
        document.getElementById('adjustmentHelp').textContent = helpText[type] || '';
    } else {
        document.getElementById('adjustmentPreview').style.display = 'none';
    }
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
jQuery(document).ready(function($) {
    $('#adjustmentForm').on('submit', function(e) {
        e.preventDefault();
        
        const productId = document.getElementById('adjust_product_id').value;
        const type = document.getElementById('adjustment_type').value;
        const quantity = parseFloat(document.getElementById('adjust_quantity').value);
        const notes = document.getElementById('adjust_notes').value;
        
        if (!productId || !quantity || quantity <= 0) {
            showWarning('Please fill in all required fields');
            return;
        }
        
        const formData = {
            product_id: productId,
            transaction_type: type,
            quantity: quantity,
            notes: notes,
            action: 'adjust_stock'
        };
        
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Processing...');
        
        $.ajax({
            url: '<?php echo APP_URL; ?>/api/inventory.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    bootstrap.Modal.getInstance(document.getElementById('adjustmentModal')).hide();
                    showSuccess('Stock adjusted successfully').then(() => {
                        location.reload();
                    });
                } else {
                    showError(response.message || 'Error adjusting stock');
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                showError('Error adjusting stock: ' + (xhr.responseJSON?.message || error));
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>
