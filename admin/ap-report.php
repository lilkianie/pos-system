<?php
$pageTitle = 'AP Reports';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('view_ap_reports');
$db = new Database();

$supplier_id = $_GET['supplier_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-90 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$where = "WHERE ap.invoice_date BETWEEN ? AND ?";
$params = [$date_from, $date_to];

if ($supplier_id) {
    $where .= " AND ap.supplier_id = ?";
    $params[] = $supplier_id;
}

// Aging Report
$aging = $db->fetchAll(
    "SELECT ap.*, s.supplier_name, po.po_number,
     CASE 
         WHEN DATEDIFF(CURDATE(), ap.due_date) <= 0 THEN 'current'
         WHEN DATEDIFF(CURDATE(), ap.due_date) <= 30 THEN '1-30'
         WHEN DATEDIFF(CURDATE(), ap.due_date) <= 60 THEN '31-60'
         WHEN DATEDIFF(CURDATE(), ap.due_date) <= 90 THEN '61-90'
         ELSE '90+'
     END as aging_bucket
     FROM accounts_payable ap
     JOIN suppliers s ON ap.supplier_id = s.id
     JOIN purchase_orders po ON ap.purchase_order_id = po.id
     WHERE ap.status IN ('open', 'partial', 'overdue')
     " . ($supplier_id ? "AND ap.supplier_id = ?" : ""),
    $supplier_id ? [$supplier_id] : []
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

// Summary by Status
$statusSummary = $db->fetchAll(
    "SELECT ap.status, COUNT(*) as count, SUM(ap.balance) as total_balance
     FROM accounts_payable ap
     $where
     GROUP BY ap.status",
    $params
);

// Summary by Supplier
$supplierSummary = $db->fetchAll(
    "SELECT s.supplier_name, COUNT(ap.id) as invoice_count, SUM(ap.balance) as total_balance
     FROM accounts_payable ap
     JOIN suppliers s ON ap.supplier_id = s.id
     $where
     GROUP BY s.id, s.supplier_name
     ORDER BY total_balance DESC
     LIMIT 10",
    $params
);

$suppliers = $db->fetchAll("SELECT id, supplier_name FROM suppliers WHERE is_active = 1 ORDER BY supplier_name");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-graph-up"></i> Accounts Payable Reports</h2>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Supplier</label>
                <select class="form-select" name="supplier_id">
                    <option value="">All Suppliers</option>
                    <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?php echo $supplier['id']; ?>" <?php echo $supplier_id == $supplier['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
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
                <a href="ap-report.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

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

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5>Top Suppliers by Outstanding Balance</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Supplier</th>
                                <th>Invoices</th>
                                <th class="text-end">Outstanding Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($supplierSummary as $supplier): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($supplier['supplier_name']); ?></td>
                                <td><?php echo $supplier['invoice_count']; ?></td>
                                <td class="text-end">
                                    <span class="<?php echo $supplier['total_balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                        ₱<?php echo number_format($supplier['total_balance'], 2); ?>
                                    </span>
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
                        <th>PO Number</th>
                        <th>Supplier</th>
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
                        <td><a href="<?php echo APP_URL; ?>/admin/purchase-order-details.php?id=<?php echo $item['purchase_order_id']; ?>"><?php echo htmlspecialchars($item['po_number']); ?></a></td>
                        <td><?php echo htmlspecialchars($item['supplier_name']); ?></td>
                        <td><?php echo $item['invoice_date'] ? date('M d, Y', strtotime($item['invoice_date'])) : '-'; ?></td>
                        <td><?php echo date('M d, Y', strtotime($item['due_date'])); ?></td>
                        <td>
                            <?php 
                            $days = $item['days_overdue'] ?? 0;
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
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
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
