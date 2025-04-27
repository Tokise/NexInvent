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

// Check if user has permission to view sales
requirePermission('view_sales');

// Get date filters - default to show all sales if no dates specified
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '2000-01-01';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Fetch sales summary
$sql = "SELECT 
            COUNT(*) as total_transactions,
            SUM(total_amount) as total_revenue,
            SUM(tax_amount) as total_tax,
            AVG(total_amount) as average_sale
        FROM sales 
        WHERE DATE(created_at) BETWEEN ? AND ?";
$summary = fetchRow($sql, [$start_date, $end_date]);

// Fetch recent sales with cashier details - order by most recent first
$sql = "SELECT s.*, u.username as cashier_name
        FROM sales s
        LEFT JOIN users u ON s.cashier_id = u.user_id
        ORDER BY s.created_at DESC";
$sales = fetchAll($sql, []);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Sales History</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .product-img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .sale-items {
            max-height: 300px;
            overflow-y: auto;
        }
        .status-badge {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }
        .status-completed { background-color: #198754; }
        .status-pending { background-color: #ffc107; }
        .status-cancelled { background-color: #dc3545; }
    </style>
</head>
<body>

<?php include '../../includes/header.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Sales History</h2>
            <a href="../pos/index.php" class="btn btn-primary">
                <i class="bi bi-cart-plus"></i> New Sale
            </a>
        </div>

        <!-- Date Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" 
                               value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-filter"></i> Apply Filter
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sales Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Sales</h5>
                        <h3 class="mb-0"><?php echo (int)($summary['total_transactions'] ?? 0); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Revenue</h5>
                        <h3 class="mb-0">$<?php echo number_format((float)($summary['total_revenue'] ?? 0), 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Tax</h5>
                        <h3 class="mb-0">$<?php echo number_format((float)($summary['total_tax'] ?? 0), 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Average Sale</h5>
                        <h3 class="mb-0">$<?php echo number_format((float)($summary['average_sale'] ?? 0), 2); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Table -->
        <div class="card">
            <div class="card-body">
                <table id="salesTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Transaction #</th>
                            <th>Date & Time</th>
                            <th>Items</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>Cashier</th>
                            <th>Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sale['transaction_number']); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($sale['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="viewSaleItems(<?php echo $sale['sale_id']; ?>)">
                                        View Items
                                    </button>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo ucfirst($sale['payment_method']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="status-badge status-<?php echo $sale['payment_status']; ?>"></span>
                                        <?php echo ucfirst($sale['payment_status']); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($sale['cashier_name']); ?></td>
                                <td class="text-end">$<?php echo number_format((float)($sale['total_amount'] ?? 0), 2); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="printReceipt(<?php echo $sale['sale_id']; ?>)">
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

<!-- Sale Items Modal -->
<div class="modal fade" id="saleItemsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sale Items</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="saleItemsList" class="sale-items">
                    <!-- Sale items will be loaded here -->
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

<script>
$(document).ready(function() {
    $('#salesTable').DataTable({
        "order": [[1, "desc"]],
        "pageLength": 25
    });
});

function viewSaleItems(saleId) {
    $.ajax({
        url: 'ajax/get_sale_items.php',
        method: 'GET',
        data: { sale_id: saleId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '';
                response.items.forEach(item => {
                    html += `
                        <div class="d-flex align-items-center mb-3">
                            <img src="${item.image_url ? '../../' + item.image_url : '../../assets/images/no-image.svg'}" 
                                 class="product-img me-3" alt="${item.name}">
                            <div class="flex-grow-1">
                                <h6 class="mb-1 text-dark">${item.name}</h6>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">
                                        ${item.quantity} × $${parseFloat(item.unit_price).toFixed(2)}
                                    </span>
                                    <span class="fw-bold">
                                        $${parseFloat(item.subtotal).toFixed(2)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    `;
                });
                $('#saleItemsList').html(html);
                $('#saleItemsModal').modal('show');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.error || 'Failed to load sale items'
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading sale items:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load sale items. Please try again.'
            });
        }
    });
}

function printReceipt(saleId) {
    window.open(`receipt.php?sale_id=${saleId}`, '_blank');
}
</script>

</body>
</html>