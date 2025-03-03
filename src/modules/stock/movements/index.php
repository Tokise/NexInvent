<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../login/index.php");
    exit();
}

// Check if user has permission to view inventory
requirePermission('view_inventory');

// Get filters from query parameters with defaults
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
$type = isset($_GET['type']) ? $_GET['type'] : null;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Build query
$sql = "SELECT sm.*, p.name as product_name, p.sku, u.username 
        FROM stock_movements sm 
        JOIN products p ON sm.product_id = p.product_id 
        JOIN users u ON sm.user_id = u.user_id 
        WHERE 1=1";
$params = [];

// Add filters to query
if ($product_id) {
    $sql .= " AND sm.product_id = ?";
    $params[] = $product_id;
}

if ($type) {
    $sql .= " AND sm.type = ?";
    $params[] = $type;
}

if ($start_date) {
    $sql .= " AND DATE(sm.created_at) >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $sql .= " AND DATE(sm.created_at) <= ?";
    $params[] = $end_date;
}

$sql .= " ORDER BY sm.created_at DESC";

// Fetch data
$movements = fetchAll($sql, $params);
$products = fetchAll("SELECT product_id, name, sku FROM products ORDER BY name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Stock Movement History</title>
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

<?php include '../../../includes/sidebar.php'; ?>
<?php include '../../../includes/header.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Stock Movement History</h2>
            <a href="../index.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Stock
            </a>
        </div>

        <!-- Filters Card -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <!-- Product Filter -->
                    <div class="col-md-3">
                        <label for="product_id" class="form-label">Product</label>
                        <select class="form-select" id="product_id" name="product_id">
                            <option value="">All Products</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['product_id']; ?>" 
                                        <?php echo $product_id == $product['product_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($product['name'] . ' (' . $product['sku'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Movement Type Filter -->
                    <div class="col-md-2">
                        <label for="type" class="form-label">Movement Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">All Types</option>
                            <option value="initial" <?php echo $type === 'initial' ? 'selected' : ''; ?>>Initial</option>
                            <option value="add" <?php echo $type === 'add' ? 'selected' : ''; ?>>Add</option>
                            <option value="remove" <?php echo $type === 'remove' ? 'selected' : ''; ?>>Remove</option>
                            <option value="sale" <?php echo $type === 'sale' ? 'selected' : ''; ?>>Sale</option>
                            <option value="purchase" <?php echo $type === 'purchase' ? 'selected' : ''; ?>>Purchase</option>
                            <option value="adjustment" <?php echo $type === 'adjustment' ? 'selected' : ''; ?>>Adjustment</option>
                        </select>
                    </div>
                    
                    <!-- Date Range Filters -->
                    <div class="col-md-2">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" 
                               value="<?php echo $end_date; ?>">
                    </div>
                    
                    <!-- Filter Buttons -->
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-filter"></i> Apply Filters
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Movements Table -->
        <div class="card">
            <div class="card-body">
                <table id="movementsTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>User</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movements as $movement): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($movement['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($movement['product_name'] . ' (' . $movement['sku'] . ')'); ?></td>
                                <td><?php echo ucfirst($movement['type']); ?></td>
                                <td class="<?php echo $movement['quantity'] > 0 ? 'movement-add' : 'movement-remove'; ?>">
                                    <?php echo ($movement['quantity'] > 0 ? '+' : '') . $movement['quantity']; ?>
                                </td>
                                <td><?php echo htmlspecialchars($movement['username']); ?></td>
                                <td><?php echo htmlspecialchars($movement['notes']); ?></td>
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
    $('#movementsTable').DataTable({
        "order": [[0, "desc"]],
        "pageLength": 25
    });
});
</script>

</body>
</html>