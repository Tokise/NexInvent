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
requirePermission('view_purchases');

// Get purchase order ID from URL
$po_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$po_id) {
    $_SESSION['error'] = "Invalid purchase order ID.";
    header("Location: index.php");
    exit();
}

// Fetch purchase order details
$sql = "SELECT po.*, 
        u1.username as created_by_name,
        u2.username as approved_by_name
        FROM purchase_orders po 
        LEFT JOIN users u1 ON po.created_by = u1.user_id
        LEFT JOIN users u2 ON po.approved_by = u2.user_id
        WHERE po.po_id = ?";
$po = fetchRow($sql, [$po_id]);

if (!$po) {
    $_SESSION['error'] = "Purchase order not found.";
    header("Location: index.php");
    exit();
}

// Fetch purchase order items
$sql = "SELECT poi.*, p.name as product_name, p.sku, c.name as category_name
        FROM purchase_order_items poi
        JOIN products p ON poi.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE poi.po_id = ?
        ORDER BY poi.po_item_id ASC";
$items = fetchAll($sql, [$po_id]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - View Purchase Order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
</head>
<body>

<?php include '../../includes/sidebar.php'; ?>
<?php include '../../includes/header.php'; ?>

<div class="main-content">
    <div class="container-fluid p-4">
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Purchase Order Details</h2>
            <div>
                <a href="index.php" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <button class="btn btn-primary" onclick="window.open('print_po.php?id=<?php echo $po_id; ?>', '_blank')">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>

        <?php if ($_SESSION['role'] === 'manager' && $po['status'] === 'pending'): ?>
        <div class="alert alert-info mb-4" role="alert">
            <i class="bi bi-info-circle me-2"></i> This purchase order is awaiting approval by an administrator. As a manager, you can view but cannot approve it.
        </div>
        <?php endif; ?>

        <!-- Purchase Order Info -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Order Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="150">PO Number:</th>
                                <td><?php echo htmlspecialchars($po['po_number']); ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo match($po['status']) {
                                            'pending' => 'warning',
                                            'approved' => 'info',
                                            'ordered' => 'primary',
                                            'received' => 'success',
                                            'cancelled' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php echo ucfirst($po['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Created By:</th>
                                <td><?php echo htmlspecialchars($po['created_by_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Created At:</th>
                                <td><?php echo date('M d, Y H:i:s', strtotime($po['created_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="150">Total Amount:</th>
                                <td>$<?php echo number_format($po['total_amount'], 2); ?></td>
                            </tr>
                            <tr>
                                <th>Approved By:</th>
                                <td><?php echo $po['approved_by_name'] ? htmlspecialchars($po['approved_by_name']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Notes:</th>
                                <td><?php echo nl2br(htmlspecialchars($po['notes'] ?? '')); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Order Items</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $index => $item): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td class="text-end">$<?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td class="text-end"><?php echo $item['quantity']; ?></td>
                                    <td class="text-end">$<?php echo number_format($item['subtotal'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="6" class="text-end">Total Amount:</th>
                                <th class="text-end">$<?php echo number_format($po['total_amount'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($po['status'] === 'pending'): ?>
        <!-- Action Buttons -->
        <div class="mt-4 text-end">
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <button class="btn btn-success me-2" onclick="updateStatus('approved')">
                <i class="bi bi-check-circle"></i> Approve Order
            </button>
            
            <button class="btn btn-danger" onclick="updateStatus('cancelled')">
                <i class="bi bi-x-circle"></i> Cancel Order
            </button>
            <?php elseif ($_SESSION['role'] === 'manager'): ?>
            <!-- Managers can only view but not approve POs -->
            <div class="alert alert-info" role="alert">
                <i class="bi bi-info-circle"></i> Only administrators can approve purchase orders
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function updateStatus(status) {
    let title, text, icon, confirmButtonText;
    
    switch(status) {
        case 'approved':
            title = 'Approve Purchase Order?';
            text = 'This will mark the purchase order as approved. Continue?';
            icon = 'question';
            confirmButtonText = 'Yes, approve it!';
            break;
        case 'cancelled':
            title = 'Cancel Purchase Order?';
            text = 'This will cancel the purchase order. This action cannot be undone. Continue?';
            icon = 'warning';
            confirmButtonText = 'Yes, cancel it!';
            break;
        default:
            return;
    }

    Swal.fire({
        title: title,
        text: text,
        icon: icon,
        showCancelButton: true,
        confirmButtonColor: status === 'approved' ? '#28a745' : '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: confirmButtonText,
        cancelButtonText: 'No, keep it',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return $.ajax({
                url: 'ajax/update_po_status.php',
                method: 'POST',
                data: {
                    po_id: <?php echo $po_id; ?>,
                    status: status
                }
            }).then(response => {
                if (!response.success) {
                    throw new Error(response.message || 'An error occurred');
                }
                return response;
            }).catch(error => {
                Swal.showValidationMessage(
                    `Request failed: ${error.message || 'Unknown error'}`
                );
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: result.value.message || `Purchase order has been ${status}`,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.reload();
            });
        }
    });
}

function markPOReceived() {
    Swal.fire({
        title: 'Mark as Received?',
        text: 'This will mark the purchase order as received. Please ensure all items have been checked.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, mark as received',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return $.ajax({
                url: 'ajax/mark_po_received.php',
                method: 'POST',
                data: {
                    po_id: <?php echo $po_id; ?>
                }
            }).then(response => {
                if (!response.success) {
                    throw new Error(response.message || 'An error occurred');
                }
                return response;
            }).catch(error => {
                Swal.showValidationMessage(
                    `Request failed: ${error.message || 'Unknown error'}`
                );
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: result.value.message || 'Purchase order has been marked as received',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.reload();
            });
        }
    });
}
</script>

</body>
</html> 