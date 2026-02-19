<?php
$pageTitle = 'Purchase Order Details';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('manage_purchase_orders');
$db = new Database();

$po_id = $_GET['id'] ?? 0;
$action = $_GET['action'] ?? '';

$po = $db->fetch(
    "SELECT po.*, s.supplier_name, s.payment_terms, u.full_name as created_by_name
     FROM purchase_orders po
     JOIN suppliers s ON po.supplier_id = s.id
     JOIN users u ON po.created_by = u.id
     WHERE po.id = ?",
    [$po_id]
);

if (!$po) {
    header('Location: ' . APP_URL . '/admin/purchase-orders.php');
    exit;
}

$items = $db->fetchAll(
    "SELECT poi.*, p.product_name, p.barcode, p.stock_quantity
     FROM purchase_order_items poi
     JOIN products p ON poi.product_id = p.id
     WHERE poi.purchase_order_id = ?",
    [$po_id]
);

$ap = $db->fetch(
    "SELECT * FROM accounts_payable WHERE purchase_order_id = ?",
    [$po_id]
);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-file-earmark-text"></i> Purchase Order: <?php echo htmlspecialchars($po['po_number']); ?></h2>
    <a href="<?php echo APP_URL; ?>/admin/purchase-orders.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header">
                <h5>Order Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Supplier:</strong> <?php echo htmlspecialchars($po['supplier_name']); ?></p>
                        <p><strong>Order Date:</strong> <?php echo date('M d, Y', strtotime($po['order_date'])); ?></p>
                        <p><strong>Expected Delivery:</strong> <?php echo $po['expected_delivery_date'] ? date('M d, Y', strtotime($po['expected_delivery_date'])) : '-'; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?php 
                                echo $po['status'] == 'received' ? 'success' : 
                                    ($po['status'] == 'partial' ? 'warning' : 
                                    ($po['status'] == 'cancelled' ? 'danger' : 'info')); 
                            ?>"><?php echo ucfirst($po['status']); ?></span>
                        </p>
                        <p><strong>Payment Status:</strong> 
                            <span class="badge bg-<?php 
                                echo $po['payment_status'] == 'paid' ? 'success' : 
                                    ($po['payment_status'] == 'partial' ? 'warning' : 'danger'); 
                            ?>"><?php echo ucfirst($po['payment_status']); ?></span>
                        </p>
                        <p><strong>Created By:</strong> <?php echo htmlspecialchars($po['created_by_name']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5>Items</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Received</th>
                                <th>Unit Cost</th>
                                <th>Discount</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($item['barcode']); ?></small><br>
                                    <small class="text-info">Current Stock: <?php echo $item['stock_quantity']; ?></small>
                                </td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>
                                    <?php if ($po['status'] != 'received' && $po['status'] != 'cancelled'): ?>
                                    <input type="number" class="form-control form-control-sm" 
                                           id="received_<?php echo $item['id']; ?>" 
                                           value="<?php echo $item['received_quantity']; ?>" 
                                           min="0" max="<?php echo $item['quantity']; ?>">
                                    <?php else: ?>
                                    <?php echo $item['received_quantity']; ?>
                                    <?php endif; ?>
                                </td>
                                <td>₱<?php echo number_format($item['unit_cost'], 2); ?></td>
                                <td>₱<?php echo number_format($item['discount'], 2); ?></td>
                                <td>₱<?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-end"><strong>Subtotal:</strong></td>
                                <td><strong>₱<?php echo number_format($po['subtotal'], 2); ?></strong></td>
                            </tr>
                            <tr>
                                <td colspan="5" class="text-end"><strong>Tax:</strong></td>
                                <td>₱<?php echo number_format($po['tax_amount'], 2); ?></td>
                            </tr>
                            <tr>
                                <td colspan="5" class="text-end"><strong>Discount:</strong></td>
                                <td>₱<?php echo number_format($po['discount_amount'], 2); ?></td>
                            </tr>
                            <tr>
                                <td colspan="5" class="text-end"><strong>Total:</strong></td>
                                <td><strong>₱<?php echo number_format($po['total_amount'], 2); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($po['notes']): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h5>Notes</h5>
            </div>
            <div class="card-body">
                <p><?php echo nl2br(htmlspecialchars($po['notes'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-md-4">
        <?php if ($po['status'] != 'received' && $po['status'] != 'cancelled'): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h5>Receive Order</h5>
            </div>
            <div class="card-body">
                <button class="btn btn-success w-100" onclick="receiveOrder()">
                    <i class="bi bi-check-circle"></i> Receive Order
                </button>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($ap): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h5>Accounts Payable</h5>
            </div>
            <div class="card-body">
                <p><strong>Invoice:</strong> <?php echo htmlspecialchars($ap['invoice_number'] ?? '-'); ?></p>
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
                <a href="<?php echo APP_URL; ?>/admin/accounts-payable.php?po_id=<?php echo $po_id; ?>" class="btn btn-primary w-100">
                    View AP Details
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function receiveOrder() {
    const receivedQuantities = {};
    <?php foreach ($items as $item): ?>
    const received_<?php echo $item['id']; ?> = document.getElementById('received_<?php echo $item['id']; ?>');
    if (received_<?php echo $item['id']; ?>) {
        receivedQuantities[<?php echo $item['id']; ?>] = parseInt(received_<?php echo $item['id']; ?>.value) || 0;
    }
    <?php endforeach; ?>
    
    showConfirm('Receive this purchase order? Stock will be updated automatically.').then((result) => {
        if (result.isConfirmed) {
            fetch('<?php echo APP_URL; ?>/api/purchase-orders.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'receive',
                    purchase_order_id: <?php echo $po_id; ?>,
                    received_quantities: receivedQuantities
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Order received successfully').then(() => location.reload());
                } else {
                    showError(data.message || 'Error receiving order');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                showError('Error receiving order');
            });
        }
    });
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
