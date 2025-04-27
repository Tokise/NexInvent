<?php
require_once '../../includes/permissions.php';

// Fetch statistics based on user permissions
$totalProducts = 0;
$totalSales = 0;
$lowStock = 0;
$monthlyRevenue = 0;
$stockValue = 0;


// Get database connection
$conn = getDBConnection();

// Get total products
if (hasPermission('view_products')) {
    $sql = "SELECT COUNT(*) FROM products";
    $totalProducts = fetchValue($sql);
}

// Get total sales
if (hasPermission('view_sales')) {
    $sql = "SELECT COUNT(*) FROM sales WHERE payment_status = 'completed'";
    $totalSales = fetchValue($sql);
}

// Get monthly revenue
if (hasPermission('view_sales')) {
    $currentMonth = date('Y-m');
    $sql = "SELECT COALESCE(SUM(total_amount), 0) FROM sales 
            WHERE DATE_FORMAT(created_at, '%Y-%m') = ? 
            AND payment_status = 'completed'";
    $monthlyRevenue = fetchValue($sql, [$currentMonth]);
}

// Get low stock items
if (hasPermission('view_inventory')) {
    $sql = "SELECT COUNT(*) FROM products 
            WHERE in_stock_quantity <= reorder_level OR out_stock_quantity <= out_threshold_amount";
    $lowStock = fetchValue($sql);
}

// Get stock value
if (hasPermission('view_inventory')) {
    $sql = "SELECT COALESCE(SUM(in_stock_quantity * unit_price), 0) FROM products";
    $stockValue = fetchValue($sql);
}

// Get pending purchase orders
if (hasPermission('view_purchases')) {
    $sql = "SELECT COUNT(*) FROM purchase_orders WHERE status = 'pending'";
    $pendingOrders = fetchValue($sql);
}

// Get recent activities (last 7)
$recentActivities = [];

// Recent product additions
if (hasPermission('view_products')) {
    $sql = "SELECT 'product' as type, product_id as id, name as title, 
            'New product added' as description, created_at, 
            created_by as user_id, 
            (SELECT full_name FROM users WHERE user_id = products.created_by) as user_name
            FROM products 
            ORDER BY created_at DESC LIMIT 5";
    $recentProducts = fetchAll($sql);
    $recentActivities = array_merge($recentActivities, $recentProducts);
}

// Recent sales
if (hasPermission('view_sales')) {
    $sql = "SELECT 'sale' as type, sale_id as id, 
            CONCAT('Sale #', sale_id) as title,
            CONCAT('Sale completed for $', total_amount) as description, 
            created_at,
            cashier_id as user_id,
            (SELECT full_name FROM users WHERE user_id = sales.cashier_id) as user_name
            FROM sales 
            WHERE payment_status = 'completed'
            ORDER BY created_at DESC LIMIT 5";
    $recentSales = fetchAll($sql);
    $recentActivities = array_merge($recentActivities, $recentSales);
}

// Recent stock movements
if (hasPermission('view_inventory')) {
    $sql = "SELECT 'movement' as type, sm.movement_id as id, 
            CONCAT(p.name, ' stock ', IF(sm.quantity > 0, 'increased', 'decreased')) as title,
            CONCAT(ABS(sm.quantity), ' units ', IF(sm.quantity > 0, 'added to', 'removed from'), ' inventory') as description,
            sm.created_at,
            sm.user_id as user_id,
            (SELECT full_name FROM users WHERE user_id = sm.user_id) as user_name
            FROM stock_movements sm
            JOIN products p ON sm.product_id = p.product_id
            ORDER BY sm.created_at DESC LIMIT 5";
    $recentMovements = fetchAll($sql);
    $recentActivities = array_merge($recentActivities, $recentMovements);
}

