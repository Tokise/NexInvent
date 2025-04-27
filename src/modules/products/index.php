<?php
require_once '../../includes/require_auth.php';

// Add aggressive history protection to prevent back button to login page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Store session info in browser storage for history tracking
$session_id = session_id();
$user_id = $_SESSION['user_id'];
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

// Get stock statistics
$low_out_stock_count = fetchValue("SELECT COUNT(*) FROM products WHERE out_stock_quantity <= out_threshold_amount");
$low_in_stock_count = fetchValue("SELECT COUNT(*) FROM products WHERE in_stock_quantity <= reorder_level");
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
    <style>
        .product-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .stock-warning {
            color: #dc3545;
            font-weight: bold;
        }
        .stock-ok {
            color: #198754;
            font-weight: bold;
        }
    </style>
</head>
<body>


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
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Low OUT Stock</h5>
                        <h3 class="mb-0"><?php echo $low_out_stock_count; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title">Low IN Stock</h5>
                        <h3 class="mb-0"><?php echo $low_in_stock_count; ?></h3>
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

        <!-- Products Tabs -->
        <ul class="nav nav-tabs mb-4" id="productTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="out-products-tab" data-bs-toggle="tab" data-bs-target="#outProducts" type="button" role="tab">
                    <i class="bi bi-box-seam me-2"></i>OUT Products (Active)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="in-products-tab" data-bs-toggle="tab" data-bs-target="#inProducts" type="button" role="tab">
                    <i class="bi bi-archive me-2"></i>IN Products (Reserved)
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="productTabsContent">
            <!-- OUT Products Tab -->
            <div class="tab-pane fade show active" id="outProducts" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="outProductsTable" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Image</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>OUT Stock</th>
                                        <th>Unit Price</th>
                                        <th>OUT Threshold</th>
                                        <th class="text-center">Movements</th>
                                        <?php if ($can_manage_products): ?>
                                        <th class="text-center">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                            <td>
                                                <?php if ($product['image_url']): ?>
                                                    <img src="../../<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                         class="product-img">
                                                <?php else: ?>
                                                    <img src="../../assets/images/no-image.svg" 
                                                         alt="No image"
                                                         class="product-img">
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                            <td>
                                                <span class="<?php echo $product['out_stock_quantity'] <= $product['out_threshold_amount'] ? 'stock-warning' : 'stock-ok'; ?>">
                                                    <?php echo $product['out_stock_quantity']; ?>
                                                </span>
                                            </td>
                                            <td>$<?php echo number_format($product['unit_price'], 2); ?></td>
                                            <td><?php echo $product['out_threshold_amount']; ?></td>

                                            <td class="text-center">
                                                <a href="../stock/movements/index.php?product_id=<?php echo $product['product_id']; ?>" 
                                                    class="btn btn-sm btn-info">
                                                    <i class="bi bi-clock-history"></i> 
                                                </a>
                                            </td>
                                            <?php if ($can_manage_products): ?>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-success" 
                                                        onclick="moveOutToIn(<?php echo $product['product_id']; ?>)">
                                                    <i class="bi bi-arrow-right"></i>
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

            <!-- IN Products Tab -->
            <div class="tab-pane fade" id="inProducts" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="inProductsTable" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Image</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>IN Stock</th>
                                        <th>Unit Price</th>
                                        <th>Reorder Level</th>
                                        <th class="text-center">Movements</th>
                                        <?php if ($can_manage_products): ?>
                                        <th class="text-center">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                            <td>
                                                <?php if ($product['image_url']): ?>
                                                    <img src="../../<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                         class="product-img">
                                                <?php else: ?>
                                                    <img src="../../assets/images/no-image.svg" 
                                                         alt="No image"
                                                         class="product-img">
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                            <td>
                                                <span class="<?php echo $product['in_stock_quantity'] <= $product['reorder_level'] ? 'stock-warning' : 'stock-ok'; ?>">
                                                    <?php echo $product['in_stock_quantity']; ?>
                                                </span>
                                            </td>
                                            <td>$<?php echo number_format($product['unit_price'], 2); ?></td>
                                            <td><?php echo $product['reorder_level']; ?></td>
                                            <td class="text-center">
                                                <a href="../stock/movements/index.php?product_id=<?php echo $product['product_id']; ?>" 
                                                    class="btn btn-sm btn-info">
                                                    <i class="bi bi-clock-history"></i> 
                                                </a>
                                            </td>
                                            <?php if ($can_manage_products): ?>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-success" 
                                                        onclick="moveInToOut(<?php echo $product['product_id']; ?>)">
                                                    <i class="bi bi-arrow-left"></i>
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
    </div>
</div>

