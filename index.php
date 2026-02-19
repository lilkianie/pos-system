<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';

$auth = new Auth();
if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
    if ($user['role_name'] == 'Cashier') {
        header('Location: ' . APP_URL . '/pos/index.php');
    } else {
        header('Location: ' . APP_URL . '/admin/dashboard.php');
    }
    exit;
} else {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}
