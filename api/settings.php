<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth();
$auth->requirePermission('manage_settings');
$db = new Database();

try {
    foreach ($_POST as $key => $value) {
        if ($key == 'offline_mode_enabled') {
            $value = isset($_POST['offline_mode_enabled']) ? '1' : '0';
        }
        $db->query(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = ?",
            [$key, $value, $value]
        );
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
