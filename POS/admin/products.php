<?php
$pageTitle = 'Products';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$auth->requirePermission('manage_products');
$db = new Database();

$page = $_GET['page'] ?? 1;
$search = $_GET['search'] ?? '';
$category_id = $_GET['category_id'] ?? '';
$offset = ($page - 1) * ITEMS_PER_PAGE;

$where = "WHERE p.is_active = 1";
$params = [];

if ($search) {
    $where .= " AND (p.product_name LIKE ? OR p.barcode LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_id) {
    $where .= " AND p.category_id = ?";
    $params[] = $category_id;
}

$products = $db->fetchAll(
    "SELECT p.*, c.category_name 
     FROM products p 
     JOIN categories c ON p.category_id = c.id 
     $where 
     ORDER BY p.product_name 
     LIMIT ? OFFSET ?",
    array_merge($params, [ITEMS_PER_PAGE, $offset])
);

$total = $db->fetch("SELECT COUNT(*) as count FROM products p $where", $params)['count'];
$total_pages = ceil($total / ITEMS_PER_PAGE);

$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY category_name");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-box"></i> Products</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="openProductModal()">
        <i class="bi bi-plus-circle"></i> Add Product
    </button>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-3 mb-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Search by name or barcode..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="category_id">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary w-100">Search</button>
            </div>
            <div class="col-md-3">
                <a href="products.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Barcode</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td>
                            <?php if (!empty($product['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                            <?php else: ?>
                                <div style="width: 50px; height: 50px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-image" style="font-size: 24px; color: #ccc;"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($product['barcode']); ?></td>
                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                        <td>₱<?php echo number_format($product['price'], 2); ?></td>
                        <td>
                            <span class="badge <?php echo $product['stock_quantity'] <= $product['min_stock_level'] ? 'bg-warning' : 'bg-success'; ?>">
                                <?php echo $product['stock_quantity']; ?> <?php echo htmlspecialchars($product['unit']); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <nav>
            <ul class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category_id=<?php echo $category_id; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Product Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add/Edit Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="productForm">
                <div class="modal-body">
                    <input type="hidden" id="product_id" name="id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Barcode</label>
                            <input type="text" class="form-control" id="barcode" name="barcode" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="product_name" name="product_name" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unit</label>
                            <input type="text" class="form-control" id="unit" name="unit" value="pcs" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price</label>
                            <input type="number" step="0.01" class="form-control" id="price" name="price" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Cost</label>
                            <input type="number" step="0.01" class="form-control" id="cost" name="cost" value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Stock Quantity</label>
                            <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" value="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Min Stock Level</label>
                        <input type="number" class="form-control" id="min_stock_level" name="min_stock_level" value="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Product Image</label>
                        <div class="mb-2">
                            <div id="imagePreview" class="text-center mb-3">
                                <img id="currentImage" src="" alt="Product Image" class="product-image-preview" style="display: none;">
                            </div>
                            <div class="input-group">
                                <input type="file" class="form-control" id="product_image" name="image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" onchange="previewImageFile(this)">
                                <button type="button" class="btn btn-outline-secondary" onclick="uploadImage()" id="uploadImageBtn">
                                    <i class="bi bi-upload"></i> Upload
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1">Max file size: 5MB. Supported formats: JPEG, PNG, GIF, WebP</small>
                            <input type="hidden" id="image_url" name="image_url">
                        </div>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeImage()" id="removeImageBtn" style="display: none;">
                            <i class="bi bi-trash"></i> Remove Image
                        </button>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openProductModal() {
    document.getElementById('productForm').reset();
    document.getElementById('product_id').value = '';
    document.getElementById('image_url').value = '';
    document.getElementById('currentImage').style.display = 'none';
    document.getElementById('currentImage').src = '';
    document.getElementById('removeImageBtn').style.display = 'none';
    document.getElementById('product_image').value = '';
}

function uploadImage() {
    const fileInput = document.getElementById('product_image');
    const file = fileInput.files[0];
    
    if (!file) {
        showWarning('Please select an image file');
        return;
    }
    
    // Validate file size (5MB)
    if (file.size > 5 * 1024 * 1024) {
        showError('File size exceeds 5MB limit');
        return;
    }
    
    const formData = new FormData();
    formData.append('image', file);
    
    const uploadBtn = document.getElementById('uploadImageBtn');
    const originalText = uploadBtn.innerHTML;
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Uploading...';
    
    fetch('<?php echo APP_URL; ?>/api/upload-image.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('image_url').value = data.image_url;
            document.getElementById('currentImage').src = data.image_url;
            document.getElementById('currentImage').style.display = 'block';
            document.getElementById('removeImageBtn').style.display = 'inline-block';
            showSuccess('Image uploaded successfully');
        } else {
            showError(data.message || 'Error uploading image');
        }
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = originalText;
    })
    .catch(error => {
        console.error('Error:', error);
        showError('Error uploading image');
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = originalText;
    });
}

function removeImage() {
    document.getElementById('image_url').value = '';
    document.getElementById('currentImage').src = '';
    document.getElementById('currentImage').style.display = 'none';
    document.getElementById('removeImageBtn').style.display = 'none';
    document.getElementById('product_image').value = '';
}

function editProduct(product) {
    document.getElementById('product_id').value = product.id;
    document.getElementById('barcode').value = product.barcode;
    document.getElementById('product_name').value = product.product_name;
    document.getElementById('category_id').value = product.category_id;
    document.getElementById('price').value = product.price;
    document.getElementById('cost').value = product.cost;
    document.getElementById('stock_quantity').value = product.stock_quantity;
    document.getElementById('min_stock_level').value = product.min_stock_level;
    document.getElementById('unit').value = product.unit;
    document.getElementById('description').value = product.description || '';
    new bootstrap.Modal(document.getElementById('productModal')).show();
}

function deleteProduct(id) {
    confirmDelete('this product').then((result) => {
        if (result.isConfirmed) {
            fetch('<?php echo APP_URL; ?>/api/products.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=delete&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Product deleted successfully').then(() => {
                        location.reload();
                    });
                } else {
                    showError(data.message || 'Error deleting product');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Error deleting product');
            });
        }
    });
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
jQuery(document).ready(function($) {
    $('#productForm').on('submit', function(e) {
        e.preventDefault();
        
        // Get form data including image_url
        const formData = {
            id: document.getElementById('product_id').value,
            barcode: document.getElementById('barcode').value,
            product_name: document.getElementById('product_name').value,
            category_id: document.getElementById('category_id').value,
            price: document.getElementById('price').value,
            cost: document.getElementById('cost').value,
            stock_quantity: document.getElementById('stock_quantity').value,
            min_stock_level: document.getElementById('min_stock_level').value,
            unit: document.getElementById('unit').value,
            description: document.getElementById('description').value,
            image_url: document.getElementById('image_url').value,
            action: 'save'
        };
        
        $.post('<?php echo APP_URL; ?>/api/products.php', formData, function(response) {
            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
                showSuccess('Product saved successfully').then(() => {
                    location.reload();
                });
            } else {
                showError(response.message || 'Error saving product');
            }
        }, 'json');
    });
});
</script>
