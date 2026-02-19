<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireLogin();
// Cashiers can access their own cash count
if (!$auth->hasPermission('manage_cash_count')) {
    header('Location: ' . APP_URL . '/admin/unauthorized.php');
    exit;
}
$currentUser = $auth->getCurrentUser();

$db = new Database();

$today = date('Y-m-d');

// Get all of today's shifts (multiple per day)
$todayShifts = $db->fetchAll(
    "SELECT * FROM cash_counts 
     WHERE user_id = ? AND shift_date = ? 
     ORDER BY created_at ASC",
    [$currentUser['id'], $today]
);
$openShifts = array_filter($todayShifts, function ($s) { return $s['status'] == 'open'; });
$closedShifts = array_filter($todayShifts, function ($s) { return $s['status'] == 'closed'; });
$openShiftTypes = array_map(function ($s) { return $s['shift_type']; }, $openShifts);

// Today's cash sales (used for expected cash per shift)
$todaySales = $db->fetch(
    "SELECT COALESCE(SUM(final_amount), 0) as total 
     FROM transactions 
     WHERE user_id = ? 
     AND DATE(created_at) = ? 
     AND payment_method = 'cash'
     AND payment_status != 'voided'",
    [$currentUser['id'], $today]
)['total'] ?? 0;

$shiftTypeLabels = ['morning' => 'Morning', 'afternoon' => 'Afternoon', 'night' => 'Night', 'full_day' => 'Full day'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Count - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/pos.css">
    <style>
        .cash-count-card {
            max-width: 600px;
            margin: 0 auto;
        }
        .denomination-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        .denomination-input {
            width: 100px;
        }
        .total-display {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
            <span class="navbar-brand">
                <i class="bi bi-cash-coin"></i> Cash Count
            </span>
            <div class="d-flex align-items-center">
                <span class="text-white me-3"><?php echo htmlspecialchars($currentUser['full_name']); ?></span>
                <a href="<?php echo APP_URL; ?>/pos/index.php" class="btn btn-light btn-sm">
                    <i class="bi bi-cash-register"></i> POS
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="cash-count-card">
            <!-- Today summary -->
            <div class="card mb-3">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="bi bi-calendar-day"></i> <?php echo date('F d, Y'); ?> — <?php echo htmlspecialchars($currentUser['full_name']); ?></h5>
                </div>
                <div class="card-body">
                    <strong>Today's cash sales:</strong> ₱<?php echo number_format($todaySales, 2); ?>
                </div>
            </div>

            <!-- Start new shift (always shown so cashier can start another) -->
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h5><i class="bi bi-play-circle"></i> Start New Shift</h5>
                </div>
                <div class="card-body">
                    <form id="beginningCashForm">
                        <div class="mb-3">
                            <label class="form-label">Shift type</label>
                            <select class="form-select form-select-lg" id="shift_type" name="shift_type" required>
                                <?php foreach ($shiftTypeLabels as $val => $label): ?>
                                <option value="<?php echo $val; ?>" <?php echo in_array($val, $openShiftTypes) ? 'disabled' : ''; ?>>
                                    <?php echo $label; ?><?php echo in_array($val, $openShiftTypes) ? ' (already open)' : ''; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Beginning Cash Amount</label>
                            <input type="number" step="0.01" class="form-control form-control-lg" id="beginning_cash" 
                                   name="beginning_cash" required placeholder="0.00">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cash Breakdown (Optional)</label>
                            <div id="beginningDenominations">
                                <div class="denomination-row">
                                    <select class="form-select denomination-input" name="denomination[]">
                                        <option value="1000">₱1,000</option>
                                        <option value="500">₱500</option>
                                        <option value="200">₱200</option>
                                        <option value="100">₱100</option>
                                        <option value="50">₱50</option>
                                        <option value="20">₱20</option>
                                        <option value="10">₱10</option>
                                        <option value="5">₱5</option>
                                        <option value="1">₱1</option>
                                        <option value="0.25">₱0.25</option>
                                        <option value="0.10">₱0.10</option>
                                        <option value="0.05">₱0.05</option>
                                    </select>
                                    <input type="number" class="form-control" name="quantity[]" placeholder="Qty" min="0" value="0">
                                    <span class="total-amount">₱0.00</span>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="addDenominationRow('beginning')">
                                <i class="bi bi-plus"></i> Add Denomination
                            </button>
                            <div class="mt-3"><strong>Total Breakdown: ₱<span id="beginningTotal">0.00</span></strong></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="beginning_notes" name="beginning_notes" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100" id="btnStartShift">
                            <i class="bi bi-check-circle"></i> Start Shift
                        </button>
                    </form>
                </div>
            </div>

            <!-- Open shifts: End shift form for each -->
            <?php foreach ($openShifts as $open): 
                $expectedForShift = $open['beginning_cash'] + $todaySales;
                $typeLabel = isset($shiftTypeLabels[$open['shift_type']]) ? $shiftTypeLabels[$open['shift_type']] : $open['shift_type'];
            ?>
            <div class="card mb-3 border-success">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-stop-circle"></i> End shift — <?php echo htmlspecialchars($typeLabel); ?></h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-3">
                        <strong>Beginning:</strong> ₱<?php echo number_format($open['beginning_cash'], 2); ?> &nbsp;|&nbsp;
                        <strong>Expected:</strong> ₱<?php echo number_format($expectedForShift, 2); ?>
                    </div>
                    <form class="endingCashForm" data-cash-count-id="<?php echo (int)$open['id']; ?>" data-expected="<?php echo $expectedForShift; ?>">
                        <input type="hidden" name="cash_count_id" value="<?php echo (int)$open['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Ending Cash Amount</label>
                            <input type="number" step="0.01" class="form-control form-control-lg ending_cash_input" required placeholder="0.00">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cash Breakdown (Optional)</label>
                            <div class="endingDenominations">
                                <div class="denomination-row">
                                    <select class="form-select denomination-input">
                                        <option value="1000">₱1,000</option>
                                        <option value="500">₱500</option>
                                        <option value="200">₱200</option>
                                        <option value="100">₱100</option>
                                        <option value="50">₱50</option>
                                        <option value="20">₱20</option>
                                        <option value="10">₱10</option>
                                        <option value="5">₱5</option>
                                        <option value="1">₱1</option>
                                        <option value="0.25">₱0.25</option>
                                        <option value="0.10">₱0.10</option>
                                        <option value="0.05">₱0.05</option>
                                    </select>
                                    <input type="number" class="form-control" placeholder="Qty" min="0" value="0">
                                    <span class="total-amount">₱0.00</span>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-2 add-ending-row"><i class="bi bi-plus"></i> Add row</button>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control ending_notes" rows="2"></textarea>
                        </div>
                        <div class="differenceAlert alert" style="display: none;"></div>
                        <button type="submit" class="btn btn-danger btn-lg w-100">End shift — <?php echo htmlspecialchars($typeLabel); ?></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Closed shifts summary -->
            <?php foreach ($closedShifts as $cc): 
                $typeLabel = isset($shiftTypeLabels[$cc['shift_type']]) ? $shiftTypeLabels[$cc['shift_type']] : $cc['shift_type'];
            ?>
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($typeLabel); ?> — Closed</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-6">Beginning:</div>
                        <div class="col-6 text-end">₱<?php echo number_format($cc['beginning_cash'], 2); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-6">Ending:</div>
                        <div class="col-6 text-end">₱<?php echo number_format($cc['ending_cash'], 2); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-6">Expected:</div>
                        <div class="col-6 text-end">₱<?php echo number_format($cc['expected_cash'], 2); ?></div>
                    </div>
                    <div class="row">
                        <div class="col-6">Difference:</div>
                        <div class="col-6 text-end">
                            <span class="<?php echo $cc['difference'] == 0 ? 'text-success' : ($cc['difference'] > 0 ? 'text-warning' : 'text-danger'); ?>">
                                ₱<?php echo number_format($cc['difference'], 2); ?> (<?php echo $cc['difference'] == 0 ? 'OK' : ($cc['difference'] > 0 ? 'Over' : 'Short'); ?>)
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="mt-3">
                <a href="<?php echo APP_URL; ?>/pos/index.php" class="btn btn-outline-primary w-100">
                    <i class="bi bi-cash-register"></i> Back to POS
                </a>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/sweetalert-helpers.js"></script>
    <script>
        const APP_URL = '<?php echo APP_URL; ?>';

        function addDenominationRow(type) {
            const container = document.getElementById(type + 'Denominations');
            const row = document.createElement('div');
            row.className = 'denomination-row';
            row.innerHTML = `
                <select class="form-select denomination-input" name="denomination[]" onchange="calculateTotal('${type}')">
                    <option value="1000">₱1,000</option>
                    <option value="500">₱500</option>
                    <option value="200">₱200</option>
                    <option value="100">₱100</option>
                    <option value="50">₱50</option>
                    <option value="20">₱20</option>
                    <option value="10">₱10</option>
                    <option value="5">₱5</option>
                    <option value="1">₱1</option>
                    <option value="0.25">₱0.25</option>
                    <option value="0.10">₱0.10</option>
                    <option value="0.05">₱0.05</option>
                </select>
                <input type="number" class="form-control" name="quantity[]" placeholder="Qty" min="0" value="0" onchange="calculateTotal('${type}')" oninput="calculateTotal('${type}')">
                <span class="total-amount">₱0.00</span>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.parentElement.remove(); calculateTotal('${type}')">
                    <i class="bi bi-trash"></i>
                </button>
            `;
            container.appendChild(row);
        }

        function calculateTotal(type) {
            const container = document.getElementById(type + 'Denominations');
            const rows = container.querySelectorAll('.denomination-row');
            let total = 0;

            rows.forEach(row => {
                const denomination = parseFloat(row.querySelector('select').value) || 0;
                const quantity = parseFloat(row.querySelector('input[type="number"]').value) || 0;
                const amount = denomination * quantity;
                total += amount;
                row.querySelector('.total-amount').textContent = '₱' + amount.toFixed(2);
            });

            document.getElementById(type + 'Total').textContent = total.toFixed(2);
            
            // Auto-fill the cash amount input
            const cashInput = document.getElementById('beginning_cash');
            if (cashInput && total > 0) cashInput.value = total.toFixed(2);
        }

        // Beginning cash form (with shift_type)
        document.getElementById('beginningCashForm').addEventListener('submit', function(e) {
            e.preventDefault();
            var shiftTypeSelect = document.getElementById('shift_type');
            if (shiftTypeSelect.options[shiftTypeSelect.selectedIndex].disabled) {
                showError('This shift type already has an open shift. Choose another.');
                return;
            }
            var beginningCash = parseFloat(document.getElementById('beginning_cash').value) || 0;
            var notes = document.getElementById('beginning_notes').value;
            if (beginningCash <= 0) {
                showWarning('Please enter a valid beginning cash amount');
                return;
            }
            var denominations = [];
            document.querySelectorAll('#beginningDenominations .denomination-row').forEach(function(row) {
                var denom = parseFloat(row.querySelector('select').value);
                var qty = parseFloat(row.querySelector('input[type="number"]').value) || 0;
                if (qty > 0) {
                    denominations.push({ denomination: denom, quantity: qty, total_amount: denom * qty });
                }
            });
            var formData = {
                action: 'start_shift',
                shift_type: shiftTypeSelect.value,
                beginning_cash: beginningCash,
                beginning_notes: notes,
                denominations: denominations
            };
            fetch(APP_URL + '/api/cash-count.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(formData)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showSuccess('Shift started successfully').then(function() { location.reload(); });
                } else {
                    showError(data.message || 'Error starting shift');
                }
            })
            .catch(function() { showError('Error starting shift'); });
        });

        // Add row for ending forms
        $(document).on('click', '.add-ending-row', function() {
            var box = $(this).closest('.card-body').find('.endingDenominations');
            var first = box.find('.denomination-row').first().clone();
            first.find('input[type="number"]').val(0);
            first.find('.total-amount').text('₱0.00');
            box.append(first);
        });

        // Ending cash: show difference per form
        $(document).on('input change', '.ending_cash_input', function() {
            var form = $(this).closest('form');
            var expected = parseFloat(form.data('expected')) || 0;
            var ending = parseFloat($(this).val()) || 0;
            var diff = ending - expected;
            var alertDiv = form.find('.differenceAlert');
            if (ending <= 0) { alertDiv.hide(); return; }
            alertDiv.show();
            if (diff === 0) {
                alertDiv.removeClass('alert-warning alert-danger').addClass('alert-success').html('<strong>Perfect!</strong> Cash count matches expected.');
            } else if (diff > 0) {
                alertDiv.removeClass('alert-success alert-danger').addClass('alert-warning').html('<strong>Over:</strong> ₱' + Math.abs(diff).toFixed(2) + ' more than expected.');
            } else {
                alertDiv.removeClass('alert-success alert-warning').addClass('alert-danger').html('<strong>Short:</strong> ₱' + Math.abs(diff).toFixed(2) + ' less than expected.');
            }
        });

        // Ending cash form submit (multiple forms)
        $(document).on('submit', '.endingCashForm', function(e) {
            e.preventDefault();
            var form = $(this);
            var cashCountId = form.data('cash-count-id');
            var endingCash = parseFloat(form.find('.ending_cash_input').val()) || 0;
            var notes = form.find('.ending_notes').val();
            if (endingCash <= 0) {
                showWarning('Please enter a valid ending cash amount');
                return;
            }
            var denominations = [];
            form.find('.endingDenominations .denomination-row').each(function() {
                var row = $(this);
                var denom = parseFloat(row.find('select').val());
                var qty = parseFloat(row.find('input[type="number"]').val()) || 0;
                if (qty > 0) {
                    denominations.push({ denomination: denom, quantity: qty, total_amount: denom * qty });
                }
            });
            var payload = {
                action: 'end_shift',
                cash_count_id: cashCountId,
                ending_cash: endingCash,
                ending_notes: notes,
                denominations: denominations
            };
            showConfirm('Are you sure you want to end this shift? This cannot be undone.', 'End Shift', 'Yes, end shift', 'Cancel').then(function(result) {
                if (!result.isConfirmed) return;
                fetch(APP_URL + '/api/cash-count.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        showSuccess('Shift ended successfully').then(function() { location.reload(); });
                    } else {
                        showError(data.message || 'Error ending shift');
                    }
                })
                .catch(function() { showError('Error ending shift'); });
            });
        });
    </script>
</body>
</html>
