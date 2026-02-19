<?php
$pageTitle = 'Unauthorized';
require_once __DIR__ . '/includes/header.php';
?>
<div class="alert alert-danger">
    <h4><i class="bi bi-exclamation-triangle"></i> Access Denied</h4>
    <p>You do not have permission to access this page.</p>
    <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="btn btn-primary">Go to Dashboard</a>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
