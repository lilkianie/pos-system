<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/auth.php';

$auth = new Auth();
$auth->requireLogin();
$currentUser = $auth->getCurrentUser();
$headerAvatarUrl = !empty($currentUser['avatar_url']) ? (APP_URL . '/assets/uploads/' . $currentUser['avatar_url']) : null;
$headerName = $currentUser['full_name'] ?? $currentUser['username'] ?? 'User';
$headerInitials = preg_match('/\s+/', trim($headerName)) ? implode('', array_map(function($p) { return mb_substr($p, 0, 1); }, preg_split('/\s+/', trim($headerName), 2))) : mb_substr($headerName, 0, 2);
$headerInitials = strtoupper(mb_substr($headerInitials, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
</head>
<body>
    <!-- Top Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary topbar">
        <div class="container-fluid">
            <button class="btn btn-link text-white d-lg-none" type="button" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <a class="navbar-brand" href="<?php echo APP_URL; ?>/admin/dashboard.php">
                <i class="bi bi-shop"></i> <?php echo APP_NAME; ?>
            </a>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo APP_URL; ?>/pos/index.php">
                        <i class="bi bi-cash-register"></i> POS
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <?php if ($headerAvatarUrl): ?>
                            <img src="<?php echo htmlspecialchars($headerAvatarUrl); ?>" alt="" class="navbar-avatar">
                        <?php else: ?>
                            <span class="navbar-avatar-initials"><?php echo htmlspecialchars($headerInitials); ?></span>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($currentUser['full_name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/profile.php">
                            <i class="bi bi-person"></i> Profile
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/settings.php">
                            <i class="bi bi-gear"></i> Settings
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <h5 class="text-white mb-0">Menu</h5>
            </div>
            <ul class="list-unstyled sidebar-menu">
                <li>
                    <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="nav-link">
                        <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
                    </a>
                </li>
                <?php if ($auth->hasPermission('manage_products')): ?>
                <li>
                    <a href="<?php echo APP_URL; ?>/admin/products.php" class="nav-link">
                        <i class="bi bi-box"></i> <span>Products</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($auth->hasPermission('manage_categories')): ?>
                <li>
                    <a href="<?php echo APP_URL; ?>/admin/categories.php" class="nav-link">
                        <i class="bi bi-tags"></i> <span>Categories</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($auth->hasPermission('manage_users')): ?>
                <li>
                    <a href="<?php echo APP_URL; ?>/admin/users.php" class="nav-link">
                        <i class="bi bi-people"></i> <span>Users</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($auth->hasPermission('manage_roles')): ?>
                <li>
                    <a href="<?php echo APP_URL; ?>/admin/roles.php" class="nav-link">
                        <i class="bi bi-shield-check"></i> <span>Roles & Permissions</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="sidebar-divider">
                    <span>Reports</span>
                </li>
                <?php if ($auth->hasPermission('view_reports')): ?>
                <li>
                    <a href="<?php echo APP_URL; ?>/admin/reports.php" class="nav-link">
                        <i class="bi bi-graph-up"></i> <span>Sales Reports</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo APP_URL; ?>/admin/transactions.php" class="nav-link">
                        <i class="bi bi-receipt"></i> <span>Transactions</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo APP_URL; ?>/admin/cash-count-report.php" class="nav-link">
                        <i class="bi bi-cash-coin"></i> <span>Cash Count Report</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="sidebar-divider">
                    <span>Inventory</span>
                </li>
                <?php if ($auth->hasPermission('manage_inventory')): ?>
                <li>
                    <a href="<?php echo APP_URL; ?>/admin/inventory-transactions.php" class="nav-link">
                        <i class="bi bi-box-arrow-up-down"></i> <span>Inventory Transactions</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($auth->hasPermission('view_reports')): ?>
                <li>
                    <a href="<?php echo APP_URL; ?>/admin/inventory-report.php" class="nav-link">
                        <i class="bi bi-clipboard-data"></i> <span>Inventory Report</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="sidebar-divider">
                    <span>Customers & AR</span>
                </li>
                <?php if ($auth->hasPermission('manage_customers')): ?>
                <li>
                    <a href="<?php echo APP_URL; ?>/admin/customers.php" class="nav-link">
                        <i class="bi bi-people-fill"></i> <span>Customers</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($auth->hasPermission('manage_accounts_receivable')): ?>
                <li>
                    <a href="<?php echo APP_URL; ?>/admin/accounts-receivable.php" class="nav-link">
                        <i class="bi bi-receipt-cutoff"></i> <span>Accounts Receivable</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($auth->hasPermission('view_ar_reports')): ?>
                <li>
                    <a href="<?php echo APP_URL; ?>/admin/ar-report.php" class="nav-link">
                        <i class="bi bi-graph-up-arrow"></i> <span>AR Reports</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="sidebar-divider">
                    <span>Purchasing</span>
                </li>
                <?php if ($auth->hasPermission('manage_suppliers')): ?>
                <li>
                    <a href="<?php echo APP_URL; ?>/admin/suppliers.php" class="nav-link">
                        <i class="bi bi-truck"></i> <span>Suppliers</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($auth->hasPermission('manage_purchase_orders')): ?>
                <li>
                    <a href="<?php echo APP_URL; ?>/admin/purchase-orders.php" class="nav-link">
                        <i class="bi bi-cart-check"></i> <span>Purchase Orders</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($auth->hasPermission('manage_accounts_payable')): ?>
                <li>
                    <a href="<?php echo APP_URL; ?>/admin/accounts-payable.php" class="nav-link">
                        <i class="bi bi-receipt"></i> <span>Accounts Payable</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($auth->hasPermission('view_ap_reports')): ?>
                <li>
                    <a href="<?php echo APP_URL; ?>/admin/ap-report.php" class="nav-link">
                        <i class="bi bi-graph-up"></i> <span>AP Reports</span>
                    </a>
                </li>
                    <?php endif; ?>
                </ul>
            </nav>

        <!-- Sidebar Overlay for Mobile -->
        <div class="sidebar-toggle-overlay" onclick="document.getElementById('sidebar').classList.remove('active')"></div>

        <!-- Main Content -->
        <div id="content" class="content">
            <div class="container-fluid">
