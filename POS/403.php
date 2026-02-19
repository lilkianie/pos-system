<?php
require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Forbidden</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="text-center">
            <h1 class="display-1">403</h1>
            <p class="lead">Access Forbidden</p>
            <a href="<?php echo APP_URL; ?>/index.php" class="btn btn-primary">Go Home</a>
        </div>
    </div>
</body>
</html>
