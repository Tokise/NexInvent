<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/index.php");
    exit();
}

// Check if user has permission
requirePermission('manage_purchases');

// Fetch low stock products
$sql = "SELECT DISTINCT p.product_id, p.name, p.sku, c.name as category_name, 
        p.in_stock_quantity as current_stock,
        p.in_threshold_amount as threshold,
        p.unit_price,
        GREATEST(p.in_threshold_amount * 2 - p.in_stock_quantity, 10) as suggested_order
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.category_id 
        WHERE p.in_stock_quantity <= p.in_threshold_amount 
        ORDER BY p.in_stock_quantity ASC";
$low_stock_products = fetchAll($sql);

// Fetch pending purchase orders (pending and approved but not completed)
$sql = "SELECT po.*, 
        u1.username as created_by_name,
        u2.username as approved_by_name,
        DATE_FORMAT(po.created_at, '%M %d, %Y') as formatted_date
        FROM purchase_orders po 
        LEFT JOIN users u1 ON po.created_by = u1.user_id
        LEFT JOIN users u2 ON po.approved_by = u2.user_id
        WHERE po.status IN ('pending', 'approved')
        ORDER BY po.created_at DESC";
$pending_orders = fetchAll($sql);

// Fetch completed purchase orders
$sql = "SELECT po.*, 
        u1.username as created_by_name,
        u2.username as approved_by_name
        FROM purchase_orders po 
        LEFT JOIN users u1 ON po.created_by = u1.user_id
        LEFT JOIN users u2 ON po.approved_by = u2.user_id
        WHERE po.status = 'received'
        ORDER BY po.created_at DESC";
