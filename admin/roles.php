<?php
$pageTitle = 'Roles & Permissions';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('manage_roles');
$db = new Database();

$roles = $db->fetchAll("SELECT * FROM roles ORDER BY role_name");
$permissions = $db->fetchAll("SELECT * FROM permissions ORDER BY permission_name");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-shield-check"></i> Roles & Permissions</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roleModal" onclick="openRoleModal()">
        <i class="bi bi-plus-circle"></i> Add Role
    </button>
</div>

<div class="row">
    <?php foreach ($roles as $role): 
        $role_permissions = $db->fetchAll(
            "SELECT p.* FROM permissions p 
             JOIN role_permissions rp ON p.id = rp.permission_id 
             WHERE rp.role_id = ?",
            [$role['id']]
        );
        $permission_ids = array_column($role_permissions, 'id');
    ?>
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h5><?php echo htmlspecialchars($role['role_name']); ?></h5>
                <div>
                    <button class="btn btn-sm btn-primary" onclick="editRole(<?php echo htmlspecialchars(json_encode($role)); ?>)">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <?php if ($role['id'] != 1): ?>
                    <button class="btn btn-sm btn-danger" onclick="deleteRole(<?php echo $role['id']; ?>)">
                        <i class="bi bi-trash"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted"><?php echo htmlspecialchars($role['description'] ?? ''); ?></p>
                <h6>Permissions:</h6>
                <div class="row">
                    <?php foreach ($permissions as $perm): ?>
                    <div class="col-md-6 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" 
                                   id="perm_<?php echo $role['id']; ?>_<?php echo $perm['id']; ?>"
                                   <?php echo in_array($perm['id'], $permission_ids) ? 'checked' : ''; ?>
                                   onchange="updatePermission(<?php echo $role['id']; ?>, <?php echo $perm['id']; ?>, this.checked)">
                            <label class="form-check-label" for="perm_<?php echo $role['id']; ?>_<?php echo $perm['id']; ?>">
                                <?php echo htmlspecialchars($perm['permission_name']); ?>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Role Modal -->
<div class="modal fade" id="roleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add/Edit Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="roleForm">
                <div class="modal-body">
                    <input type="hidden" id="role_id" name="id">
                    <div class="mb-3">
                        <label class="form-label">Role Name</label>
                        <input type="text" class="form-control" id="role_name" name="role_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRoleModal() {
    document.getElementById('roleForm').reset();
    document.getElementById('role_id').value = '';
}

function editRole(role) {
    document.getElementById('role_id').value = role.id;
    document.getElementById('role_name').value = role.role_name;
    document.getElementById('description').value = role.description || '';
    new bootstrap.Modal(document.getElementById('roleModal')).show();
}

function deleteRole(id) {
    confirmDelete('this role').then((result) => {
        if (result.isConfirmed) {
            fetch('<?php echo APP_URL; ?>/api/roles.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=delete&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Role deleted successfully').then(() => {
                        location.reload();
                    });
                } else {
                    showError(data.message || 'Error deleting role');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Error deleting role');
            });
        }
    });
}

function updatePermission(roleId, permissionId, checked) {
    fetch('<?php echo APP_URL; ?>/api/roles.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=update_permission&role_id=' + roleId + '&permission_id=' + permissionId + '&checked=' + (checked ? 1 : 0)
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            showError(data.message || 'Error updating permission').then(() => {
                location.reload();
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('Error updating permission');
    });
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
jQuery(document).ready(function($) {
    $('#roleForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        $.post('<?php echo APP_URL; ?>/api/roles.php', formData + '&action=save', function(response) {
            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('roleModal')).hide();
                showSuccess('Role saved successfully').then(() => {
                    location.reload();
                });
            } else {
                showError(response.message || 'Error saving role');
            }
        }, 'json');
    });
});
</script>
