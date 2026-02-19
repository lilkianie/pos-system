<?php
$pageTitle = 'Cash Count Report';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('view_reports');
$db = new Database();

$date = $_GET['date'] ?? date('Y-m-d');
$user_id = $_GET['user_id'] ?? '';

$where = "WHERE cc.shift_date = ?";
$params = [$date];

if ($user_id) {
    $where .= " AND cc.user_id = ?";
    $params[] = $user_id;
}

$cashCounts = $db->fetchAll(
    "SELECT cc.*, u.full_name as cashier_name 
     FROM cash_counts cc 
     JOIN users u ON cc.user_id = u.id 
     $where 
     ORDER BY cc.created_at DESC",
    $params
);

$users = $db->fetchAll("SELECT * FROM users WHERE is_active = 1 ORDER BY full_name");
$shiftTypeLabels = ['morning' => 'Morning', 'afternoon' => 'Afternoon', 'night' => 'Night', 'full_day' => 'Full day'];

// Summary
$summary = $db->fetch(
    "SELECT 
        COUNT(*) as total_shifts,
        COUNT(CASE WHEN cc.status = 'closed' THEN 1 END) as closed_shifts,
        SUM(cc.beginning_cash) as total_beginning,
        SUM(cc.ending_cash) as total_ending,
        SUM(cc.difference) as total_difference
     FROM cash_counts cc 
     $where",
    $params
);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-cash-coin"></i> Daily Cash Count Report</h2>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Date</label>
                <input type="date" class="form-control" name="date" value="<?php echo $date; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Cashier</label>
                <select class="form-select" name="user_id">
                    <option value="">All Cashiers</option>
                    <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $user_id == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['full_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label><br>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="cash-count-report.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Card -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h6>Total Shifts</h6>
                <h3><?php echo $summary['total_shifts'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h6>Closed Shifts</h6>
                <h3><?php echo $summary['closed_shifts'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h6>Total Beginning Cash</h6>
                <h3>₱<?php echo number_format($summary['total_beginning'] ?? 0, 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white <?php echo ($summary['total_difference'] ?? 0) == 0 ? 'bg-success' : 'bg-warning'; ?>">
            <div class="card-body">
                <h6>Total Difference</h6>
                <h3>₱<?php echo number_format($summary['total_difference'] ?? 0, 2); ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Cashier</th>
                        <th>Date</th>
                        <th>Shift</th>
                        <th>Beginning Cash</th>
                        <th>Ending Cash</th>
                        <th>Expected Cash</th>
                        <th>Difference</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cashCounts)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted">No cash counts found for this date</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($cashCounts as $cc): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cc['cashier_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($cc['shift_date'])); ?></td>
                        <td><?php echo isset($shiftTypeLabels[$cc['shift_type']]) ? $shiftTypeLabels[$cc['shift_type']] : ucfirst($cc['shift_type'] ?? 'Full day'); ?></td>
                        <td>₱<?php echo number_format($cc['beginning_cash'], 2); ?></td>
                        <td>
                            <?php if ($cc['ending_cash'] !== null): ?>
                                ₱<?php echo number_format($cc['ending_cash'], 2); ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($cc['expected_cash'] !== null): ?>
                                ₱<?php echo number_format($cc['expected_cash'], 2); ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($cc['difference'] !== null): ?>
                                <span class="badge <?php echo $cc['difference'] == 0 ? 'bg-success' : ($cc['difference'] > 0 ? 'bg-warning' : 'bg-danger'); ?>">
                                    ₱<?php echo number_format($cc['difference'], 2); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $cc['status'] == 'open' ? 'bg-warning' : 'bg-success'; ?>">
                                <?php echo ucfirst($cc['status']); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="viewCashCount(<?php echo $cc['id']; ?>)">
                                <i class="bi bi-eye"></i> View Details
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Cash Count Details Modal -->
<div class="modal fade" id="cashCountModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cash Count Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="cashCountDetails">
                <p class="text-center">Loading...</p>
            </div>
        </div>
    </div>
</div>

<script>
function viewCashCount(id) {
    fetch('<?php echo APP_URL; ?>/api/cash-count.php?action=get_cash_count&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const cc = data.cash_count;
                const shiftLabels = { morning: 'Morning', afternoon: 'Afternoon', night: 'Night', full_day: 'Full day' };
                const shiftLabel = shiftLabels[cc.shift_type] || (cc.shift_type || 'Full day');
                let html = `
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Cashier:</strong> ${cc.cashier_name}<br>
                            <strong>Date:</strong> ${new Date(cc.shift_date).toLocaleDateString()}<br>
                            <strong>Shift:</strong> ${shiftLabel}<br>
                            <strong>Status:</strong> <span class="badge ${cc.status == 'open' ? 'bg-warning' : 'bg-success'}">${cc.status.toUpperCase()}</span>
                        </div>
                        <div class="col-md-6">
                            <strong>Started:</strong> ${new Date(cc.started_at).toLocaleString()}<br>
                            ${cc.ended_at ? '<strong>Ended:</strong> ' + new Date(cc.ended_at).toLocaleString() + '<br>' : ''}
                        </div>
                    </div>
                    <hr>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>Beginning Cash:</strong><br>
                            <span class="h5">₱${parseFloat(cc.beginning_cash).toFixed(2)}</span>
                        </div>
                        <div class="col-md-3">
                            <strong>Ending Cash:</strong><br>
                            <span class="h5">${cc.ending_cash ? '₱' + parseFloat(cc.ending_cash).toFixed(2) : '-'}</span>
                        </div>
                        <div class="col-md-3">
                            <strong>Expected Cash:</strong><br>
                            <span class="h5">${cc.expected_cash ? '₱' + parseFloat(cc.expected_cash).toFixed(2) : '-'}</span>
                        </div>
                        <div class="col-md-3">
                            <strong>Difference:</strong><br>
                            <span class="h5 ${cc.difference == 0 ? 'text-success' : (cc.difference > 0 ? 'text-warning' : 'text-danger')}">
                                ${cc.difference !== null ? '₱' + parseFloat(cc.difference).toFixed(2) : '-'}
                            </span>
                        </div>
                    </div>
                `;

                if (data.beginning_items && data.beginning_items.length > 0) {
                    html += `
                        <hr>
                        <h6>Beginning Cash Breakdown:</h6>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Denomination</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;
                    data.beginning_items.forEach(item => {
                        html += `
                            <tr>
                                <td>₱${parseFloat(item.denomination).toFixed(2)}</td>
                                <td>${item.quantity}</td>
                                <td>₱${parseFloat(item.total_amount).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                    html += `
                            </tbody>
                        </table>
                    `;
                }

                if (data.ending_items && data.ending_items.length > 0) {
                    html += `
                        <hr>
                        <h6>Ending Cash Breakdown:</h6>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Denomination</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;
                    data.ending_items.forEach(item => {
                        html += `
                            <tr>
                                <td>₱${parseFloat(item.denomination).toFixed(2)}</td>
                                <td>${item.quantity}</td>
                                <td>₱${parseFloat(item.total_amount).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                    html += `
                            </tbody>
                        </table>
                    `;
                }

                if (cc.beginning_notes) {
                    html += `<hr><strong>Beginning Notes:</strong><br><p class="text-muted">${cc.beginning_notes}</p>`;
                }
                if (cc.ending_notes) {
                    html += `<hr><strong>Ending Notes:</strong><br><p class="text-muted">${cc.ending_notes}</p>`;
                }

                document.getElementById('cashCountDetails').innerHTML = html;
                new bootstrap.Modal(document.getElementById('cashCountModal')).show();
            } else {
                showError('Error loading cash count details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error loading cash count details');
        });
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
