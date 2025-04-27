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

// Check if user has permission to view inventory
requirePermission('view_inventory');

// Fetch inventory items with their categories
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.category_id 
        ORDER BY p.name";
$inventory_items = fetchAll($sql);

// Fetch low stock items
$sql = "SELECT COUNT(*) FROM products WHERE in_stock_quantity <= reorder_level";
$low_stock_count = fetchValue($sql);

// Get user permissions for UI rendering
$can_manage_inventory = hasPermission('manage_inventory');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Stock Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Add styles after the Google Fonts link -->
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
            <h2>Stock Management</h2>
            <div>
                <a href="pending_additions.php" class="btn btn-outline-primary me-2">
                    <i class="bi bi-box-seam"></i> Pending Additions
                </a>
                <a href="movements/index.php" class="btn btn-outline-primary me-2">
                    <i class="bi bi-clock-history"></i> Movement History
                </a>
            </div>
        </div>

        <!-- Stock Overview Cards -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Items</h5>
                        <h2 class="mb-0"><?php echo count($inventory_items); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title">Low Stock Items</h5>
                        <h2 class="mb-0"><?php echo $low_stock_count; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock Items Table -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Stock Items</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="stockItemsTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>IN Stock</th>
                                <th>OUT Stock</th>
                                <th>Unit Price</th>
                                <th>Total Value</th>
                                <th>Status</th>
                                <?php if ($can_manage_inventory): ?>
                                <th class="text-center">Movements</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                    <td>
                                        <?php if ($item['image_url']): ?>
                                            <img src="../../<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                 class="product-img">
                                        <?php else: ?>
                                            <img src="../../assets/images/no-image.svg" 
                                                 alt="No image"
                                                 class="product-img">
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td>
                                        <span class="<?php echo $item['in_stock_quantity'] <= $item['reorder_level'] ? 'stock-warning' : 'stock-ok'; ?>">
                                            <?php echo $item['in_stock_quantity']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="<?php echo $item['out_stock_quantity'] <= $item['out_threshold_amount'] ? 'stock-warning' : 'stock-ok'; ?>">
                                            <?php echo $item['out_stock_quantity']; ?>
                                        </span>
                                    </td>
                                    <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td>$<?php echo number_format($item['unit_price'] * ($item['in_stock_quantity'] + $item['out_stock_quantity']), 2); ?></td>
                                    <td>
                                        <?php if ($item['in_stock_quantity'] <= $item['reorder_level']): ?>
                                            <span class="badge bg-danger">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($can_manage_inventory): ?>
                                    <td class="text-center">
                                        
                                        <a href="movements/index.php?product_id=<?php echo $item['product_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="bi bi-clock-history"></i> 
                                        </a>
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
    $('#stockItemsTable').DataTable({
        "order": [[2, "asc"]],
        "pageLength": 25
    });
});

function adjustStock(productId, productName) {
    // Reset form
    $('#adjustStockForm')[0].reset();
    $('#adjust_product_id').val(productId);
    $('#product_name').val(productName);
    $('#adjustStockModal').modal('show');
}

function saveStockAdjustment() {
    const formData = {
        product_id: $('#adjust_product_id').val(),
        type: $('#adjustment_type').val(),
        quantity: $('#adjustment_quantity').val(),
        reason: $('#adjustment_reason').val(),
        is_transfer: false
    };

    // Set is_transfer based on the selected type
    if (formData.type === 'in_to_out' || formData.type === 'out_to_in') {
        // Convert the special types to in/out but mark as transfer
        formData.is_transfer = true;
        if (formData.type === 'in_to_out') {
            formData.type = 'out'; // We're moving stock OUT of IN stock
        } else {
            formData.type = 'in';  // We're moving stock IN to IN stock from OUT
        }
    }

    // Validate required fields
    if (!formData.quantity || !formData.reason) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Please fill in all required fields'
        });
        return;
    }

    if (parseFloat(formData.quantity) <= 0) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Quantity must be greater than zero'
        });
        return;
    }

    // Show loading state
    Swal.fire({
        title: 'Adjusting stock...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Send AJAX request
    $.ajax({
        url: 'ajax/adjust_stock.php',
        method: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                // Set appropriate icon and color based on movement type
                let icon = 'success';
                let iconColor = '#28a745';
                
                if (response.movement_type === 'in_to_out') {
                    icon = 'info';
                    iconColor = '#17a2b8';
                } else if (response.movement_type === 'out_to_in') {
                    icon = 'info';
                    iconColor = '#17a2b8';
                } else if (response.movement_type === 'out_adjustment') {
                    icon = 'warning';
                    iconColor = '#ffc107';
                }
                
                Swal.fire({
                    icon: icon,
                    iconColor: iconColor,
                    title: 'Stock Updated',
                    text: response.message,
                    confirmButtonColor: '#28a745'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.error || 'Failed to adjust stock'
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to communicate with the server'
            });
        }
    });
}
</script>

</body>
</html>