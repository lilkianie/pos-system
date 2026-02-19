<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('view_dashboard');
$db = new Database();

// Get statistics
$stats = [
    'total_sales_today' => $db->fetch("SELECT COALESCE(SUM(final_amount), 0) as total FROM transactions WHERE DATE(created_at) = CURDATE() AND payment_status != 'voided'")['total'] ?? 0,
    'total_transactions_today' => $db->fetch("SELECT COUNT(*) as count FROM transactions WHERE DATE(created_at) = CURDATE() AND payment_status != 'voided'")['count'] ?? 0,
    'total_products' => $db->fetch("SELECT COUNT(*) as count FROM products WHERE is_active = 1")['count'] ?? 0,
    'low_stock_items' => $db->fetch("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= min_stock_level AND is_active = 1")['count'] ?? 0,
];

// Recent transactions
$recent_transactions = $db->fetchAll(
    "SELECT t.*, u.full_name as cashier_name 
     FROM transactions t 
     JOIN users u ON t.user_id = u.id 
     ORDER BY t.created_at DESC 
     LIMIT 10"
);

// Sales chart data (last 7 days)
$sales_data = $db->fetchAll(
    "SELECT DATE(created_at) as date, SUM(final_amount) as total 
     FROM transactions 
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     AND payment_status != 'voided'
     GROUP BY DATE(created_at)
     ORDER BY date ASC"
);

// AR Statistics
$ar_stats = [
    'total_outstanding' => $db->fetch("SELECT COALESCE(SUM(balance), 0) as total FROM accounts_receivable WHERE status IN ('open', 'partial', 'overdue')")['total'] ?? 0,
    'overdue_amount' => $db->fetch("SELECT COALESCE(SUM(balance), 0) as total FROM accounts_receivable WHERE status = 'overdue'")['total'] ?? 0,
    'total_customers' => $db->fetch("SELECT COUNT(DISTINCT customer_id) as count FROM accounts_receivable WHERE status IN ('open', 'partial', 'overdue')")['count'] ?? 0,
    'collections_today' => $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM ar_payments WHERE DATE(payment_date) = CURDATE()")['total'] ?? 0,
];

// AP Statistics
$ap_stats = [
    'total_outstanding' => $db->fetch("SELECT COALESCE(SUM(balance), 0) as total FROM accounts_payable WHERE status IN ('open', 'partial', 'overdue')")['total'] ?? 0,
    'overdue_amount' => $db->fetch("SELECT COALESCE(SUM(balance), 0) as total FROM accounts_payable WHERE status = 'overdue'")['total'] ?? 0,
    'total_suppliers' => $db->fetch("SELECT COUNT(DISTINCT supplier_id) as count FROM accounts_payable WHERE status IN ('open', 'partial', 'overdue')")['count'] ?? 0,
    'payments_today' => $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM ap_payments WHERE DATE(payment_date) = CURDATE()")['total'] ?? 0,
];

// AR Aging data
$ar_aging = $db->fetchAll(
    "SELECT 
     CASE 
         WHEN DATEDIFF(CURDATE(), due_date) <= 0 THEN 'current'
         WHEN DATEDIFF(CURDATE(), due_date) <= 30 THEN '1-30'
         WHEN DATEDIFF(CURDATE(), due_date) <= 60 THEN '31-60'
         WHEN DATEDIFF(CURDATE(), due_date) <= 90 THEN '61-90'
         ELSE '90+'
     END as aging_bucket,
     SUM(balance) as total
     FROM accounts_receivable
     WHERE status IN ('open', 'partial', 'overdue')
     GROUP BY aging_bucket
     ORDER BY 
     CASE aging_bucket
         WHEN 'current' THEN 1
         WHEN '1-30' THEN 2
         WHEN '31-60' THEN 3
         WHEN '61-90' THEN 4
         WHEN '90+' THEN 5
     END"
);

// AP Aging data
$ap_aging = $db->fetchAll(
    "SELECT 
     CASE 
         WHEN DATEDIFF(CURDATE(), due_date) <= 0 THEN 'current'
         WHEN DATEDIFF(CURDATE(), due_date) <= 30 THEN '1-30'
         WHEN DATEDIFF(CURDATE(), due_date) <= 60 THEN '31-60'
         WHEN DATEDIFF(CURDATE(), due_date) <= 90 THEN '61-90'
         ELSE '90+'
     END as aging_bucket,
     SUM(balance) as total
     FROM accounts_payable
     WHERE status IN ('open', 'partial', 'overdue')
     GROUP BY aging_bucket
     ORDER BY 
     CASE aging_bucket
         WHEN 'current' THEN 1
         WHEN '1-30' THEN 2
         WHEN '31-60' THEN 3
         WHEN '61-90' THEN 4
         WHEN '90+' THEN 5
     END"
);