// Recent employee additions
if (hasPermission('view_employees')) {
    $sql = "SELECT 'employee' as type, u.user_id as id, 
            u.full_name as title, 
            CONCAT('New ', u.role, ' added') as description, 
            u.created_at,
            IFNULL(u.created_by, 0) as user_id,
            IFNULL((SELECT full_name FROM users WHERE user_id = u.created_by), 'System') as user_name
            FROM users u
            ORDER BY u.created_at DESC LIMIT 5";
    $recentEmployees = fetchAll($sql);
    $recentActivities = array_merge($recentActivities, $recentEmployees);
}

// Sort all activities by date (newest first)
usort($recentActivities, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Limit to 7 most recent activities
$recentActivities = array_slice($recentActivities, 0, 7);

// Get sales data for chart (last 6 months)
$salesChartData = [];
$salesChartLabels = [];

if (hasPermission('view_sales')) {
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthName = date('M', strtotime("-$i months"));
        
        $sql = "SELECT COALESCE(SUM(total_amount), 0) FROM sales 
                WHERE DATE_FORMAT(created_at, '%Y-%m') = ? 
                AND payment_status = 'completed'";
        $monthlySales = fetchValue($sql, [$month]);
        
        $salesChartData[] = $monthlySales;
        $salesChartLabels[] = $monthName;
    }
}

// Get product categories data for pie chart
$categoriesData = [];
$categoriesLabels = [];

if (hasPermission('view_products')) {
    $sql = "SELECT c.name, COUNT(p.product_id) as count
            FROM categories c
            LEFT JOIN products p ON c.category_id = p.category_id
            GROUP BY c.category_id
            ORDER BY count DESC
            LIMIT 5";
    $categories = fetchAll($sql);
    
    foreach ($categories as $category) {
        $categoriesLabels[] = $category['name'];
        $categoriesData[] = $category['count'];
    }
}

// Get top selling products data
$topSellingProducts = [];
$topSellingLabels = [];
$topSellingData = [];

if (hasPermission('view_sales')) {
    $sql = "SELECT p.name, SUM(si.quantity) as total_sold
            FROM sale_items si
            JOIN products p ON si.product_id = p.product_id
            JOIN sales s ON si.sale_id = s.sale_id
            WHERE s.payment_status = 'completed'
            GROUP BY si.product_id
            ORDER BY total_sold DESC
            LIMIT 5";
    $topSellingProducts = fetchAll($sql);
    
    foreach ($topSellingProducts as $product) {
        $topSellingLabels[] = $product['name'];
        $topSellingData[] = $product['total_sold'];
    }
}

// Get recently added products
$recentlyAddedProducts = [];
if (hasPermission('view_products')) {
    $sql = "SELECT p.*, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            ORDER BY p.created_at DESC
            LIMIT 5";
    $recentlyAddedProducts = fetchAll($sql);
}
?>

