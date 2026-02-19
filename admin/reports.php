<?php
$pageTitle = 'Reports';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('view_reports');
$db = new Database();

$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$sales_report = $db->fetchAll(
    "SELECT DATE(created_at) as date, 
            COUNT(*) as transactions,
            SUM(final_amount) as total_sales,
            SUM(discount_amount) as total_discounts
     FROM transactions 
     WHERE DATE(created_at) BETWEEN ? AND ? 
     AND payment_status != 'voided'
     GROUP BY DATE(created_at)
     ORDER BY date DESC",
    [$date_from, $date_to]
);

$top_products = $db->fetchAll(
    "SELECT p.product_name, SUM(ti.quantity) as total_qty, SUM(ti.subtotal) as total_revenue
     FROM transaction_items ti
     JOIN products p ON ti.product_id = p.id
     JOIN transactions t ON ti.transaction_id = t.id
     WHERE DATE(t.created_at) BETWEEN ? AND ?
     GROUP BY p.id, p.product_name
     ORDER BY total_revenue DESC
     LIMIT 10",
    [$date_from, $date_to]
);

$payment_methods = $db->fetchAll(
    "SELECT payment_method, COUNT(*) as count, SUM(final_amount) as total
     FROM transactions
     WHERE DATE(created_at) BETWEEN ? AND ?
     AND payment_status != 'voided'
     GROUP BY payment_method",
    [$date_from, $date_to]
);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-graph-up"></i> Reports</h2>
    <form method="GET" class="d-flex gap-2">
        <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
        <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
        <button type="submit" class="btn btn-primary">Filter</button>
        <button type="button" class="btn btn-success" onclick="printReport()">
            <i class="bi bi-printer"></i> Print
        </button>
    </form>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6>Total Sales</h6>
                <h3>₱<?php echo number_format(array_sum(array_column($sales_report, 'total_sales')), 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6>Total Transactions</h6>
                <h3><?php echo array_sum(array_column($sales_report, 'transactions')); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6>Total Discounts</h6>
                <h3>₱<?php echo number_format(array_sum(array_column($sales_report, 'total_discounts')), 2); ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5>Sales Report</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transactions</th>
                                <th>Total Sales</th>
                                <th>Discounts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales_report as $report): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($report['date'])); ?></td>
                                <td><?php echo $report['transactions']; ?></td>
                                <td>₱<?php echo number_format($report['total_sales'], 2); ?></td>
                                <td>₱<?php echo number_format($report['total_discounts'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5>Top Products</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($top_products as $product): ?>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo htmlspecialchars($product['product_name']); ?></h6>
                            <small>₱<?php echo number_format($product['total_revenue'], 2); ?></small>
                        </div>
                        <small>Qty: <?php echo $product['total_qty']; ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Payment Methods</h5>
            </div>
            <div class="card-body">
                <canvas id="paymentChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const paymentData = <?php echo json_encode($payment_methods); ?>;
const ctx = document.getElementById('paymentChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: paymentData.map(d => d.payment_method.replace('_', ' ').toUpperCase()),
        datasets: [{
            data: paymentData.map(d => parseFloat(d.total)),
            backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56']
        }]
    }
});

function printReport() {
    window.print();
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