// AR Trend (last 7 days) - fill missing dates
$ar_trend_raw = $db->fetchAll(
    "SELECT DATE(created_at) as date, SUM(amount) as total
     FROM accounts_receivable
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     GROUP BY DATE(created_at)
     ORDER BY date ASC"
);
$ar_trend_map = [];
foreach ($ar_trend_raw as $row) {
    $ar_trend_map[$row['date']] = $row['total'];
}
$ar_trend = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $ar_trend[] = ['date' => $date, 'total' => $ar_trend_map[$date] ?? 0];
}

// AP Trend (last 7 days) - fill missing dates
$ap_trend_raw = $db->fetchAll(
    "SELECT DATE(created_at) as date, SUM(amount) as total
     FROM accounts_payable
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     GROUP BY DATE(created_at)
     ORDER BY date ASC"
);
$ap_trend_map = [];
foreach ($ap_trend_raw as $row) {
    $ap_trend_map[$row['date']] = $row['total'];
}
$ap_trend = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $ap_trend[] = ['date' => $date, 'total' => $ap_trend_map[$date] ?? 0];
}

// AR Collections Trend (last 7 days) - fill missing dates
$ar_collections_raw = $db->fetchAll(
    "SELECT DATE(payment_date) as date, SUM(amount) as total
     FROM ar_payments
     WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     GROUP BY DATE(payment_date)
     ORDER BY date ASC"
);
$ar_collections_map = [];
foreach ($ar_collections_raw as $row) {
    $ar_collections_map[$row['date']] = $row['total'];
}
$ar_collections_trend = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $ar_collections_trend[] = ['date' => $date, 'total' => $ar_collections_map[$date] ?? 0];
}

// AP Payments Trend (last 7 days) - fill missing dates
$ap_payments_raw = $db->fetchAll(
    "SELECT DATE(payment_date) as date, SUM(amount) as total
     FROM ap_payments
     WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     GROUP BY DATE(payment_date)
     ORDER BY date ASC"
);
$ap_payments_map = [];
foreach ($ap_payments_raw as $row) {
    $ap_payments_map[$row['date']] = $row['total'];
}
$ap_payments_trend = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $ap_payments_trend[] = ['date' => $date, 'total' => $ap_payments_map[$date] ?? 0];
}
?>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Sales Today</h6>
                        <h2>₱<?php echo number_format($stats['total_sales_today'], 2); ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-currency-dollar fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Transactions Today</h6>
                        <h2><?php echo $stats['total_transactions_today']; ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-receipt fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Active Products</h6>
                        <h2><?php echo $stats['total_products']; ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-box fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Low Stock Items</h6>
                        <h2><?php echo $stats['low_stock_items']; ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-exclamation-triangle fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($auth->hasPermission('view_ar_reports') || $auth->hasPermission('view_ap_reports')): ?>
<div class="row mb-4">
    <?php if ($auth->hasPermission('view_ar_reports')): ?>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">AR Outstanding</h6>
                        <h2>₱<?php echo number_format($ar_stats['total_outstanding'], 2); ?></h2>
                        <small>Collections Today: ₱<?php echo number_format($ar_stats['collections_today'], 2); ?></small>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-receipt-cutoff fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">AR Overdue</h6>
                        <h2>₱<?php echo number_format($ar_stats['overdue_amount'], 2); ?></h2>
                        <small><?php echo $ar_stats['total_customers']; ?> customers</small>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-exclamation-triangle fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($auth->hasPermission('view_ap_reports')): ?>
    <div class="col-md-3">
        <div class="card text-white" style="background-color: #6f42c1;">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">AP Outstanding</h6>
                        <h2>₱<?php echo number_format($ap_stats['total_outstanding'], 2); ?></h2>
                        <small>Payments Today: ₱<?php echo number_format($ap_stats['payments_today'], 2); ?></small>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-receipt fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">AP Overdue</h6>
                        <h2>₱<?php echo number_format($ap_stats['overdue_amount'], 2); ?></h2>
                        <small><?php echo $ap_stats['total_suppliers']; ?> suppliers</small>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-exclamation-triangle fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-graph-up"></i> Sales Trend (Last 7 Days)</h5>
            </div>
            <div class="card-body">
                <canvas id="salesChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-clock-history"></i> Recent Transactions</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($recent_transactions as $trans): ?>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo htmlspecialchars($trans['transaction_number']); ?></h6>
                            <small><?php echo date('H:i', strtotime($trans['created_at'])); ?></small>
                        </div>
                        <p class="mb-1"><?php echo htmlspecialchars($trans['cashier_name']); ?></p>
                        <small class="text-success">₱<?php echo number_format($trans['final_amount'], 2); ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($auth->hasPermission('view_ar_reports')): ?>
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-pie-chart"></i> AR Aging Analysis</h5>
            </div>
            <div class="card-body">
                <canvas id="arAgingChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-graph-up-arrow"></i> AR Trend (Last 7 Days)</h5>
            </div>
            <div class="card-body">
                <canvas id="arTrendChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($auth->hasPermission('view_ap_reports')): ?>
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-pie-chart"></i> AP Aging Analysis</h5>
            </div>
            <div class="card-body">
                <canvas id="apAgingChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-graph-down-arrow"></i> AP Trend (Last 7 Days)</h5>
            </div>
            <div class="card-body">
                <canvas id="apTrendChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Sales Chart