<link href="/NexInvent/src/css/global.css" rel="stylesheet">

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Dashboard</h2>
            <div class="user-info">
                <span class="me-2"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span>
                <span class="badge bg-primary"><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></span>
            </div>
        </div>

        <!-- Statistics Cards - Consistent Card Design -->
        <div class="row g-4 mb-4">
            <?php if (hasPermission('view_products')): ?>
            <div class="col-md-3 col-sm-6">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Products</h5>
                        <h3 class="mb-0"><?php echo number_format($totalProducts); ?></h3>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (hasPermission('view_sales')): ?>
            <div class="col-md-3 col-sm-6">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Monthly Revenue</h5>
                        <h3 class="mb-0">$<?php echo number_format($monthlyRevenue, 2); ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Sales</h5>
                        <h3 class="mb-0"><?php echo number_format($totalSales); ?></h3>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (hasPermission('view_inventory')): ?>
            <div class="col-md-3 col-sm-6">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title">Low Stock Items</h5>
                        <h3 class="mb-0"><?php echo number_format($lowStock); ?></h3>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Charts and Notifications Row -->
        <div class="row g-4 mb-4">
            <?php if (hasPermission('view_sales')): ?>
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent border-0">
                        <h5 class="card-title m-0">Sales Overview</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="salesChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notifications Card -->
            <div class="col-lg-5">
                <div class="card card-dashboard border-0 shadow-sm h-100">
                    <div class="card-body p-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px; min-width: 35px;">
                                    <i class="bi bi-bell" style="font-size: 0.9rem;"></i>
                                </div>
                                <h6 class="card-title mb-0" style="font-size: 0.9rem;">Recent Notifications</h6>
                            </div>
                            <a href="/NexInvent/src/modules/notifications/index.php" class="text-decoration-none small">View all</a>
                        </div>
                        
                        <?php
                        // Get notifications
                        $conn = getDBConnection();
                        $userRole = $_SESSION['role'] ?? '';
                        $query = "SELECT * FROM notifications WHERE (for_role = ? OR for_role = 'admin') ORDER BY created_at DESC LIMIT 3";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$userRole]);
                        $dashboardNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (empty($dashboardNotifications)): 
                        ?>
                        <div class="text-center py-2">
                            <i class="bi bi-bell-slash text-muted opacity-50" style="font-size: 1.2rem;"></i>
                            <p class="mt-1 text-muted small" style="font-size: 0.7rem;">No notifications found</p>
                        </div>
                        <?php else: ?>
                        <div class="notifications-list">
                            <?php foreach ($dashboardNotifications as $notification): ?>
                            <div class="notification-item d-flex p-3 border-bottom">
                                <?php
                                // Determine icon and color based on notification type
                                $iconClass = 'bi-info-circle';
                                $colorClass = 'primary';
                                
                                if (strpos($notification['message'], 'stock') !== false) {
                                    $iconClass = 'bi-box';
                                    $colorClass = 'warning';
                                } elseif (strpos($notification['message'], 'order') !== false) {
                                    $iconClass = 'bi-cart';
                                    $colorClass = 'info';
                                } elseif (strpos($notification['message'], 'sale') !== false) {
                                    $iconClass = 'bi-cash';
                                    $colorClass = 'success';
                                } elseif (strpos($notification['message'], 'error') !== false || strpos($notification['message'], 'failed') !== false) {
                                    $iconClass = 'bi-exclamation-circle';
                                    $colorClass = 'danger';
                                }
                                ?>
                                <div class="me-2">
                                    <div class="notification-icon rounded-circle bg-light-<?php echo $colorClass; ?> text-<?php echo $colorClass; ?> d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                        <i class="bi <?php echo $iconClass; ?>"></i>
                                    </div>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 fw-semibold small text-truncate" style="font-size: 0.8rem;"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                        <small class="text-muted ms-1" style="font-size: 0.7rem; white-space: nowrap;"><?php echo timeAgo($notification['created_at']); ?></small>
                                    </div>
                                    <p class="mb-0 small text-truncate" style="font-size: 0.75rem;"><?php echo htmlspecialchars($notification['message']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Recent Activities Row -->
        <div class="row g-4">
            <?php if (hasPermission('view_sales')): ?>
            <div class="col-lg-7">
                <div class="row g-4">
                    <?php if (hasPermission('view_products')): ?>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-transparent border-0">
                                <h5 class="card-title m-0">Product Categories</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="categoriesChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($topSellingData)): ?>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-transparent border-0">
                                <h5 class="card-title m-0">Top Selling Products</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="topSellingChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title m-0">Recent Activity</h5>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary rounded-pill dropdown-toggle" type="button" id="activityFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-filter me-1"></i> <span id="currentFilter">All</span>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="activityFilterDropdown">
                                <li><a class="dropdown-item active" href="#" data-filter="all">All Users</a></li>
                                <li><a class="dropdown-item" href="#" data-filter="mine">My Activities</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentActivities)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-x text-muted" style="font-size: 2rem;"></i>
                                <p class="mt-2 text-muted">No recent activities found</p>
                            </div>
                        <?php else: ?>
                            <div class="activity-timeline p-3">
                                <?php 
                                // Make a copy of the activities array for filtering
                                $filteredActivities = $recentActivities;
                                
                                // Group activities by date
                                $groupedActivities = [];
                                foreach ($filteredActivities as $activity) {
                                    $date = date('Y-m-d', strtotime($activity['created_at']));
                                    if (!isset($groupedActivities[$date])) {
                                        $groupedActivities[$date] = [];
                                    }
                                    $groupedActivities[$date][] = $activity;
                                }
                                
                                // Display activities grouped by date
                                foreach ($groupedActivities as $date => $activities):
                                    // Determine date label
                                    $dateLabel = 'Today';
                                    if ($date == date('Y-m-d', strtotime('-1 day'))) {
                                        $dateLabel = 'Yesterday';
                                    } elseif ($date != date('Y-m-d')) {
                                        $dateLabel = date('F j, Y', strtotime($date));
                                    }
                                ?>
                                    <div class="activity-date mb-2">
                                        <span class="small text-muted"><?php echo $dateLabel; ?></span>
                                    </div>
                                    
                                    <?php foreach ($activities as $activity): ?>
                                        <div class="activity-item mb-3" 
                                             data-user-id="<?php echo $activity['user_id']; ?>" 
                                             data-current-user="<?php echo $_SESSION['user_id']; ?>">
                                            <div class="d-flex">
                                                <?php
                                                // Determine icon and color based on activity type
                                                $iconClass = 'bi-box';
                                                $colorClass = 'primary';
                                                
                                                if ($activity['type'] === 'product') {
                                                    $iconClass = 'bi-box';
                                                    $colorClass = 'primary';
                                                } elseif ($activity['type'] === 'sale') {
                                                    $iconClass = 'bi-cart';
                                                    $colorClass = 'success';
                                                } elseif ($activity['type'] === 'movement') {
                                                    $iconClass = 'bi-arrow-left-right';
                                                    $colorClass = 'warning';
                                                } elseif ($activity['type'] === 'employee') {
                                                    $iconClass = 'bi-person';
                                                    $colorClass = 'info';
                                                }
                                                ?>
                                                <div class="activity-icon rounded-circle bg-light-<?php echo $colorClass; ?> text-<?php echo $colorClass; ?> d-flex align-items-center justify-content-center me-3">
                                                    <i class="bi <?php echo $iconClass; ?> small"></i>
                                                </div>
                                                <div class="activity-content flex-grow-1">
                                                    <div class="d-flex justify-content-between">
                                                        <p class="mb-0 small"><?php echo htmlspecialchars($activity['description']); ?></p>
                                                        <small class="text-muted"><?php echo date('h:i A', strtotime($activity['created_at'])); ?></small>
                                                    </div>
                                                    <small class="text-muted">by <?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (hasPermission('view_products') && !empty($recentlyAddedProducts)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent border-0">
                        <h5 class="card-title m-0">Recently Added Products</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentlyAddedProducts as $product): ?>
                            <div class="list-group-item border-0 border-bottom py-3">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1 fw-semibold"><?php echo htmlspecialchars($product['name']); ?></h6>
                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                </div>
                                <p class="mb-0 small">
                                    <span class="badge bg-light-secondary text-secondary me-2">SKU: <?php echo htmlspecialchars($product['sku']); ?></span>
                                    <span class="badge bg-light-success text-success me-2">$<?php echo number_format($product['unit_price'], 2); ?></span>
                                    <span class="badge bg-light-info text-info">Stock: <?php echo $product['in_stock_quantity']; ?></span>
                                </p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Minimal UI Styles */
