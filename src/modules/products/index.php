<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/permissions.php';


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/index.php");
    exit();
}

// Check if user has permission to view products
requirePermission('view_products');

// Get user permissions for UI rendering
$can_manage_products = hasPermission('manage_products');

// Fetch all products with their categories
$sql = "SELECT p.*, c.name as category_name, 
        (SELECT COUNT(*) FROM stock_movements WHERE product_id = p.product_id) as movement_count
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.category_id 
        ORDER BY p.name";
$products = fetchAll($sql);

// Fetch categories for the filter and add form
$categories = fetchAll("SELECT * FROM categories ORDER BY name");

// Get low stock products count
$low_stock_count = fetchValue("SELECT COUNT(*) FROM products WHERE quantity_in_stock <= reorder_level");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Products Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
   
</head>
<body>

<?php include '../../includes/sidebar.php'; ?>
<?php include '../../includes/header.php'; ?>
<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Products Management</h2>
            <div>
                <?php if ($can_manage_products): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="bi bi-plus-lg"></i> Add Product
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Products</h5>
                        <h3 class="mb-0"><?php echo count($products); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title">Low Stock Items</h5>
                        <h3 class="mb-0"><?php echo $low_stock_count; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Categories</h5>
                        <h3 class="mb-0"><?php echo count($categories); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="productsTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Stock Level</th>
                                <th>Unit Price</th>
                                <th>Reorder Level</th>
                                <th>Movements</th>
                                <?php if ($can_manage_products): ?>
                                <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                    <td>
                                        <span class="<?php echo $product['quantity_in_stock'] <= $product['reorder_level'] ? 'stock-warning' : 'stock-ok'; ?>">
                                            <?php echo $product['quantity_in_stock']; ?>
                                        </span>
                                    </td>
                                    <td>$<?php echo number_format($product['unit_price'], 2); ?></td>
                                    <td><?php echo $product['reorder_level']; ?></td>
                                    <td>
                                        <a href="../stock/movements/index.php?product_id=<?php echo $product['product_id']; ?>" 
                                           class="btn btn-sm btn-info">
                                            <?php echo $product['movement_count']; ?> movements
                                        </a>
                                    </td>
                                    <?php if ($can_manage_products): ?>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                onclick="editProduct(<?php echo $product['product_id']; ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="deleteProduct(<?php echo $product['product_id']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($can_manage_products): ?>
<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addProductForm" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="sku" class="form-label">SKU</label>
                        <input type="text" class="form-control" id="sku" name="sku" required>
                        <div class="invalid-feedback">Please enter SKU</div>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                        <div class="invalid-feedback">Please enter product name</div>
                    </div>

                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['category_id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a category</div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="unit_price" class="form-label">Unit Price</label>
                        <input type="number" class="form-control" id="unit_price" name="unit_price" 
                               step="0.01" min="0" required>
                        <div class="invalid-feedback">Please enter unit price</div>
                    </div>

                    <div class="mb-3">
                        <label for="initial_stock" class="form-label">Initial Stock</label>
                        <input type="number" class="form-control" id="initial_stock" name="initial_stock" 
                               min="0" required>
                        <div class="invalid-feedback">Please enter initial stock</div>
                    </div>

                    <div class="mb-3">
                        <label for="reorder_level" class="form-label">Reorder Level</label>
                        <input type="number" class="form-control" id="reorder_level" name="reorder_level" 
                               min="0" required>
                        <div class="invalid-feedback">Please enter reorder level</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveProduct()">Save Product</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editProductForm" class="needs-validation" novalidate>
                    <input type="hidden" id="edit_product_id">
                    <div class="mb-3">
                        <label for="edit_sku" class="form-label">SKU</label>
                        <input type="text" class="form-control" id="edit_sku" name="sku" required>
                        <div class="invalid-feedback">Please enter SKU</div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                        <div class="invalid-feedback">Please enter product name</div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_category_id" class="form-label">Category</label>
                        <select class="form-select" id="edit_category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['category_id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a category</div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="edit_unit_price" class="form-label">Unit Price</label>
                        <input type="number" class="form-control" id="edit_unit_price" name="unit_price" 
                               step="0.01" min="0" required>
                        <div class="invalid-feedback">Please enter unit price</div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_reorder_level" class="form-label">Reorder Level</label>
                        <input type="number" class="form-control" id="edit_reorder_level" name="reorder_level" 
                               min="0" required>
                        <div class="invalid-feedback">Please enter reorder level</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateProduct()">Update Product</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#productsTable').DataTable({
        "order": [[1, "asc"]],
        "pageLength": 25
    });
});

function saveProduct() {
    const form = document.getElementById('addProductForm');
    if (form.checkValidity()) {
        const formData = {
            sku: $('#sku').val(),
            name: $('#name').val(),
            category_id: $('#category_id').val(),
            description: $('#description').val(),
            unit_price: $('#unit_price').val(),
            quantity_in_stock: $('#initial_stock').val(),
            reorder_level: $('#reorder_level').val()
        };

        $.ajax({
            url: 'ajax/save_product.php',
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Product saved successfully'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    throw new Error(response.error || 'Failed to save product');
                }
            },
            error: function(xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: xhr.responseJSON?.error || 'Failed to save product'
                });
            }
        });
    }
    form.classList.add('was-validated');
}

function editProduct(productId) {
    $.get('ajax/get_product.php', { id: productId }, function(response) {
        if (response.success) {
            const product = response.data;
            $('#edit_product_id').val(product.product_id);
            $('#edit_sku').val(product.sku);
            $('#edit_name').val(product.name);
            $('#edit_category_id').val(product.category_id);
            $('#edit_description').val(product.description);
            $('#edit_unit_price').val(product.unit_price);
            $('#edit_reorder_level').val(product.reorder_level);
            $('#editProductModal').modal('show');
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: response.error || 'Failed to load product details'
            });
        }
    });
}

function updateProduct() {
    const form = document.getElementById('editProductForm');
    if (form.checkValidity()) {
        const formData = {
            product_id: $('#edit_product_id').val(),
            sku: $('#edit_sku').val(),
            name: $('#edit_name').val(),
            category_id: $('#edit_category_id').val(),
            description: $('#edit_description').val(),
            unit_price: $('#edit_unit_price').val(),
            reorder_level: $('#edit_reorder_level').val()
        };

        $.ajax({
            url: 'ajax/update_product.php',
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Product updated successfully'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    throw new Error(response.error || 'Failed to update product');
                }
            },
            error: function(xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: xhr.responseJSON?.error || 'Failed to update product'
                });
            }
        });
    }
    form.classList.add('was-validated');
}

function deleteProduct(productId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax/delete_product.php',
                method: 'POST',
                data: { product_id: productId },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: 'Product deleted successfully'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        throw new Error(response.error || 'Failed to delete product');
                    }
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: xhr.responseJSON?.error || 'Failed to delete product'
                    });
                }
            });
        }
    });
}
</script>

</body>
</html>