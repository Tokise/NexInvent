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

// Get user permissions for UI rendering
$can_view_products = hasPermission('view_products');
$can_view_sales = hasPermission('view_sales');
$can_view_inventory = hasPermission('view_inventory');
$can_view_reports = hasPermission('view_reports');

// Fetch statistics if user has permissions
$totalProducts = $can_view_products ? fetchValue("SELECT COUNT(*) FROM products") : 0;
$totalSales = $can_view_sales ? fetchValue("SELECT COUNT(*) FROM sales") : 0;
$lowStockItems = $can_view_inventory ? fetchValue("SELECT COUNT(*) FROM products WHERE in_stock_quantity <= reorder_level") : 0;

// Get the session info for the history cleanup
$session_id = session_id();
$user_id = $_SESSION['user_id'];

// Add aggressive no-cache headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Dashboard</title>
    <!-- Block browser caching to prevent access after logout -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Modern Charts.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- Chart.js Plugin for gradient backgrounds -->
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-gradient"></script>
    
    <style>
    :root {
        --body-bg: #f9fafb;
        --card-bg: #ffffff;
        --text-color: #333;
        --text-muted: #6c757d;
        --primary-color: #4f46e5;
        --success-color: #10b981;
        --warning-color: #f59e0b;
        --danger-color: #ef4444;
        --info-color: #3b82f6;
        --border-radius: 15px;
        --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }
    
    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--body-bg);
        color: var(--text-color);
    }
    
    h1, h2, h3, h4, h5, h6, .card-title {
        font-family: 'Poppins', sans-serif;
    }
    
    .card {
        background-color: var(--card-bg);
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .card:hover {
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    }
    
    .badge {
        font-weight: 500;
        letter-spacing: 0.3px;
        padding: 0.35em 0.65em;
    }
    
    .text-primary { color: var(--primary-color) !important; }
    .text-success { color: var(--success-color) !important; }
    .text-warning { color: var(--warning-color) !important; }
    .text-danger { color: var(--danger-color) !important; }
    .text-info { color: var(--info-color) !important; }
    
    .bg-primary { background-color: var(--primary-color) !important; }
    .bg-success { background-color: var(--success-color) !important; }
    .bg-warning { background-color: var(--warning-color) !important; }
    .bg-danger { background-color: var(--danger-color) !important; }
    .bg-info { background-color: var(--info-color) !important; }
    
    .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); }
    .btn-success { background-color: var(--success-color); border-color: var(--success-color); }
    .btn-warning { background-color: var(--warning-color); border-color: var(--warning-color); }
    .btn-danger { background-color: var(--danger-color); border-color: var(--danger-color); }
    .btn-info { background-color: var(--info-color); border-color: var(--info-color); }
    
    .btn {
        font-weight: 500;
        letter-spacing: 0.3px;
        padding: 0.475rem 1rem;
        border-radius: 8px;
    }
    
    .main-content {
        padding-top: 1.5rem;
        margin-left: 15rem;
        padding-right: 1rem;
        transition: all 0.3s;
    }
    
    @media (max-width: 991.98px) {
        .main-content {
            margin-left: 0;
        }
    }
    </style>
    
    <!-- Dashboard history navigation handler - dashboard-specific version -->
    <script>
    // MOST AGGRESSIVE HISTORY MANIPULATION FOR DASHBOARD
    // This code does multiple things to ensure back button never goes to login
    (function() {
        // Create session markers to identify our auth state
        var sessionInfo = {
            session_id: '<?php echo $session_id; ?>',
            user_id: <?php echo $user_id; ?>,
            timestamp: Date.now()
        };
        
        // Store session info in sessionStorage to check if we're still in the same session
        try {
            sessionStorage.setItem('nexinvent_auth', JSON.stringify(sessionInfo));
        } catch(e) {}
        
        function clearLoginFromHistory() {
            // First replace the current state to mark this as our base state
            if (history.replaceState) {
                history.replaceState({page: 'dashboard'}, document.title, location.href);
            }
            
            // Then add a new state that we can go back to
            if (history.pushState) {
                history.pushState({page: 'dashboard-current'}, document.title, location.href);
            }
            
            // If back button is pressed, prevent going back
            window.addEventListener('popstate', function(e) {
                // Check if we're going back to our marker
                if (!e.state || e.state.page !== 'dashboard') {
                    // Not our marker, so force forward again
                    history.go(1);
                }
            });
        }
        
        // Clear on page load
        clearLoginFromHistory();
        
        // Also clear when page becomes visible again (e.g., after back button)
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                clearLoginFromHistory();
            }
        });
    })();
    </script>