const salesData = <?php echo json_encode($sales_data); ?>;
const salesCtx = document.getElementById('salesChart').getContext('2d');
new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: salesData.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }),
        datasets: [{
            label: 'Sales (₱)',
            data: salesData.map(d => parseFloat(d.total) || 0),
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₱' + value.toFixed(0);
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Sales: ₱' + context.parsed.y.toFixed(2);
                    }
                }
            }
        }
    }
});

<?php if ($auth->hasPermission('view_ar_reports')): ?>
// AR Aging Chart
const arAgingData = <?php echo json_encode($ar_aging); ?>;
const arAgingLabels = arAgingData.map(d => d.aging_bucket.toUpperCase());
const arAgingValues = arAgingData.map(d => parseFloat(d.total) || 0);
const arAgingCtx = document.getElementById('arAgingChart').getContext('2d');
new Chart(arAgingCtx, {
    type: 'doughnut',
    data: {
        labels: arAgingLabels,
        datasets: [{
            data: arAgingValues,
            backgroundColor: [
                'rgba(40, 167, 69, 0.8)',   // current - green
                'rgba(255, 193, 7, 0.8)',   // 1-30 - yellow
                'rgba(255, 152, 0, 0.8)',   // 31-60 - orange
                'rgba(255, 87, 34, 0.8)',   // 61-90 - deep orange
                'rgba(220, 53, 69, 0.8)'    // 90+ - red
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ₱' + context.parsed.toFixed(2);
                    }
                }
            }
        }
    }
});

// AR Trend Chart
const arTrendData = <?php echo json_encode($ar_trend); ?>;
const arCollectionsData = <?php echo json_encode($ar_collections_trend); ?>;
const arTrendCtx = document.getElementById('arTrendChart').getContext('2d');
new Chart(arTrendCtx, {
    type: 'bar',
    data: {
        labels: arTrendData.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }),
        datasets: [
            {
                label: 'AR Created',
                data: arTrendData.map(d => parseFloat(d.total) || 0),
                backgroundColor: 'rgba(13, 110, 253, 0.5)',
                borderColor: 'rgb(13, 110, 253)',
                borderWidth: 1
            },
            {
                label: 'Collections',
                data: arCollectionsData.map(d => parseFloat(d.total) || 0),
                backgroundColor: 'rgba(25, 135, 84, 0.5)',
                borderColor: 'rgb(25, 135, 84)',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₱' + value.toFixed(0);
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ₱' + context.parsed.y.toFixed(2);
                    }
                }
            }
        }
    }
});
<?php endif; ?>

<?php if ($auth->hasPermission('view_ap_reports')): ?>
// AP Aging Chart
const apAgingData = <?php echo json_encode($ap_aging); ?>;
const apAgingLabels = apAgingData.map(d => d.aging_bucket.toUpperCase());
const apAgingValues = apAgingData.map(d => parseFloat(d.total) || 0);
const apAgingCtx = document.getElementById('apAgingChart').getContext('2d');
new Chart(apAgingCtx, {
    type: 'doughnut',
    data: {
        labels: apAgingLabels,
        datasets: [{
            data: apAgingValues,
            backgroundColor: [
                'rgba(40, 167, 69, 0.8)',   // current - green
                'rgba(255, 193, 7, 0.8)',   // 1-30 - yellow
                'rgba(255, 152, 0, 0.8)',   // 31-60 - orange
                'rgba(255, 87, 34, 0.8)',   // 61-90 - deep orange
                'rgba(220, 53, 69, 0.8)'    // 90+ - red
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ₱' + context.parsed.toFixed(2);
                    }
                }
            }
        }
    }
});

// AP Trend Chart
const apTrendData = <?php echo json_encode($ap_trend); ?>;
const apPaymentsData = <?php echo json_encode($ap_payments_trend); ?>;
const apTrendCtx = document.getElementById('apTrendChart').getContext('2d');
new Chart(apTrendCtx, {
    type: 'bar',
    data: {
        labels: apTrendData.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }),
        datasets: [
            {
                label: 'AP Created',
                data: apTrendData.map(d => parseFloat(d.total) || 0),
                backgroundColor: 'rgba(111, 66, 193, 0.5)',
                borderColor: 'rgb(111, 66, 193)',
                borderWidth: 1
            },
            {
                label: 'Payments',
                data: apPaymentsData.map(d => parseFloat(d.total) || 0),
                backgroundColor: 'rgba(25, 135, 84, 0.5)',
                borderColor: 'rgb(25, 135, 84)',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₱' + value.toFixed(0);
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ₱' + context.parsed.y.toFixed(2);
                    }
                }
            }
        }
    }
});
<?php endif; ?>
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
