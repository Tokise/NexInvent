<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/index.php");
    exit();
}

// Check if user has permission to manage purchases
requirePermission('manage_purchases');

// Fetch all active suppliers
$suppliers = fetchAll("SELECT supplier_id, company_name FROM suppliers ORDER BY company_name");

// Fetch all products
$products = fetchAll("SELECT product_id, name, unit_price FROM products WHERE quantity_in_stock > 0 ORDER BY name");

// Fetch existing purchase orders
$purchaseOrders = fetchAll("
    SELECT po.*, s.company_name, 
           COUNT(poi.po_item_id) as items_count
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.supplier_id
    LEFT JOIN po_items poi ON po.po_id = poi.po_id
    GROUP BY po.po_id
    ORDER BY po.created_at DESC
");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Purchase Orders</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Add Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
 
</head>
<body>

<?php include '../../includes/sidebar.php'; ?>
<?php include '../../includes/header.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Purchase Orders</h2>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPurchaseModal">
                <i class="bi bi-plus-lg"></i> New Purchase Order
            </button>
        </div>

        <!-- Purchase Orders Table -->
        <div class="card">
            <div class="card-body">
                <table id="purchasesTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Supplier</th>
                            <th>Date</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchaseOrders as $po): ?>
                        <tr>
                            <td>PO-<?php echo str_pad($po['po_id'], 6, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($po['company_name']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($po['created_at'])); ?></td>
                            <td>$<?php echo number_format($po['total_amount'], 2); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $po['status'] === 'received' ? 'success' : 
                                        ($po['status'] === 'pending' ? 'warning' : 'danger'); 
                                ?>">
                                    <?php echo ucfirst($po['status']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="viewPurchase(<?php echo $po['po_id']; ?>)">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <?php if ($po['status'] === 'pending'): ?>
                                <button class="btn btn-sm btn-success" onclick="receivePurchase(<?php echo $po['po_id']; ?>)">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Purchase Order Modal -->
<div class="modal fade" id="addPurchaseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Purchase Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addPurchaseForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="supplier" class="form-label">Supplier</label>
                            <select class="form-select" id="supplier" name="supplier_id" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['supplier_id']; ?>">
                                    <?php echo htmlspecialchars($supplier['company_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date" name="date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Items</label>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="itemsTable">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Total</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <select class="form-select product-select" name="product_id[]" required onchange="updatePrice(this)">
                                                <option value="">Select Product</option>
                                                <?php foreach ($products as $product): ?>
                                                <option value="<?php echo $product['product_id']; ?>" 
                                                        data-price="<?php echo $product['unit_price']; ?>">
                                                    <?php echo htmlspecialchars($product['name']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control" name="quantity[]" min="1" required 
                                                   onchange="calculateRowTotal($(this).closest('tr'))">
                                        </td>
                                        <td>
                                            <input type="number" class="form-control" name="unit_price[]" min="0" step="0.01" required 
                                                   onchange="calculateRowTotal($(this).closest('tr'))">
                                        </td>
                                        <td class="row-total">$0.00</td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addItem()">
                            <i class="bi bi-plus-circle"></i> Add Item
                        </button>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="savePurchase()">Create Purchase Order</button>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#purchasesTable').DataTable({
        "order": [[1, "desc"]],
        "pageLength": 25
    });
});

function updatePrice(select) {
    const tr = $(select).closest('tr');
    const price = $(select).find(':selected').data('price') || 0;
    tr.find('input[name="unit_price[]"]').val(price);
    calculateRowTotal(tr);
}

function calculateRowTotal(row) {
    const quantity = $(row).find('input[name="quantity[]"]').val() || 0;
    const price = $(row).find('input[name="unit_price[]"]').val() || 0;
    const total = quantity * price;
    $(row).find('.row-total').text('$' + total.toFixed(2));
    calculateGrandTotal();
}

function calculateGrandTotal() {
    let total = 0;
    $('.row-total').each(function() {
        total += parseFloat($(this).text().replace('$', '')) || 0;
    });
    $('#grandTotal').text('$' + total.toFixed(2));
}

function addItem() {
    const newRow = `
        <tr>
            <td>
                <select class="form-select product-select" name="product_id[]" required onchange="updatePrice(this)">
                    <option value="">Select Product</option>
                    <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['product_id']; ?>" 
                            data-price="<?php echo $product['unit_price']; ?>">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <input type="number" class="form-control" name="quantity[]" min="1" required 
                       onchange="calculateRowTotal($(this).closest('tr'))">
            </td>
            <td>
                <input type="number" class="form-control" name="unit_price[]" min="0" step="0.01" required 
                       onchange="calculateRowTotal($(this).closest('tr'))">
            </td>
            <td class="row-total">$0.00</td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `;
    $('#itemsTable tbody').append(newRow);
}

function savePurchase() {
    const formData = new FormData(document.getElementById('addPurchaseForm'));
    
    $.ajax({
        url: 'ajax/save_purchase.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Purchase order created successfully',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.error
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to create purchase order'
            });
        }
    });
}

function viewPurchase(poNumber) {
    // Implement view functionality
    showLoading('Loading purchase order details...');
    // Add AJAX call here
}

function editPurchase(poNumber) {
    // Implement edit functionality
    showLoading('Loading purchase order...');
    // Add AJAX call here
}

function deletePurchase(poNumber) {
    showConfirm('Are you sure you want to delete this purchase order?', function() {
        showLoading('Deleting purchase order...');
        // Add AJAX call here
    });
}

function receivePurchase(poId) {
    Swal.fire({
        title: 'Receive Purchase Order?',
        text: 'This will update inventory levels.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, receive it',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Processing...',
                text: 'Updating inventory levels',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'ajax/receive_purchase.php',
                type: 'POST',
                data: { po_id: poId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message || 'Purchase order received successfully',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        throw new Error(response.error || 'Failed to receive purchase order');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to receive purchase order: ' + error
                    });
                }
            });
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Check for supplier_id and autoopen parameters
    const urlParams = new URLSearchParams(window.location.search);
    const supplierId = urlParams.get('supplier_id');
    const autoOpen = urlParams.get('autoopen');
    
    if (supplierId && autoOpen === 'true') {
        // Wait a bit for everything to load
        setTimeout(() => {
            // Show the modal
            const purchaseModal = new bootstrap.Modal(document.getElementById('addPurchaseModal'));
            purchaseModal.show();
            
            // Set the supplier in the dropdown
            const supplierSelect = document.getElementById('supplier');
            if (supplierSelect) {
                supplierSelect.value = supplierId;
                // Trigger any change events if needed
                supplierSelect.dispatchEvent(new Event('change'));
            }

            // Set today's date
            const dateInput = document.getElementById('date');
            if (dateInput) {
                dateInput.value = new Date().toISOString().split('T')[0];
            }
        }, 500);
    }
});
</script>

</body>
</html>