<?php
$pageTitle = 'AR Reports';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('view_ar_reports');
$db = new Database();

$customer_id = $_GET['customer_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-90 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'aging';

$where = "WHERE ar.invoice_date BETWEEN ? AND ?";
$params = [$date_from, $date_to];

if ($customer_id) {
    $where .= " AND ar.customer_id = ?";
    $params[] = $customer_id;
}

// Aging Report
$aging = $db->fetchAll(
    "SELECT ar.*, c.customer_code, c.customer_name, c.standing, t.transaction_number,
     CASE 
         WHEN DATEDIFF(CURDATE(), ar.due_date) <= 0 THEN 'current'
         WHEN DATEDIFF(CURDATE(), ar.due_date) <= 30 THEN '1-30'
         WHEN DATEDIFF(CURDATE(), ar.due_date) <= 60 THEN '31-60'
         WHEN DATEDIFF(CURDATE(), ar.due_date) <= 90 THEN '61-90'
         ELSE '90+'
     END as aging_bucket,
     DATEDIFF(CURDATE(), ar.due_date) as days_overdue
     FROM accounts_receivable ar
     JOIN customers c ON ar.customer_id = c.id
     JOIN transactions t ON ar.transaction_id = t.id
     WHERE ar.status IN ('open', 'partial', 'overdue')
     " . ($customer_id ? "AND ar.customer_id = ?" : ""),
    $customer_id ? [$customer_id] : []
);

// Aging Summary
$agingSummary = [
    'current' => 0,
    '1-30' => 0,
    '31-60' => 0,
    '61-90' => 0,
    '90+' => 0
];

foreach ($aging as $item) {
    if (isset($agingSummary[$item['aging_bucket']])) {
        $agingSummary[$item['aging_bucket']] += $item['balance'];
    }
}

// Collection Report
$collections = $db->fetchAll(
    "SELECT DATE(ap.payment_date) as collection_date,
     COUNT(DISTINCT ap.accounts_receivable_id) as invoices_paid,
     COUNT(ap.id) as payment_count,
     SUM(ap.amount) as total_collected
     FROM ar_payments ap
     JOIN accounts_receivable ar ON ap.accounts_receivable_id = ar.id
     $where
     GROUP BY DATE(ap.payment_date)
     ORDER BY collection_date DESC",
    $params
);

// Customer Sales Report
$customerSales = $db->fetchAll(
    "SELECT c.id, c.customer_code, c.customer_name, c.customer_type,
     COUNT(DISTINCT ar.id) as invoice_count,
     SUM(ar.amount) as total_sales,
     SUM(ar.paid_amount) as total_paid,
     SUM(ar.balance) as total_outstanding,
     MAX(DATEDIFF(CURDATE(), ar.due_date)) as max_days_overdue
     FROM customers c
     LEFT JOIN accounts_receivable ar ON c.id = ar.customer_id
     " . ($customer_id ? "WHERE c.id = ?" : "") . "
     GROUP BY c.id, c.customer_code, c.customer_name, c.customer_type
     ORDER BY total_sales DESC
     LIMIT 50",
    $customer_id ? [$customer_id] : []
);

// Summary by Status
$statusSummary = $db->fetchAll(
    "SELECT ar.status, COUNT(*) as count, SUM(ar.balance) as total_balance
     FROM accounts_receivable ar
     $where
     GROUP BY ar.status",
    $params
);

$customers = $db->fetchAll("SELECT id, customer_code, customer_name FROM customers WHERE is_active = 1 ORDER BY customer_name");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-graph-up-arrow"></i> Accounts Receivable Reports</h2>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Report Type</label>
                <select class="form-select" name="report_type" onchange="this.form.submit()">
                    <option value="aging" <?php echo $report_type == 'aging' ? 'selected' : ''; ?>>Aging Report</option>
                    <option value="collection" <?php echo $report_type == 'collection' ? 'selected' : ''; ?>>Collection Report</option>
                    <option value="customer_sales" <?php echo $report_type == 'customer_sales' ? 'selected' : ''; ?>>Customer Sales Report</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Customer</label>
                <select class="form-select" name="customer_id">
                    <option value="">All Customers</option>
                    <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo $customer['id']; ?>" <?php echo $customer_id == $customer['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($customer['customer_code'] . ' - ' . $customer['customer_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Date From</label>
                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Date To</label>
                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label><br>
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>
    </div>
</div>

<?php if ($report_type == 'aging'): ?>
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Aging Summary</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Aging Bucket</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Current (Not Due)</td>
                            <td class="text-end">₱<?php echo number_format($agingSummary['current'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>1-30 Days</td>
                            <td class="text-end text-warning">₱<?php echo number_format($agingSummary['1-30'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>31-60 Days</td>
                            <td class="text-end text-warning">₱<?php echo number_format($agingSummary['31-60'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>61-90 Days</td>
                            <td class="text-end text-danger">₱<?php echo number_format($agingSummary['61-90'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>90+ Days</td>
                            <td class="text-end text-danger">₱<?php echo number_format($agingSummary['90+'], 2); ?></td>
                        </tr>
                        <tr class="table-primary">
                            <td><strong>Total Outstanding</strong></td>
                            <td class="text-end"><strong>₱<?php echo number_format(array_sum($agingSummary), 2); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Summary by Status</h5>
            </div>
            <div class="card-body">
                <canvas id="statusChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>Aging Report</h5>
    </div>
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
                        <th>Days Overdue</th>
                        <th>Aging Bucket</th>
                        <th>Amount</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aging as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['invoice_number'] ?? '-'); ?></td>
                        <td><a href="<?php echo APP_URL; ?>/admin/transactions.php?search=<?php echo urlencode($item['transaction_number']); ?>"><?php echo htmlspecialchars($item['transaction_number']); ?></a></td>
                        <td><?php echo htmlspecialchars($item['customer_code'] . ' - ' . $item['customer_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($item['invoice_date'])); ?></td>
                        <td><?php echo date('M d, Y', strtotime($item['due_date'])); ?></td>
                        <td>
                            <?php 
                            $days = isset($item['days_overdue']) ? intval($item['days_overdue']) : 0;
                            if ($days > 0): 
                            ?>
                            <span class="text-danger"><?php echo $days; ?> days</span>
                            <?php else: ?>
                            <span class="text-success">Not due</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $bucketColors = [
                                'current' => 'success',
                                '1-30' => 'warning',
                                '31-60' => 'warning',
                                '61-90' => 'danger',
                                '90+' => 'danger'
                            ];
                            $color = $bucketColors[$item['aging_bucket']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $color; ?>"><?php echo $item['aging_bucket']; ?></span>
                        </td>
                        <td>₱<?php echo number_format($item['amount'], 2); ?></td>
                        <td>₱<?php echo number_format($item['paid_amount'], 2); ?></td>
                        <td>
                            <span class="<?php echo $item['balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                ₱<?php echo number_format($item['balance'], 2); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $item['status'] == 'paid' ? 'success' : 
                                    ($item['status'] == 'overdue' ? 'danger' : 
                                    ($item['status'] == 'partial' ? 'warning' : 'info')); 
                            ?>"><?php echo ucfirst($item['status']); ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($report_type == 'collection'): ?>
<div class="card">
    <div class="card-header">
        <h5>Collection Report</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Invoices Paid</th>
                        <th>Payment Count</th>
                        <th class="text-end">Total Collected</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($collections as $collection): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($collection['collection_date'])); ?></td>
                        <td><?php echo $collection['invoices_paid']; ?></td>
                        <td><?php echo $collection['payment_count']; ?></td>
                        <td class="text-end"><strong>₱<?php echo number_format($collection['total_collected'], 2); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <td colspan="3"><strong>Total</strong></td>
                        <td class="text-end"><strong>₱<?php echo number_format(array_sum(array_column($collections, 'total_collected')), 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php elseif ($report_type == 'customer_sales'): ?>
<div class="card">
    <div class="card-header">
        <h5>Customer Sales Report</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Invoices</th>
                        <th class="text-end">Total Sales</th>
                        <th class="text-end">Total Paid</th>
                        <th class="text-end">Outstanding</th>
                        <th>Max Days Overdue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customerSales as $sale): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($sale['customer_code']); ?></strong><br>
                            <small><?php echo htmlspecialchars($sale['customer_name']); ?></small>
                        </td>
                        <td>
                            <span class="badge <?php echo $sale['customer_type'] == 'member' ? 'bg-info' : 'bg-secondary'; ?>">
                                <?php echo ucfirst($sale['customer_type']); ?>
                            </span>
                        </td>
                        <td><?php echo $sale['invoice_count'] ?? 0; ?></td>
                        <td class="text-end">₱<?php echo number_format($sale['total_sales'] ?? 0, 2); ?></td>
                        <td class="text-end text-success">₱<?php echo number_format($sale['total_paid'] ?? 0, 2); ?></td>
                        <td class="text-end">
                            <span class="<?php echo ($sale['total_outstanding'] ?? 0) > 0 ? 'text-danger' : 'text-success'; ?>">
                                ₱<?php echo number_format($sale['total_outstanding'] ?? 0, 2); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($sale['max_days_overdue'] && $sale['max_days_overdue'] > 0): ?>
                            <span class="text-danger"><?php echo $sale['max_days_overdue']; ?> days</span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
<?php if ($report_type == 'aging'): ?>
const statusData = <?php echo json_encode($statusSummary); ?>;
const ctx = document.getElementById('statusChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: statusData.map(d => d.status.charAt(0).toUpperCase() + d.status.slice(1)),
        datasets: [{
            data: statusData.map(d => parseFloat(d.total_balance) || 0),
            backgroundColor: [
                'rgba(13, 110, 253, 0.8)',
                'rgba(255, 193, 7, 0.8)',
                'rgba(25, 135, 84, 0.8)',
                'rgba(220, 53, 69, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true
    }
});
<?php endif; ?>
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
