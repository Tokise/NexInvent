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
                <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#generateReportModal">
                    <i class="bi bi-file-earmark-pdf"></i> Generate Report
                </button>
            </div>
        </div>

        <!-- Generate Report Modal -->
        <div class="modal fade" id="generateReportModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Generate Report</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="generateReportForm" method="POST" action="ajax/generate_report.php" target="_blank">
                            <div class="mb-3">
                                <label for="report_type" class="form-label">Report Type</label>
                                <select class="form-select" id="report_type" name="report_type" required>
                                    <option value="">Select Report Type</option>
                                    <option value="sales_daily">Daily Sales Report</option>
                                    <option value="sales_monthly">Monthly Sales Report</option>
                                    <option value="inventory">Inventory Report</option>
                                    <option value="attendance">Attendance Report</option>
                                    <option value="stock_movement">Stock Movement Report</option>
                                    <option value="purchase_orders">Purchase Orders Report</option>
                                    <option value="employees">Employees Report</option>
                                    <option value="categories">Categories Report</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                            <input type="hidden" name="format" id="reportFormat" value="pdf">
                        </form>
                        <div class="mb-3">
                            <canvas id="reportChart" height="120"></canvas>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="generateReport()">Generate PDF</button>
                    </div>
                </div>
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
                            <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" onclick="showReportModal('sales_daily')">
                                Daily Sales Report
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" onclick="showReportModal('sales_monthly')">
                                Monthly Sales Summary
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
                            <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" onclick="showReportModal('inventory')">
                                Current Stock Levels
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" onclick="showReportModal('low_stock')">
                                Low Stock Alert
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" onclick="showReportModal('stock_movement')">
                                Stock Movement
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Reports -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-check me-2"></i>Attendance Reports
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" onclick="showReportModal('attendance')">
                                Daily Attendance
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" onclick="showReportModal('attendance_summary')">
                                Monthly Summary
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" onclick="showReportModal('attendance_performance')">
                                Employee Performance
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
function showReportModal(type) {
    $('#report_type').val(type);
    $('#generateReportModal').modal('show');
}

function generateReport() {
    const form = document.getElementById('generateReportForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    // Set format to PDF
    document.getElementById('reportFormat').value = 'pdf';
    const formData = new FormData(form);
    fetch('ajax/generate_report.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.indexOf('application/json') !== -1) {
            return response.json().then(data => { throw new Error(data.error || 'Unknown error'); });
        }
        return response.blob();
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = $('#report_type').val() + '_report.pdf';
        document.body.appendChild(a);
        a.click();
        a.remove();
        $('#generateReportModal').modal('hide');
    })
    .catch(error => {
        alert('Failed to generate report: ' + error.message);
    });
}

// Set default dates
$(document).ready(function() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    $('#start_date').val(firstDay.toISOString().split('T')[0]);
    $('#end_date').val(today.toISOString().split('T')[0]);
});

let chartInstance = null;

function updateChart() {
    const reportType = $('#report_type').val();
    const startDate = $('#start_date').val();
    const endDate = $('#end_date').val();
    if (!reportType || !startDate || !endDate) return;
    fetch('ajax/generate_report.php', {
        method: 'POST',
        body: new URLSearchParams({
            report_type: reportType,
            start_date: startDate,
            end_date: endDate,
            format: 'json' // We'll handle this in PHP to return JSON data only
        })
    })
    .then(res => res.json())
    .then(res => {
        if (!res.success && res.error) {
            if (chartInstance) chartInstance.destroy();
            return;
        }
        const data = res.data || res;
        let chartData = { labels: [], datasets: [] };
        let chartType = 'bar';
        // Build chart data based on report type
        switch (reportType) {
            case 'sales_daily':
                chartData.labels = data.map(row => row.date);
                chartData.datasets = [{ label: 'Total Sales', data: data.map(row => row.total_sales), backgroundColor: '#4f46e5' }];
                chartType = 'bar';
                break;
            case 'sales_monthly':
                chartData.labels = data.map(row => row.month);
                chartData.datasets = [{ label: 'Total Sales', data: data.map(row => row.total_sales), backgroundColor: '#4f46e5' }];
                chartType = 'bar';
                break;
            case 'inventory':
                chartData.labels = data.map(row => row.name);
                chartData.datasets = [{ label: 'Stock', data: data.map(row => row.current_stock), backgroundColor: '#22c55e' }];
                chartType = 'bar';
                break;
            case 'attendance':
                chartData.labels = data.map(row => row.employee_name);
                chartData.datasets = [
                    { label: 'Present', data: data.map(row => row.present_days), backgroundColor: '#22c55e' },
                    { label: 'Absent', data: data.map(row => row.absent_days), backgroundColor: '#ef4444' },
                    { label: 'Late', data: data.map(row => row.late_days), backgroundColor: '#f59e0b' }
                ];
                chartType = 'bar';
                break;
            case 'stock_movement':
                chartData.labels = data.map(row => row.date);
                chartData.datasets = [{ label: 'Quantity', data: data.map(row => row.quantity), backgroundColor: '#3b82f6' }];
                chartType = 'line';
                break;
            case 'purchase_orders':
                chartData.labels = data.map(row => row.po_number);
                chartData.datasets = [{ label: 'Total Amount', data: data.map(row => row.total_amount), backgroundColor: '#4f46e5' }];
                chartType = 'bar';
                break;
            case 'employees':
                chartData.labels = data.map(row => row.employee_name);
                chartData.datasets = [{ label: 'Employees', data: data.map(() => 1), backgroundColor: '#4f46e5' }];
                chartType = 'bar';
                break;
            case 'categories':
                chartData.labels = data.map(row => row.name);
                chartData.datasets = [{ label: 'Categories', data: data.map(() => 1), backgroundColor: '#22c55e' }];
                chartType = 'bar';
                break;
        }
        const ctx = document.getElementById('reportChart').getContext('2d');
        if (chartInstance) chartInstance.destroy();
        chartInstance = new Chart(ctx, {
            type: chartType,
            data: chartData,
            options: { responsive: true, plugins: { legend: { display: true } } }
        });
    });
}

$('#report_type, #start_date, #end_date').on('change', updateChart);
$('#generateReportModal').on('shown.bs.modal', updateChart);
</script>

</body>
</html>