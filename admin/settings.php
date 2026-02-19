<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('manage_settings');
$db = new Database();

$settings = $db->fetchAll("SELECT * FROM settings");
$settings_map = [];
foreach ($settings as $setting) {
    $settings_map[$setting['setting_key']] = $setting['setting_value'];
}
?>
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-gear"></i> System Settings</h5>
            </div>
            <div class="card-body">
                <form id="settingsForm">
                    <div class="mb-3">
                        <label class="form-label">Store Name</label>
                        <input type="text" class="form-control" name="store_name" value="<?php echo htmlspecialchars($settings_map['store_name'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tax Rate (%)</label>
                        <input type="number" step="0.01" class="form-control" name="tax_rate" value="<?php echo htmlspecialchars($settings_map['tax_rate'] ?? '12'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Currency</label>
                        <input type="text" class="form-control" name="currency" value="<?php echo htmlspecialchars($settings_map['currency'] ?? 'PHP'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Receipt Footer Text</label>
                        <textarea class="form-control" name="receipt_footer" rows="3"><?php echo htmlspecialchars($settings_map['receipt_footer'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="offline_mode_enabled" id="offline_mode" 
                                   <?php echo ($settings_map['offline_mode_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="offline_mode">Enable Offline Mode</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
jQuery(document).ready(function($) {
    $('#settingsForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        $.post('<?php echo APP_URL; ?>/api/settings.php', formData, function(response) {
            if (response.success) {
                showSuccess('Settings saved successfully');
            } else {
                showError(response.message || 'Error saving settings');
            }
        }, 'json');
    });
});
</script>