$completed_orders = fetchAll($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Purchase Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .print-section { display: none; }
        @media print {
            .no-print { display: none !important; }
            .print-section { display: block; }
            .print-break-after { page-break-after: always; }
        }
        .nav-tabs .nav-link {
            color: #6c757d;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            color: #0d6efd;
            font-weight: 600;
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<?php include '../../includes/sidebar.php'; ?>
<?php include '../../includes/header.php'; ?>

<div class="main-content">
    <div class="container-fluid p-4">
        <h2 class="mb-4">Purchase Orders</h2>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Purchase Orders Tabs -->
        <ul class="nav nav-tabs mb-4" id="purchaseOrderTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="low-stock-tab" data-bs-toggle="tab" data-bs-target="#lowStock" type="button" role="tab">
                    <i class="bi bi-exclamation-triangle me-2"></i>Low Stock Products
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pending-orders-tab" data-bs-toggle="tab" data-bs-target="#pendingOrders" type="button" role="tab">
                    <i class="bi bi-clock-history me-2"></i>Pending Orders
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="completed-orders-tab" data-bs-toggle="tab" data-bs-target="#completedOrders" type="button" role="tab">
                    <i class="bi bi-check-circle me-2"></i>Completed Orders
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="purchaseOrderTabsContent">
            <!-- Low Stock Products Tab -->
            <div class="tab-pane fade show active" id="lowStock" role="tabpanel">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Low Stock Products</h5>
                        <?php if (!empty($low_stock_products)): ?>
                        <button type="button" class="btn btn-light" id="generatePOBtn">
                            <i class="bi bi-file-earmark-plus"></i> Generate Purchase Order
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="lowStockTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll" class="form-check-input">
                                        </th>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>SKU</th>
                                        <th>Current Stock</th>
                                        <th>Threshold</th>
                                        <th>Suggested Order</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($low_stock_products as $product): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input product-select" 
                                                       data-product-id="<?php echo $product['product_id']; ?>"
                                                       data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                       data-suggested-order="<?php echo $product['suggested_order']; ?>"
                                                       data-unit-price="<?php echo $product['unit_price']; ?>">
                                            </td>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                            <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                            <td>
                                                <span class="badge bg-danger">
                                                    <?php echo $product['current_stock']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $product['threshold']; ?></td>
                                            <td><?php echo $product['suggested_order']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Orders Tab -->
            <div class="tab-pane fade" id="pendingOrders" role="tabpanel">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0">Pending Purchase Orders</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="pendingOrdersTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>PO Number</th>
                                        <th>Date Created</th>
                                        <th>Total Amount</th>
                                        <th>Created By</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_orders as $po): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($po['po_number']); ?></td>
                                            <td><?php echo htmlspecialchars($po['formatted_date']); ?></td>
                                            <td>$<?php echo number_format($po['total_amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($po['created_by_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $po['status'] === 'pending' ? 'warning' : 'info'; ?>">
                                                    <?php echo ucfirst($po['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="viewPurchaseOrder('<?php echo $po['po_id']; ?>')">
                                                    <i class="bi bi-eye"></i> View
                                                </button>
                                                <?php if ($po['status'] === 'approved' || $po['status'] === 'ordered'): ?>
                                                <button class="btn btn-sm btn-primary" onclick="viewReceipt('<?php echo $po['po_id']; ?>')">
                                                    <i class="bi bi-receipt"></i> View Receipt
                                                </button>
                                                <button class="btn btn-sm btn-success" onclick="markAsReceived('<?php echo $po['po_id']; ?>')">
                                                    <i class="bi bi-check-lg"></i> Mark Received
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

            <!-- Completed Orders Tab -->
            <div class="tab-pane fade" id="completedOrders" role="tabpanel">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Completed Purchase Orders</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="completedOrdersTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>PO Number</th>
                                        <th>Date Created</th>
                                        <th>Date Completed</th>
                                        <th>Total Amount</th>
                                        <th>Created By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($completed_orders as $po): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($po['po_number']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($po['created_at'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($po['updated_at'])); ?></td>
                                            <td>$<?php echo number_format($po['total_amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($po['created_by_name']); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="viewPurchaseOrder('<?php echo $po['po_id']; ?>')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-primary" onclick="printPurchaseOrder('<?php echo $po['po_id']; ?>')">
                                                    <i class="bi bi-printer"></i>
                                                </button>
                                            </td>
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

<!-- Generate Purchase Order Modal -->
<div class="modal fade" id="generatePOModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Purchase Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="purchaseOrderForm">
                    <div class="mb-3">
                        <label class="form-label">Selected Products</label>
                        <div class="table-responsive">
                            <table class="table" id="poItemsTable">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-end">Total Amount:</th>
                                        <th id="poTotalAmount">$0.00</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="poNotes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="savePOBtn">Generate Order</button>
            </div>
        </div>
    </div>
</div>

<!-- Mark Products Modal -->
<div class="modal fade" id="markProductsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mark Received Products</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="markProductsForm">
                    <input type="hidden" id="mark_po_id">
                    <div class="mb-3">
                        <div class="d-flex justify-content-end mb-2">
                            <button type="button" class="btn btn-secondary" id="markAllBtn">
                                <i class="bi bi-check-all"></i> Mark All as Received
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table" id="markProductsTable">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Ordered Quantity</th>
                                        <th>Received Quantity</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="receiveNotes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="completeReceiveBtn">Complete Receiving</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTables
    $('#lowStockTable').DataTable({
        pageLength: 25,
        order: [[4, 'asc']] // Sort by Current Stock
    });

    $('#pendingOrdersTable').DataTable({
        pageLength: 25,
        order: [[1, 'desc']] // Sort by Date Created
    });

    $('#completedOrdersTable').DataTable({
        pageLength: 25,
        order: [[1, 'desc']] // Sort by Date
    });

    // Handle select all checkbox
    $('#selectAll').change(function() {
        $('.product-select').prop('checked', $(this).prop('checked'));
        updateGenerateButton();
    });

    // Handle individual checkboxes
    $('.product-select').change(function() {
        updateGenerateButton();
    });

    // Handle generate PO button
    $('#generatePOBtn').click(function() {
        const selectedProducts = getSelectedProducts();
        if (selectedProducts.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No Products Selected',
                text: 'Please select at least one product to generate a purchase order.'
            });
            return;
        }

        // Show confirmation dialog
        Swal.fire({
            title: 'Generate Purchase Order?',
            text: `Generate purchase order for ${selectedProducts.length} selected products?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, generate',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                generatePurchaseOrder(selectedProducts);
            }
        });
    });

    // Handle Save PO button click
    $('#savePOBtn').click(function(e) {
        e.preventDefault();
        const items = [];
        let hasError = false;

        $('#poItemsTable tbody tr').each(function() {
            const quantity = parseInt($(this).find('.quantity-input').val());
            if (quantity < 1) {
                alert('Quantity must be at least 1');
                hasError = true;
                return false;
            }
            
            const unitPrice = parseFloat($(this).find('.quantity-input').data('unit-price'));
            items.push({
                product_id: $(this).find('.quantity-input').data('product-id'),
                quantity: quantity,
                unit_price: unitPrice
            });
        });

        if (hasError) return;

        const poData = {
            notes: $('#poNotes').val().trim(),
            items: items
        };

        $.ajax({
            url: 'ajax/save_purchase_order.php',
            method: 'POST',
            data: JSON.stringify(poData),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.error || 'Failed to create purchase order');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', error);
                alert('Failed to create purchase order');
            }
        });
    });

    // Handle Mark All button click
    $('#markAllBtn').click(function() {
        $('#markProductsTable tbody tr').each(function() {
            const orderedQty = parseInt($(this).find('.ordered-qty').text());
            $(this).find('.received-qty').val(orderedQty);
            updateReceiveStatus($(this).find('.received-qty')[0]);
        });
    });

    // Handle Complete Receive button click
    $('#completeReceiveBtn').click(function() {
        const items = [];
        let hasError = false;

        $('#markProductsTable tbody tr').each(function() {
            const receivedQty = parseInt($(this).find('.received-qty').val());
            if (isNaN(receivedQty) || receivedQty < 0) {
                toastr.error('Please enter valid quantities');
                hasError = true;
                return false;
            }

            items.push({
                product_id: $(this).data('product-id'),
                received_quantity: receivedQty
            });
        });

        if (hasError) return;

        const data = {
            po_id: $('#mark_po_id').val(),
            notes: $('#receiveNotes').val().trim(),
            items: items
        };

        // Show loading state
        const $btn = $(this);
        const originalText = $btn.html();
        $btn.html('<i class="spinner-border spinner-border-sm"></i> Processing...').prop('disabled', true);

        $.ajax({
            url: 'ajax/complete_purchase_order.php',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.message || 'Purchase order has been marked as received.',
                        showConfirmButton: true
                    }).then((result) => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.error || 'Failed to complete purchase order'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to complete purchase order. Please try again.'
                });
            },
            complete: function() {
                // Restore button state
                $btn.html(originalText).prop('disabled', false);
                $('#markProductsModal').modal('hide');
            }
        });
    });

    // Initialize toastr
    toastr.options = {
        "closeButton": true,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "timeOut": "3000"
    };
});

function updateGenerateButton() {
    const selectedCount = $('.product-select:checked').length;
    $('#generatePOBtn').prop('disabled', selectedCount === 0);
}

function getSelectedProducts() {
    return $('.product-select:checked').map(function() {
        const $checkbox = $(this);
        return {
            product_id: parseInt($checkbox.data('product-id')),
            product_name: $checkbox.data('product-name'),
            quantity: parseInt($checkbox.data('suggested-order')),
            unit_price: parseFloat($checkbox.data('unit-price'))
        };
    }).get();
}

function generatePurchaseOrder(products) {
    // Show loading state
    Swal.fire({
        title: 'Generating Purchase Order',
        text: 'Please wait...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Send request to generate PO
    fetch('ajax/generate_po.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            items: products
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: `Purchase order ${data.data.po_number} has been generated successfully.`,
                showConfirmButton: true
            }).then(() => {
                // Refresh the page to show the new PO
                window.location.href = `view_po.php?id=${data.data.po_id}`;
            });
        } else {
            throw new Error(data.error || 'Failed to generate purchase order');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'Failed to generate purchase order'
        });
    });
}

function viewPurchaseOrder(poId) {
    window.location.href = `view_po.php?id=${poId}`;
}

function printPurchaseOrder(poId) {
    window.open(`print_po.php?id=${poId}`, '_blank');
}

function viewReceipt(poId) {
    window.location.href = `view_receipt.php?id=${poId}`;
}

function markAsReceived(poId) {
    Swal.fire({
        title: 'Mark as Received?',
        text: 'This will update the stock levels. Continue?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, mark as received',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            Swal.fire({
                title: 'Processing...',
                text: 'Please wait while we update the stock levels.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Prepare the data
            const data = {
                po_id: poId,
                items: [] // This will be populated from the receipt view
            };

            // Send request to mark as received
            fetch('ajax/mark_po_received.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        showConfirmButton: true
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    throw new Error(data.error || 'Failed to mark as received');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Failed to mark as received'
                });
            });
        }
    });
}

function updateReceiveStatus(input) {
    const row = $(input).closest('tr');
    const orderedQty = parseInt(row.find('.ordered-qty').text());
    const receivedQty = parseInt(input.value) || 0;
    
    let status, badgeClass;
    if (receivedQty === 0) {
        status = 'Pending';
        badgeClass = 'bg-warning';
    } else if (receivedQty < orderedQty) {
        status = 'Partial';
        badgeClass = 'bg-info';
    } else if (receivedQty === orderedQty) {
        status = 'Complete';
        badgeClass = 'bg-success';
    } else {
        status = 'Over';
        badgeClass = 'bg-danger';
    }
    
    row.find('.receive-status').html(`
        <span class="badge ${badgeClass}">${status}</span>
    `);
}
</script>

</body>
</html>