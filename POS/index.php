<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth();
$auth->requireLogin();
$currentUser = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Cashiering - <?php echo APP_NAME; ?></title>
    <link rel="manifest" href="<?php echo APP_URL; ?>/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/pos.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
            <span class="navbar-brand">
                <i class="bi bi-cash-register"></i> POS Cashiering
            </span>
            <div class="d-flex align-items-center">
                <span class="badge bg-light text-dark me-3" id="connectionStatus">
                    <i class="bi bi-wifi"></i> <span id="statusText">Online</span>
                </span>
                <span class="text-white me-3"><?php echo htmlspecialchars($currentUser['full_name']); ?></span>
                <a href="<?php echo APP_URL; ?>/pos/cash-count.php" class="btn btn-light btn-sm me-2">
                    <i class="bi bi-cash-coin"></i> Cash Count
                </a>
                <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="btn btn-light btn-sm me-2">
                    <i class="bi bi-speedometer2"></i> Admin
                </a>
                <a href="<?php echo APP_URL; ?>/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-3">
        <div class="row">
            <!-- Product Search/Scan Area -->
            <div class="col-md-4">
                <!-- Customer Selection -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5><i class="bi bi-person"></i> Customer</h5>
                    </div>
                    <div class="card-body">
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" id="customerSearch" 
                                   placeholder="Search customer code or name..." onkeyup="searchCustomer()">
                            <button class="btn btn-outline-secondary" onclick="clearCustomer()">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                        <div id="customerSearchResults" class="list-group" style="max-height: 150px; overflow-y: auto; display: none;"></div>
                        <div id="selectedCustomer" class="mt-2" style="display: none;">
                            <div class="alert alert-info mb-0">
                                <strong id="customerName"></strong><br>
                                <small id="customerInfo"></small>
                                <div id="customerStandingAlert" class="mt-2"></div>
                            </div>
                        </div>
                        <input type="hidden" id="selectedCustomerId">
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h5><i class="bi bi-upc-scan"></i> Scan / Search Product</h5>
                    </div>
                    <div class="card-body">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control form-control-lg" id="barcodeInput" 
                                   placeholder="Scan barcode or search..." autofocus>
                            <button class="btn btn-primary" onclick="searchProduct()">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                        <div id="productSearchResults" class="list-group" style="max-height: 300px; overflow-y: auto;"></div>
                    </div>
                </div>

                <!-- Cart Summary -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h5><i class="bi bi-cart"></i> Cart</h5>
                        <button class="btn btn-sm btn-danger" onclick="clearCart()">
                            <i class="bi bi-trash"></i> Clear
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="cartItems" style="max-height: 400px; overflow-y: auto;">
                            <p class="text-muted text-center">Cart is empty</p>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <strong>Subtotal:</strong>
                            <span id="cartSubtotal">₱0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <strong>Discount:</strong>
                            <span id="cartDiscount">₱0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <strong>Tax:</strong>
                            <span id="cartTax">₱0.00</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <h4>Total:</h4>
                            <h4 class="text-primary" id="cartTotal">₱0.00</h4>
                        </div>
                        <div id="pointsSection" style="display: none;" class="mb-2">
                            <div class="alert alert-warning mb-2">
                                <small><i class="bi bi-star-fill"></i> Points: <strong id="customerPoints">0</strong></small>
                            </div>
                            <label class="form-label">Redeem Points</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="pointsRedeem" 
                                       value="0" min="0" onchange="updatePointsDiscount()">
                                <span class="input-group-text">pts</span>
                            </div>
                            <small class="text-muted">Points Discount: <span id="pointsDiscount">₱0.00</span></small>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Discount Amount</label>
                            <input type="number" step="0.01" class="form-control" id="discountInput" 
                                   value="0" onchange="updateCartTotals()">
                        </div>
                        <button class="btn btn-success btn-lg w-100" onclick="openPaymentModal()" id="checkoutBtn" disabled>
                            <i class="bi bi-credit-card"></i> Checkout
                        </button>
                    </div>
                </div>
            </div>

            <!-- Product Grid -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h5><i class="bi bi-grid"></i> Products</h5>
                            </div>
                            <div class="col-md-6">
                                <select class="form-select" id="categoryFilter" onchange="loadProducts()">
                                    <option value="">All Categories</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="productGrid" class="row g-3">
                            <!-- Products will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <h4>Total Amount: <span id="paymentTotal">₱0.00</span></h4>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-select" id="paymentMethod" onchange="updatePaymentFields()">
                            <option value="cash">Cash</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="e_wallet">E-Wallet</option>
                            <option value="credit" id="creditOption" style="display: none;">Credit (Charge to Account)</option>
                        </select>
                    </div>
                    <div id="creditInfo" class="alert alert-info mb-3" style="display: none;">
                        <strong>Credit Sale</strong><br>
                        <small>Customer: <span id="creditCustomerName"></span></small><br>
                        <small>Credit Limit: <span id="creditLimit"></span></small><br>
                        <small>Available Credit: <span id="availableCredit"></span></small>
                    </div>
                    <div class="mb-3" id="cashPaymentFields">
                        <label class="form-label">Amount Received</label>
                        <input type="number" step="0.01" class="form-control" id="amountReceived" 
                               onchange="calculateChange()">
                        <div class="mt-2">
                            <strong>Change: <span id="changeAmount" class="text-success">₱0.00</span></strong>
                        </div>
                    </div>
                    <div class="mb-3" id="cardPaymentFields" style="display: none;">
                        <label class="form-label">Card Number</label>
                        <input type="text" class="form-control" id="cardNumber" placeholder="**** **** **** ****">
                        <label class="form-label mt-2">Cardholder Name</label>
                        <input type="text" class="form-control" id="cardholderName">
                    </div>
                    <div class="mb-3" id="ewalletPaymentFields" style="display: none;">
                        <label class="form-label">E-Wallet Provider</label>
                        <select class="form-select" id="ewalletProvider">
                            <option value="gcash">GCash</option>
                            <option value="paymaya">PayMaya</option>
                            <option value="grabpay">GrabPay</option>
                            <option value="other">Other</option>
                        </select>
                        <label class="form-label mt-2">Reference Number</label>
                        <input type="text" class="form-control" id="ewalletReference">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="processPayment()">
                        <i class="bi bi-check-circle"></i> Process Payment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Receipt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="receiptContent">
                    <!-- Receipt content will be generated here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printReceipt()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Define APP_URL for JavaScript
        const APP_URL = '<?php echo APP_URL; ?>';
        const CURRENT_USER_ID = <?php echo $currentUser['id']; ?>;
    </script>
    <script src="<?php echo APP_URL; ?>/assets/js/sweetalert-helpers.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/pos.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/offline.js"></script>
    <script>
        // Initialize POS
        $(document).ready(function() {
            loadCategories();
            loadProducts();
            loadCartFromStorage();
            checkConnection();
            setInterval(checkConnection, 5000);
            setInterval(syncOfflineTransactions, 30000); // Sync every 30 seconds
            
            // Barcode scanner support
            $('#barcodeInput').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    searchProduct();
                }
            });
        });
    </script>
</body>
</html>
