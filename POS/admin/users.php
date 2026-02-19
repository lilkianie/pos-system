<?php
$pageTitle = 'Users';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('manage_users');
$db = new Database();

$users = $db->fetchAll(
    "SELECT u.*, r.role_name 
     FROM users u 
     JOIN roles r ON u.role_id = r.id 
     ORDER BY u.created_at DESC"
);

$roles = $db->fetchAll("SELECT * FROM roles ORDER BY role_name");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-people"></i> Users</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openUserModal()">
        <i class="bi bi-plus-circle"></i> Add User
    </button>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><span class="badge bg-info"><?php echo htmlspecialchars($user['role_name']); ?></span></td>
                        <td>
                            <span class="badge <?php echo $user['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add/Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="userForm">
                <div class="modal-body">
                    <input type="hidden" id="user_id" name="id">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter password for new user">
                        <small class="text-muted" id="passwordHelp">Required for new users. Leave blank when editing existing user.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" id="role_id" name="role_id" required>
                            <option value="">Select Role</option>
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openUserModal() {
    document.getElementById('userForm').reset();
    document.getElementById('user_id').value = '';
    document.getElementById('is_active').checked = true;
    document.getElementById('password').required = true;
    document.getElementById('password').placeholder = 'Enter password (required)';
}

function editUser(user) {
    document.getElementById('user_id').value = user.id;
    document.getElementById('username').value = user.username;
    document.getElementById('full_name').value = user.full_name;
    document.getElementById('email').value = user.email;
    document.getElementById('role_id').value = user.role_id;
    document.getElementById('is_active').checked = user.is_active == 1;
    document.getElementById('password').required = false;
    document.getElementById('password').placeholder = 'Leave blank to keep current password';
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

function deleteUser(id) {
    confirmDelete('this user').then((result) => {
        if (result.isConfirmed) {
            fetch('<?php echo APP_URL; ?>/api/users.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=delete&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('User deleted successfully').then(() => {
                        location.reload();
                    });
                } else {
                    showError(data.message || 'Error deleting user');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Error deleting user');
            });
        }
    });
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
// This script runs after jQuery is loaded (in footer.php)
jQuery(document).ready(function($) {
    $('#userForm').on('submit', function(e) {
        e.preventDefault();
        
        // Validate password for new users
        const userId = document.getElementById('user_id').value;
        const password = document.getElementById('password').value;
        
        if (!userId && !password) {
            showWarning('Password is required for new users').then(() => {
                document.getElementById('password').focus();
            });
            return;
        }
        
        // Validate other required fields
        if (!document.getElementById('username').value || 
            !document.getElementById('full_name').value || 
            !document.getElementById('email').value || 
            !document.getElementById('role_id').value) {
            showWarning('Please fill in all required fields');
            return;
        }
        
        const formData = $(this).serialize();
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Saving...');
        
        $.ajax({
            url: '<?php echo APP_URL; ?>/api/users.php',
            method: 'POST',
            data: formData + '&action=save',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
                    showSuccess('User saved successfully').then(() => {
                        location.reload();
                    });
                } else {
                    showError(response.message || 'Error saving user');
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                console.error('Response:', xhr.responseText);
                showError('Error saving user: ' + (xhr.responseJSON?.message || error));
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>
