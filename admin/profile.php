<?php
$pageTitle = 'Profile';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requireLogin();
$db = new Database();

$currentUser = $auth->getCurrentUser();
$user = $db->fetch(
    "SELECT u.id, u.username, u.email, u.full_name, u.avatar_url, u.role_id, r.role_name 
     FROM users u 
     LEFT JOIN roles r ON u.role_id = r.id 
     WHERE u.id = ?",
    [$currentUser['id']]
);
if (!$user) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}
$user['email'] = $user['email'] ?? '';
$user['full_name'] = $user['full_name'] ?? '';
$user['role_name'] = $user['role_name'] ?? '';
$avatarUrl = !empty($user['avatar_url']) ? (APP_URL . '/assets/uploads/' . $user['avatar_url']) : null;
$name = $user['full_name'] ?? $user['username'] ?? 'U';
$parts = preg_split('/\s+/', trim($name), 2);
$initials = isset($parts[1]) ? mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1) : mb_substr($name, 0, 2);
$initials = strtoupper(mb_substr($initials, 0, 2));
?>
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person"></i> Profile Settings</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-4">
                    <div class="profile-avatar-wrap me-3">
                        <img id="profileAvatarImg" src="<?php echo $avatarUrl ? htmlspecialchars($avatarUrl) : ''; ?>" alt="Avatar" class="profile-avatar rounded-circle" style="<?php echo $avatarUrl ? '' : 'display:none'; ?>">
                        <span id="profileAvatarInitials" class="profile-avatar-initials rounded-circle d-inline-flex align-items-center justify-content-center bg-primary text-white <?php echo $avatarUrl ? 'd-none' : ''; ?>"><?php echo htmlspecialchars($initials); ?></span>
                    </div>
                    <div>
                        <label class="form-label mb-1">Profile photo</label>
                        <form id="avatarForm" class="d-flex align-items-center gap-2">
                            <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" class="form-control form-control-sm" style="max-width:220px">
                            <button type="submit" class="btn btn-sm btn-outline-primary" id="avatarBtn">Upload</button>
                        </form>
                        <small class="text-muted">JPG, PNG, GIF or WebP. Max 2 MB.</small>
                    </div>
                </div>
                <form id="profileForm">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly disabled>
                        <small class="text-muted">Username cannot be changed</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['role_name'] ?? ''); ?>" readonly disabled>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-key"></i> Change Password</h5>
            </div>
            <div class="card-body">
                <form id="passwordForm">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
jQuery(document).ready(function($) {
    $('#profileForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: '<?php echo APP_URL; ?>/api/profile.php',
            method: 'POST',
            data: {
                action: 'update',
                full_name: $('#full_name').val(),
                email: $('#email').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showSuccess(response.message);
                } else {
                    showError(response.message || 'Error updating profile');
                }
            },
            error: function(xhr) {
                showError('Error: ' + (xhr.responseJSON?.message || xhr.statusText));
            }
        });
    });

    $('#avatarForm').on('submit', function(e) {
        e.preventDefault();
        var input = document.getElementById('avatarInput');
        if (!input.files || !input.files.length) {
            showError('Please select an image');
            return;
        }
        var fd = new FormData();
        fd.append('action', 'upload_avatar');
        fd.append('avatar', input.files[0]);
        $('#avatarBtn').prop('disabled', true);
        $.ajax({
            url: '<?php echo APP_URL; ?>/api/profile.php',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success && response.avatar_url) {
                    $('#profileAvatarImg').attr('src', response.avatar_url).show();
                    $('#profileAvatarInitials').addClass('d-none');
                    showSuccess(response.message);
                } else {
                    showError(response.message || 'Upload failed');
                }
            },
            error: function(xhr) {
                showError('Error: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : xhr.statusText));
            },
            complete: function() {
                $('#avatarBtn').prop('disabled', false);
                input.value = '';
            }
        });
    });

    $('#passwordForm').on('submit', function(e) {
        e.preventDefault();
        var newPwd = $('#new_password').val();
        var confirmPwd = $('#confirm_password').val();
        if (newPwd !== confirmPwd) {
            showError('New passwords do not match');
            return;
        }
        $.ajax({
            url: '<?php echo APP_URL; ?>/api/profile.php',
            method: 'POST',
            data: {
                action: 'change_password',
                current_password: $('#current_password').val(),
                new_password: newPwd,
                confirm_password: confirmPwd
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showSuccess(response.message).then(function() {
                        $('#passwordForm')[0].reset();
                    });
                } else {
                    showError(response.message || 'Error changing password');
                }
            },
            error: function(xhr) {
                showError('Error: ' + (xhr.responseJSON?.message || xhr.statusText));
            }
        });
    });
});
</script>
