<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/index.php");
    exit();
}

// Check if user has permission to view inventory
requirePermission('view_inventory');

// Fetch inventory items with their categories
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.category_id 
        ORDER BY p.name";
$inventory_items = fetchAll($sql);

// Fetch low stock items
$sql = "SELECT COUNT(*) FROM products WHERE quantity_in_stock <= reorder_level";
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
</head>
<body>

<?php include '../../includes/sidebar.php'; ?>
<?php include '../../includes/header.php'; ?>
<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Stock Management</h2>
            <div>
                <a href="movements/index.php" class="btn btn-outline-primary me-2">
                    <i class="bi bi-clock-history"></i> Movement History
                </a>
            </div>
        </div>

        <!-- Inventory Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Items</h5>
                        <h3 class="mb-0"><?php echo count($inventory_items); ?></h3>
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
        </div>

        <!-- Inventory Table -->
        <div class="card">
            <div class="card-body">
                <table id="inventoryTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Stock Level</th>
                            <th>Unit Price</th>
                            <th>Total Value</th>
                            <?php if ($can_manage_inventory): ?>
                            <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                <td>
                                    <span class="<?php echo $item['quantity_in_stock'] <= $item['reorder_level'] ? 'stock-warning' : 'stock-ok'; ?>">
                                        <?php echo $item['quantity_in_stock']; ?>
                                    </span>
                                </td>
                                <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td>$<?php echo number_format($item['unit_price'] * $item['quantity_in_stock'], 2); ?></td>
                                <?php if ($can_manage_inventory): ?>
                                <td>
                                    <button class="btn btn-sm btn-success" onclick="adjustStock(<?php echo $item['product_id']; ?>)">
                                        <i class="bi bi-arrow-left-right"></i> Adjust Stock
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

<?php if ($can_manage_inventory): ?>
<!-- Stock Adjustment Modal -->
<div class="modal fade" id="adjustStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adjust Stock Level</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="adjustStockForm">
                    <input type="hidden" id="adjust_product_id">
                    <div class="mb-3">
                        <label for="adjustment_type" class="form-label">Adjustment Type</label>
                        <select class="form-control" id="adjustment_type" required>
                            <option value="add">Add Stock</option>
                            <option value="remove">Remove Stock</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="adjustment_quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="adjustment_quantity" required min="1">
                    </div>
                    <div class="mb-3">
                        <label for="adjustment_reason" class="form-label">Reason</label>
                        <textarea class="form-control" id="adjustment_reason" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveStockAdjustment()">Save Adjustment</button>
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
    $('#inventoryTable').DataTable({
        "order": [[1, "asc"]],
        "pageLength": 25
    });

    // Load categories for the add item form
    loadCategories();
});

function loadCategories() {
    $.get('ajax/get_categories.php', function(response) {
        if (response.error) {
            showError('Failed to load categories: ' + response.error);
            return;
        }
        $('#category').html(response);
    }).fail(function() {
        showError('Failed to load categories');
    });
}

function adjustStock(productId) {
    // Reset form
    $('#adjustStockForm')[0].reset();
    $('#adjust_product_id').val(productId);
    $('#adjustStockModal').modal('show');
}

function saveStockAdjustment() {
    const formData = {
        product_id: $('#adjust_product_id').val(),
        type: $('#adjustment_type').val() === 'add' ? 'in' : 'out',
        quantity: $('#adjustment_quantity').val(),
        reason: $('#adjustment_reason').val()
    };

    // Validate required fields
    if (!formData.quantity || !formData.reason) {
        showError('Please fill in all required fields');
        return;
    }

    if (parseFloat(formData.quantity) <= 0) {
        showError('Quantity must be greater than zero');
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

    $.ajax({
        url: 'ajax/adjust_stock.php',
        type: 'POST',
        data: formData,
        dataType: 'json'
    })
    .done(function(response) {
        if (response.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: response.message,
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
                $('#adjustStockModal').modal('hide');
                location.reload();
            });
        } else {
            showError(response.error || 'Failed to adjust stock');
        }
    })
    .fail(function(xhr) {
        let errorMessage = 'Failed to adjust stock';
        try {
            const response = JSON.parse(xhr.responseText);
            errorMessage = response.error || errorMessage;
        } catch (e) {
            console.error('Error parsing response:', e);
        }
        showError(errorMessage);
    })
    .always(function() {
        if (Swal.isLoading()) {
            Swal.close();
        }
    });
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message,
        confirmButtonText: 'OK'
    });
}
</script>

</body>
</html>