<?php if ($can_manage_products): ?>
<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addProductForm" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Product Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="sku" class="form-label">SKU</label>
                                <input type="text" class="form-control" id="sku" name="sku" required>
                            </div>
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-control" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="unit_price" class="form-label">Unit Price</label>
                                <input type="number" class="form-control" id="unit_price" name="unit_price" step="0.01" required>
                            </div>
                            <div class="mb-3">
                                <label for="initial_stock" class="form-label">Initial IN Stock</label>
                                <input type="number" class="form-control" id="initial_stock" name="initial_stock" min="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="reorder_level" class="form-label">Reorder Level</label>
                                <input type="number" class="form-control" id="reorder_level" name="reorder_level" min="0" required>
                                <small class="text-muted">Minimum stock level before reordering</small>
                            </div>
                            <div class="mb-3">
                                <label for="out_threshold_amount" class="form-label">OUT Stock Threshold</label>
                                <input type="number" class="form-control" id="out_threshold_amount" name="out_threshold_amount" min="0" required>
                                <small class="text-muted">Minimum OUT stock level before alert</small>
                            </div>
                            <div class="mb-3">
                                <label for="image" class="form-label">Product Image</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            </div>
                            <div id="imagePreview" class="mt-2 d-none">
                                <img src="" alt="Preview" class="img-thumbnail" style="max-width: 200px;">
                            </div>
                        </div>
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

<!-- Move IN to OUT Modal -->
<div class="modal fade" id="moveInToOutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Move Stock (IN to OUT)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="moveInToOutForm">
                    <input type="hidden" id="move_in_to_out_product_id">
                    <div class="mb-3">
                        <label for="move_in_to_out_quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="move_in_to_out_quantity" required min="1">
                    </div>
                    <div class="mb-3">
                        <label for="move_in_to_out_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="move_in_to_out_notes" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitMoveInToOut()">Move Stock</button>
            </div>
        </div>
    </div>
</div>

<!-- Move OUT to IN Modal -->
<div class="modal fade" id="moveOutToInModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Move Stock (OUT to IN)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="moveOutToInForm">
                    <input type="hidden" id="move_out_to_in_product_id">
                    <div class="mb-3">
                        <label for="move_out_to_in_quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="move_out_to_in_quantity" required min="1">
                    </div>
                    <div class="mb-3">
                        <label for="move_out_to_in_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="move_out_to_in_notes" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitMoveOutToIn()">Move Stock</button>
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
    // Initialize DataTables
    $('#outProductsTable, #inProductsTable').DataTable({
        order: [[2, 'asc']], // Sort by name by default
        pageLength: 25,
        responsive: true
    });

    // Image preview for add/edit product
    $('#image').change(function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#imagePreview').removeClass('d-none').find('img').attr('src', e.target.result);
            };
            reader.readAsDataURL(file);
        }
    });

    // Initialize Bootstrap modals
    const moveInToOutModal = new bootstrap.Modal(document.getElementById('moveInToOutModal'));
    const moveOutToInModal = new bootstrap.Modal(document.getElementById('moveOutToInModal'));

    // Make modals available globally
    window.moveInToOutModal = moveInToOutModal;
    window.moveOutToInModal = moveOutToInModal;
});

// Save new product
function saveProduct() {
    const form = document.getElementById('addProductForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    
    fetch('ajax/save_product.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Product added successfully');
            location.reload();
        } else {
            alert(data.error || 'Failed to add product');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving the product');
    });
}

// Move stock functions
function moveInToOut(productId) {
    $('#move_in_to_out_product_id').val(productId);
    $('#move_in_to_out_quantity').val('');
    $('#move_in_to_out_notes').val('');
    window.moveInToOutModal.show();
}

function moveOutToIn(productId) {
    $('#move_out_to_in_product_id').val(productId);
    $('#move_out_to_in_quantity').val('');
    $('#move_out_to_in_notes').val('');
    window.moveOutToInModal.show();
}

function submitMoveInToOut() {
    const form = document.getElementById('moveInToOutForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const data = {
        product_id: $('#move_in_to_out_product_id').val(),
        quantity: $('#move_in_to_out_quantity').val(),
        notes: $('#move_in_to_out_notes').val()
    };

    // Show loading state
    Swal.fire({
        title: 'Processing...',
        text: 'Moving stock from IN to OUT',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('ajax/move_in_to_out.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.moveInToOutModal.hide();
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Stock moved successfully',
                showConfirmButton: true
            }).then(() => {
                location.reload();
            });
        } else {
            throw new Error(data.error || 'Failed to move stock');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'An error occurred while moving stock'
        });
    });
}

function submitMoveOutToIn() {
    const form = document.getElementById('moveOutToInForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const data = {
        product_id: $('#move_out_to_in_product_id').val(),
        quantity: $('#move_out_to_in_quantity').val(),
        notes: $('#move_out_to_in_notes').val()
    };

    // Show loading state
    Swal.fire({
        title: 'Processing...',
        text: 'Moving stock from OUT to IN',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('ajax/move_out_to_in.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.moveOutToInModal.hide();
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Stock moved successfully',
                showConfirmButton: true
            }).then(() => {
                location.reload();
            });
        } else {
            throw new Error(data.error || 'Failed to move stock');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'An error occurred while moving stock'
        });
    });
}



function removeImage() {
    $('#current_image').hide();
    $('#remove_image_btn').hide();
    $('#remove_image').val('1');
}

function updateProduct() {
    const form = document.getElementById('editProductForm');
    if (form.checkValidity()) {
        const formData = new FormData(form);
        formData.append('product_id', $('#edit_product_id').val());

        $.ajax({
            url: 'ajax/update_product.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
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