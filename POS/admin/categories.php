<?php
$pageTitle = 'Categories';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('manage_categories');
$db = new Database();

$categories = $db->fetchAll("SELECT * FROM categories ORDER BY category_name");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-tags"></i> Categories</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="openCategoryModal()">
        <i class="bi bi-plus-circle"></i> Add Category
    </button>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><?php echo $cat['id']; ?></td>
                        <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
                        <td><?php echo htmlspecialchars($cat['description'] ?? ''); ?></td>
                        <td>
                            <span class="badge <?php echo $cat['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo $cat['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="editCategory(<?php echo htmlspecialchars(json_encode($cat)); ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteCategory(<?php echo $cat['id']; ?>)">
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

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add/Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="categoryForm">
                <div class="modal-body">
                    <input type="hidden" id="category_id" name="id">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="category_name" name="category_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
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
                    <button type="submit" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCategoryModal() {
    document.getElementById('categoryForm').reset();
    document.getElementById('category_id').value = '';
    document.getElementById('is_active').checked = true;
}

function editCategory(category) {
    document.getElementById('category_id').value = category.id;
    document.getElementById('category_name').value = category.category_name;
    document.getElementById('description').value = category.description || '';
    document.getElementById('is_active').checked = category.is_active == 1;
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
}

function deleteCategory(id) {
    confirmDelete('this category').then((result) => {
        if (result.isConfirmed) {
            fetch('<?php echo APP_URL; ?>/api/categories.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=delete&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Category deleted successfully').then(() => {
                        location.reload();
                    });
                } else {
                    showError(data.message || 'Error deleting category');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Error deleting category');
            });
        }
    });
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
jQuery(document).ready(function($) {
    $('#categoryForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        $.post('<?php echo APP_URL; ?>/api/categories.php', formData + '&action=save', function(response) {
            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('categoryModal')).hide();
                showSuccess('Category saved successfully').then(() => {
                    location.reload();
                });
            } else {
                showError(response.message || 'Error saving category');
            }
        }, 'json');
    });
});
</script>
