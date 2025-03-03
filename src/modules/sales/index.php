<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/index.php");
    exit();
}

// Check if user has permission to view sales
requirePermission('view_sales');

// Get user permissions for UI rendering
$can_create_sale = hasPermission('create_sale');

// Fetch recent sales
$sql = "SELECT so.*, c.name as customer_name, 
        COUNT(soi.order_item_id) as total_items
        FROM sales_orders so 
        LEFT JOIN customers c ON so.customer_id = c.customer_id
        LEFT JOIN sales_order_items soi ON so.sale_id = soi.sale_id
        GROUP BY so.sale_id
        ORDER BY so.created_at DESC 
        LIMIT 10";
$recent_sales = fetchAll($sql);

// Fetch sales statistics
$stats = [
    'today_sales' => fetchValue("SELECT COUNT(*) FROM sales_orders WHERE DATE(created_at) = CURDATE()"),
    'month_sales' => fetchValue("SELECT COUNT(*) FROM sales_orders WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())"),
    'pending_orders' => fetchValue("SELECT COUNT(*) FROM sales_orders WHERE status IN ('draft', 'confirmed', 'processing')"),
    'unpaid_orders' => fetchValue("SELECT COUNT(*) FROM sales_orders WHERE payment_status != 'paid'")
];

// Calculate total revenue
$revenue = [
    'today' => fetchValue("SELECT COALESCE(SUM(grand_total), 0) FROM sales_orders WHERE DATE(created_at) = CURDATE()"),
    'month' => fetchValue("SELECT COALESCE(SUM(grand_total), 0) FROM sales_orders WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Sales Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Add these new Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    


    
</head>
<body>

<?php include '../../includes/sidebar.php'; ?>
<?php include '../../includes/header.php'; ?>
<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Sales Management</h2>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Today's Sales</h5>
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="mb-0"><?php echo $stats['today_sales']; ?></h3>
                            <h4 class="mb-0">$<?php echo number_format($revenue['today'], 2); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Monthly Sales</h5>
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="mb-0"><?php echo $stats['month_sales']; ?></h3>
                            <h4 class="mb-0">$<?php echo number_format($revenue['month'], 2); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <h5 class="card-title">Pending Orders</h5>
                        <h3 class="mb-0"><?php echo $stats['pending_orders']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title">Unpaid Orders</h5>
                        <h3 class="mb-0"><?php echo $stats['unpaid_orders']; ?></h3>
                    </div>
                </div>
            </div>
        </div>


        <!-- Recent Sales Table -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Recent Sales</h5>
                <table id="recentSalesTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_sales as $sale): ?>
                            <tr>
                                <td>#<?php echo str_pad($sale['sale_id'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($sale['created_at'])); ?></td>
                                <td><?php echo $sale['total_items']; ?></td>
                                <td>$<?php echo number_format($sale['grand_total'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo getStatusBadgeClass($sale['status']); ?>">
                                        <?php echo ucfirst($sale['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo getPaymentStatusBadgeClass($sale['payment_status'] ?? 'unpaid'); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $sale['payment_status'] ?? 'unpaid')); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="../orders/view.php?id=<?php echo $sale['sale_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        
                                        <?php if ($sale['payment_status'] !== 'paid' && hasPermission('process_payments')): ?>
                                        <a href="../orders/payment.php?id=<?php echo $sale['sale_id']; ?>" class="btn btn-sm btn-success">
                                            <i class="bi bi-cash"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($sale['status'] !== 'completed' && $sale['status'] !== 'cancelled' && (hasPermission('manage_sales') || hasPermission('process_payments'))): ?>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="updateOrderStatus(<?php echo $sale['sale_id']; ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission('delete_sales')): ?>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="archiveOrder(<?php echo $sale['sale_id']; ?>)">
                                            <i class="bi bi-archive"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#recentSalesTable').DataTable({
        "order": [[2, "desc"]],
        "pageLength": 10
    });
});

function updateOrderStatus(saleId) {
    Swal.fire({
        title: 'Update Order Status',
        html: `
            <select id="orderStatus" class="form-select">
                <option value="confirmed">Confirmed</option>
                <option value="processing">Processing</option>
                <option value="shipped">Shipped</option>
                <option value="delivered">Delivered</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        `,
        showCancelButton: true,
        confirmButtonText: 'Update',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const status = document.getElementById('orderStatus').value;
            return fetch(`ajax/update_order_status.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `sale_id=${saleId}&status=${status}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(response.statusText);
                }
                return response.json();
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            if (result.value.success) {
                Swal.fire({
                    title: 'Success!',
                    text: result.value.message,
                    icon: 'success'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: result.value.error,
                    icon: 'error'
                });
            }
        }
    });
}

function archiveOrder(saleId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This will archive the order. You can still view it in the archived orders section.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, archive it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`ajax/archive_order.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `sale_id=${saleId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Archived!',
                        text: data.message,
                        icon: 'success'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.error,
                        icon: 'error'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to archive order. Please try again.',
                    icon: 'error'
                });
            });
        }
    });
}

<?php
function getStatusBadgeClass($status) {
    return match($status) {
        'draft' => 'secondary',
        'confirmed' => 'primary',
        'processing' => 'info',
        'shipped' => 'warning',
        'delivered' => 'success',
        'completed' => 'success',
        'cancelled' => 'danger',
        default => 'secondary'
    };
}

function getPaymentStatusBadgeClass($status) {
    return match($status) {
        'paid' => 'success',
        'partially_paid' => 'warning',
        'unpaid' => 'danger',
        default => 'warning'
    };
}
?>
</script>

</body>
</html>