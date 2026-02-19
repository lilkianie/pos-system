<?php
$pageTitle = 'Inventory Report';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('view_reports');
$db = new Database();

$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$product_id = $_GET['product_id'] ?? '';

$where = "WHERE DATE(it.created_at) BETWEEN ? AND ?";
$params = [$date_from, $date_to];

if ($product_id) {
    $where .= " AND it.product_id = ?";
    $params[] = $product_id;
}

// Summary by transaction type
$summaryByType = $db->fetchAll(
    "SELECT 
        it.transaction_type,
        COUNT(*) as count,
        SUM(CASE WHEN it.quantity > 0 THEN it.quantity ELSE 0 END) as total_in,
        SUM(CASE WHEN it.quantity < 0 THEN ABS(it.quantity) ELSE 0 END) as total_out
     FROM inventory_transactions it
     $where
     GROUP BY it.transaction_type
     ORDER BY count DESC",
    $params
);

// Top products by transactions
$topProducts = $db->fetchAll(
    "SELECT 
        p.id,
        p.product_name,
        p.barcode,
        COUNT(it.id) as transaction_count,
        SUM(CASE WHEN it.quantity > 0 THEN it.quantity ELSE 0 END) as total_in,
        SUM(CASE WHEN it.quantity < 0 THEN ABS(it.quantity) ELSE 0 END) as total_out,
        MAX(it.new_stock) as current_stock
     FROM inventory_transactions it
     JOIN products p ON it.product_id = p.id
     $where
     GROUP BY p.id, p.product_name, p.barcode
     ORDER BY transaction_count DESC
     LIMIT 20",
    $params
);

// Stock movements timeline
$stockMovements = $db->fetchAll(
    "SELECT 
        DATE(it.created_at) as date,
        COUNT(*) as transactions,
        SUM(CASE WHEN it.quantity > 0 THEN it.quantity ELSE 0 END) as total_in,
        SUM(CASE WHEN it.quantity < 0 THEN ABS(it.quantity) ELSE 0 END) as total_out
     FROM inventory_transactions it
     $where
     GROUP BY DATE(it.created_at)
     ORDER BY date DESC",
    $params
);

$products = $db->fetchAll("SELECT id, product_name FROM products WHERE is_active = 1 ORDER BY product_name");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-clipboard-data"></i> Inventory Report</h2>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Product</label>
                <select class="form-select" name="product_id">
                    <option value="">All Products</option>
                    <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['id']; ?>" <?php echo $product_id == $product['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($product['product_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date From</label>
                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date To</label>
                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label><br>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="inventory-report.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Summary by Transaction Type</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Count</th>
                                <th>Total In</th>
                                <th>Total Out</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summaryByType as $summary): ?>
                            <tr>
                                <td><?php echo ucfirst(str_replace('_', ' ', $summary['transaction_type'])); ?></td>
                                <td><?php echo $summary['count']; ?></td>
                                <td class="text-success">+<?php echo $summary['total_in'] ?? 0; ?></td>
                                <td class="text-danger">-<?php echo $summary['total_out'] ?? 0; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Stock Movements Timeline</h5>
            </div>
            <div class="card-body">
                <canvas id="movementsChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>Top Products by Transactions</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Barcode</th>
                        <th>Transactions</th>
                        <th>Total In</th>
                        <th>Total Out</th>
                        <th>Current Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topProducts as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($product['barcode']); ?></td>
                        <td><?php echo $product['transaction_count']; ?></td>
                        <td class="text-success">+<?php echo $product['total_in'] ?? 0; ?></td>
                        <td class="text-danger">-<?php echo $product['total_out'] ?? 0; ?></td>
                        <td>
                            <span class="badge <?php echo $product['current_stock'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $product['current_stock'] ?? 0; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const movementsData = <?php echo json_encode($stockMovements); ?>;
const ctx = document.getElementById('movementsChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: movementsData.map(d => d.date),
        datasets: [
            {
                label: 'Stock In',
                data: movementsData.map(d => parseInt(d.total_in) || 0),
                backgroundColor: 'rgba(40, 167, 69, 0.5)',
                borderColor: 'rgb(40, 167, 69)',
                borderWidth: 1
            },
            {
                label: 'Stock Out',
                data: movementsData.map(d => parseInt(d.total_out) || 0),
                backgroundColor: 'rgba(220, 53, 69, 0.5)',
                borderColor: 'rgb(220, 53, 69)',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
