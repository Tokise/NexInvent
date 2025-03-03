<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/index.php");
    exit();
}

// Check if user has permission to view reports
requirePermission('view_reports');

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Reports & Analytics</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Add Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4f46e5 !important;
            --primary-dark: #4338ca !important;
            --success-color: #22c55e !important;
            --warning-color: #f59e0b !important;
            --danger-color: #ef4444 !important;
            --info-color: #3b82f6 !important;
        }

        body {
            font-family: 'Inter', sans-serif !important;
            background-color: #f9fafb !important;
            color: #1f2937 !important;
        }

        h1, h2, h3, h4, h5, .card-title {
            font-family: 'Poppins', sans-serif !important;
            font-weight: 600 !important;
            color: #111827 !important;
        }

        .main-content {
            padding: 2rem !important;
        }

        .card {
            border-radius: 1rem !important;
            border: none !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
            transition: transform 0.2s ease-in-out !important;
        }

        .card:hover {
            transform: translateY(-5px) !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
        }

        .card-header {
            border-radius: 1rem 1rem 0 0 !important;
            padding: 1.5rem !important;
            border: none !important;
        }

        .card-header.bg-primary {
            background: linear-gradient(145deg, var(--primary-color), var(--primary-dark)) !important;
        }

        .card-header.bg-success {
            background: linear-gradient(145deg, #22c55e, #16a34a) !important;
        }

        .card-header.bg-info {
            background: linear-gradient(145deg, #3b82f6, #2563eb) !important;
        }

        .card-header.bg-warning {
            background: linear-gradient(145deg, #f59e0b, #d97706) !important;
        }

        .card-header.bg-secondary {
            background: linear-gradient(145deg, #6b7280, #4b5563) !important;
        }

        .card-header.bg-dark {
            background: linear-gradient(145deg, #1f2937, #111827) !important;
        }

        .card-title {
            font-size: 1.1rem !important;
            margin: 0 !important;
            color: white !important;
        }

        .list-group-item {
            padding: 1rem 1.25rem !important;
            border: none !important;
            border-bottom: 1px solid #e5e7eb !important;
            font-size: 0.875rem !important;
            color: #4b5563 !important;
            transition: all 0.2s ease-in-out !important;
        }

        .list-group-item:last-child {
            border-bottom: none !important;
        }

        .list-group-item:hover {
            background-color: #f3f4f6 !important;
            color: var(--primary-color) !important;
            transform: translateX(5px) !important;
        }

        .list-group-item i {
            transition: transform 0.2s ease-in-out !important;
        }

        .list-group-item:hover i {
            transform: translateX(5px) !important;
        }

        .btn {
            font-weight: 500 !important;
            padding: 0.625rem 1.25rem !important;
            border-radius: 0.5rem !important;
            transition: all 0.2s ease-in-out !important;
        }

        .btn-primary {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark) !important;
            border-color: var(--primary-dark) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2) !important;
        }

        .btn-success {
            background-color: var(--success-color) !important;
            border-color: var(--success-color) !important;
        }

        .btn-success:hover {
            background-color: #16a34a !important;
            border-color: #16a34a !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 6px -1px rgba(22, 163, 74, 0.2) !important;
        }

        .btn i {
            margin-right: 0.5rem !important;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem !important;
            }

            .row {
                margin-left: -0.5rem !important;
                margin-right: -0.5rem !important;
            }

            .col-md-6 {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }
        }
    </style>
</head>
<body>

<?php include '../../includes/sidebar.php'; ?>
<?php include '../../includes/header.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Reports & Analytics</h2>
            <div>
                <button type="button" class="btn btn-primary me-2" onclick="generateReport()">
                    <i class="bi bi-file-earmark-pdf"></i> Generate Report
                </button>
                <button type="button" class="btn btn-success" onclick="exportData()">
                    <i class="bi bi-file-earmark-excel"></i> Export Data
                </button>
            </div>
        </div>

        <!-- Reports Grid -->
        <div class="row g-4">
            <!-- Sales Reports -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-graph-up me-2"></i>Sales Reports
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="sales/daily.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Daily Sales Report
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="sales/monthly.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Monthly Sales Summary
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="sales/performance.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Sales Performance
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Reports -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-box-seam me-2"></i>Stocks Reports
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="inventory/stock.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Current Stock Levels
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="inventory/low-stock.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Low Stock Alert
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="inventory/movement.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Stock Movement History
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial Reports -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-cash-stack me-2"></i>Financial Reports
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="financial/revenue.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Revenue Analysis
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="financial/expenses.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Expense Tracking
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="financial/profit.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Profit & Loss
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employee Reports -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-warning text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-people me-2"></i>Employee Reports
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="employees/performance.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Performance Metrics
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="employees/attendance.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Attendance Records
                                <i class="bi bi-chevron-right"></i>
                            </a>
                           
                        </div>
                    </div>
                </div>
            </div>

            <!-- Supplier Reports -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-truck me-2"></i>Supplier Reports
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="suppliers/orders.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Purchase Orders
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="suppliers/performance.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Supplier Performance
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="suppliers/payments.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Payment History
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Reports -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-gear me-2"></i>Custom Reports
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="custom/builder.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Report Builder
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="custom/scheduled.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Scheduled Reports
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="custom/saved.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Saved Reports
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
function generateReport() {
    showLoading('Generating report...');
    // Add AJAX call here
    setTimeout(() => {
        hideLoading();
        showSuccess('Report generated successfully');
    }, 1000);
}

function exportData() {
    showLoading('Preparing data export...');
    // Add AJAX call here
    setTimeout(() => {
        hideLoading();
        showSuccess('Data exported successfully');
    }, 1000);
}
</script>

</body>
</html>