</head>
<body>

<?php include '../../includes/sidebar.php'; ?>
<?php include 'dashboard_content.php'; ?>


<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Modern Charts Implementation -->
<script>
// Sales Chart
<?php if (hasPermission('view_sales') && !empty($salesChartData)): ?>
const salesCtx = document.getElementById('salesChart').getContext('2d');

const salesGradient = salesCtx.createLinearGradient(0, 0, 0, 400);
salesGradient.addColorStop(0, 'rgba(79, 70, 229, 0.6)');
salesGradient.addColorStop(1, 'rgba(79, 70, 229, 0.1)');

new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($salesChartLabels); ?>,
        datasets: [{
            label: 'Monthly Sales',
            data: <?php echo json_encode($salesChartData); ?>,
            borderColor: '#4f46e5',
            backgroundColor: salesGradient,
            borderWidth: 2,
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#ffffff',
            pointBorderColor: '#4f46e5',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.7)',
                titleFont: {
                    family: 'Inter',
                    size: 13
                },
                bodyFont: {
                    family: 'Inter',
                    size: 12
                },
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    label: function(context) {
                        return '$' + context.raw.toLocaleString();
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)',
                    drawBorder: false
                },
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    },
                    font: {
                        family: 'Inter',
                        size: 11
                    }
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    font: {
                        family: 'Inter',
                        size: 11
                    }
                }
            }
        }
    }
});
<?php endif; ?>

// Categories Chart
<?php if (hasPermission('view_products') && !empty($categoriesData)): ?>
const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
new Chart(categoriesCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($categoriesLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($categoriesData); ?>,
            backgroundColor: [
                'rgba(79, 70, 229, 0.9)',
                'rgba(16, 185, 129, 0.9)',
                'rgba(245, 158, 11, 0.9)',
                'rgba(239, 68, 68, 0.9)',
                'rgba(59, 130, 246, 0.9)',
                'rgba(124, 58, 237, 0.9)'
            ],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '75%',
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    font: {
                        family: 'Inter',
                        size: 12
                    },
                    usePointStyle: true,
                    padding: 15
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.7)',
                titleFont: {
                    family: 'Inter',
                    size: 13
                },
                bodyFont: {
                    family: 'Inter',
                    size: 12
                },
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.raw || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = Math.round((value / total) * 100);
                        return `${label}: ${value} products (${percentage}%)`;
                    }
                }
            }
        }
    }
});
<?php endif; ?>

// Top Selling Products Chart
<?php if (!empty($topSellingLabels) && !empty($topSellingData)): ?>
const topSellingCtx = document.getElementById('topSellingChart').getContext('2d');
new Chart(topSellingCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($topSellingLabels); ?>,
        datasets: [{
            label: 'Units Sold',
            data: <?php echo json_encode($topSellingData); ?>,
            backgroundColor: 'rgba(16, 185, 129, 0.7)',
            borderColor: 'rgba(16, 185, 129, 1)',
            borderWidth: 1,
            borderRadius: 6,
            maxBarThickness: 25
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.7)',
                titleFont: {
                    family: 'Inter',
                    size: 13
                },
                bodyFont: {
                    family: 'Inter',
                    size: 12
                },
                padding: 12,
                cornerRadius: 8
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)',
                    drawBorder: false
                },
                ticks: {
                    font: {
                        family: 'Inter',
                        size: 11
                    }
                }
            },
            y: {
                grid: {
                    display: false
                },
                ticks: {
                    font: {
                        family: 'Inter',
                        size: 11
                    }
                }
            }
        }
    }
});
<?php endif; ?>
</script>

</body>
</html>