.card-stats {
    transition: transform 0.2s;
    border-radius: 10px;
}
.card-stats:hover {
    transform: translateY(-5px);
}
.stat-icon {
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}
.bg-light-primary {
    background-color: rgba(13, 110, 253, 0.1);
}
.bg-light-success {
    background-color: rgba(25, 135, 84, 0.1);
}
.bg-light-info {
    background-color: rgba(13, 202, 240, 0.1);
}
.bg-light-warning {
    background-color: rgba(255, 193, 7, 0.1);
}
.bg-light-danger {
    background-color: rgba(220, 53, 69, 0.1);
}
.bg-light-secondary {
    background-color: rgba(108, 117, 125, 0.1);
}
.activity-icon {
    width: 32px;
    height: 32px;
    min-width: 32px;
}
.card {
    border-radius: 10px;
    overflow: hidden;
}
.card-header {
    padding: 1rem 1.5rem;
}
.list-group-item:last-child {
    border-bottom: 0 !important;
}
</style>

<?php
// Helper function to format time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 172800) {
        return 'yesterday';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2419200) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Activity Filter
    const activityItems = document.querySelectorAll('.activity-item');
    const filterDropdownItems = document.querySelectorAll('.dropdown-item[data-filter]');
    const currentFilterText = document.getElementById('currentFilter');
    
    // Initialize date headers visibility check
    function updateDateHeadersVisibility() {
        const dateHeaders = document.querySelectorAll('.activity-date');
        dateHeaders.forEach(header => {
            const nextElement = header.nextElementSibling;
            let hasVisibleActivity = false;
            
            // Check if any following activity items (until next date header) are visible
            let current = nextElement;
            while (current && !current.classList.contains('activity-date')) {
                if (current.classList.contains('activity-item') && 
                    window.getComputedStyle(current).display !== 'none') {
                    hasVisibleActivity = true;
                    break;
                }
                current = current.nextElementSibling;
            }
            
            // Show/hide date header based on visibility of its activities
            header.style.display = hasVisibleActivity ? '' : 'none';
        });
        
        // Check if any activities are visible
        let hasAnyVisible = false;
        activityItems.forEach(item => {
            if (window.getComputedStyle(item).display !== 'none') {
                hasAnyVisible = true;
            }
        });
        
        // Show "no activities" message if none are visible
        const activityTimeline = document.querySelector('.activity-timeline');
        if (activityTimeline) {
            if (!hasAnyVisible) {
                // If no activities visible, show message
                let noActivitiesMsg = document.querySelector('.no-activities-msg');
                if (!noActivitiesMsg) {
                    noActivitiesMsg = document.createElement('div');
                    noActivitiesMsg.className = 'no-activities-msg text-center py-4';
                    noActivitiesMsg.innerHTML = `
                        <i class="bi bi-calendar-x text-muted" style="font-size: 2rem;"></i>
                        <p class="mt-2 text-muted">No activities found for this filter</p>
                    `;
                    activityTimeline.appendChild(noActivitiesMsg);
                }
            } else {
                // If activities are visible, remove message if exists
                const noActivitiesMsg = document.querySelector('.no-activities-msg');
                if (noActivitiesMsg) {
                    noActivitiesMsg.remove();
                }
            }
        }
    }
    
    // Filter activity items
    filterDropdownItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Update active state in dropdown
            filterDropdownItems.forEach(i => i.classList.remove('active'));
            this.classList.add('active');
            
            // Update filter text
            currentFilterText.textContent = this.textContent;
            
            const filter = this.getAttribute('data-filter');
            const currentUserId = <?php echo $_SESSION['user_id'] ?? 0; ?>;
            
            // Apply filter
            activityItems.forEach(activityItem => {
                const itemUserId = parseInt(activityItem.getAttribute('data-user-id'), 10);
                
                if (filter === 'all' || (filter === 'mine' && itemUserId === currentUserId)) {
                    activityItem.style.display = '';
                } else {
                    activityItem.style.display = 'none';
                }
            });
            
            // Update date headers visibility
            updateDateHeadersVisibility();
        });
    });
});
</script>