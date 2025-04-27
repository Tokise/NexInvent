<?php
require_once '../../includes/require_auth.php';

// Add aggressive history protection to prevent back button to login page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

require_once '../../config/db.php';
require_once '../../includes/permissions.php';

// Get current user ID and role
$userId = $_SESSION['user_id'] ?? 0;
$userRole = $_SESSION['role'] ?? '';

// Get all notifications for this user based on role
$conn = getDBConnection();

// Set page parameters for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count for pagination
$countQuery = "SELECT COUNT(*) FROM notifications WHERE for_role = ? OR for_role = 'admin'";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute([$userRole]);
$totalNotifications = $countStmt->fetchColumn();
$totalPages = ceil($totalNotifications / $perPage);

// Get notifications with pagination
$query = "SELECT * FROM notifications 
          WHERE for_role = ? OR for_role = 'admin' 
          ORDER BY is_read ASC, created_at DESC 
          LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$stmt->execute([$userRole, $perPage, $offset]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread count
$unreadQuery = "SELECT COUNT(*) FROM notifications WHERE (for_role = ? OR for_role = 'admin') AND is_read = 0";
$unreadStmt = $conn->prepare($unreadQuery);
$unreadStmt->execute([$userRole]);
$unreadCount = $unreadStmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Notifications</title>
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
    <!-- Custom CSS -->
    <link href="/NexInvent/src/css/global.css" rel="stylesheet">
</head>
<body>

<?php include '../../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Notifications</h2>
            
            <div class="d-flex align-items-center">
                <?php if ($unreadCount > 0): ?>
                <button id="markAllReadBtn" class="btn btn-outline-primary me-2">
                    <i class="bi bi-check2-all me-1"></i> Mark all as read
                </button>
                <?php endif; ?>
                
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        Filter
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterDropdown">
                        <li><a class="dropdown-item active" href="?filter=all">All Notifications</a></li>
                        <li><a class="dropdown-item" href="?filter=unread">Unread Only</a></li>
                        <li><a class="dropdown-item" href="?filter=read">Read Only</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="?filter=inventory">Inventory</a></li>
                        <li><a class="dropdown-item" href="?filter=sales">Sales</a></li>
                        <li><a class="dropdown-item" href="?filter=purchases">Purchase Orders</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-bell-slash text-muted opacity-50" style="font-size: 4rem;"></i>
                    <h5 class="mt-3">No notifications found</h5>
                    <p class="text-muted">You don't have any notifications at the moment.</p>
                </div>
                <?php else: ?>
                <div class="notification-list">
                    <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item p-3 border-bottom d-flex <?php echo $notification['is_read'] ? 'bg-light' : ''; ?>" data-notification-id="<?php echo $notification['notification_id']; ?>">
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
                        <div class="notification-icon bg-light-<?php echo $colorClass; ?> text-<?php echo $colorClass; ?> me-3">
                            <i class="bi <?php echo $iconClass; ?>"></i>
                        </div>
                        
                        <div class="notification-content flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <h6 class="notification-title mb-0">
                                    <?php echo htmlspecialchars($notification['title'] ?? 'Notification'); ?>
                                    <?php if (!$notification['is_read']): ?>
                                    <span class="badge bg-primary ms-2">New</span>
                                    <?php endif; ?>
                                </h6>
                                <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></small>
                            </div>
                            
                            <p class="notification-text mb-0"><?php echo htmlspecialchars($notification['message']); ?></p>
                            
                            <div class="notification-actions mt-2">
                                <?php if (!$notification['is_read']): ?>
                                <button class="btn btn-sm btn-outline-secondary mark-read" data-id="<?php echo $notification['notification_id']; ?>">
                                    Mark as read
                                </button>
                                <?php endif; ?>
                                
                                <?php if (strpos($notification['message'], 'stock') !== false): ?>
                                <a href="/NexInvent/src/modules/stock/" class="btn btn-sm btn-outline-primary ms-2">
                                    View Inventory
                                </a>
                                <?php elseif (strpos($notification['message'], 'order') !== false): ?>
                                <a href="/NexInvent/src/modules/purchases/" class="btn btn-sm btn-outline-primary ms-2">
                                    View Orders
                                </a>
                                <?php elseif (strpos($notification['message'], 'sale') !== false): ?>
                                <a href="/NexInvent/src/modules/pos/" class="btn btn-sm btn-outline-primary ms-2">
                                    View Sales
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($totalPages > 1): ?>
                <div class="pagination-container p-3">
                    <nav aria-label="Notifications pagination">
                        <ul class="pagination mb-0 justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mark single notification as read
    const markReadButtons = document.querySelectorAll('.mark-read');
    markReadButtons.forEach(button => {
        button.addEventListener('click', function() {
            const notificationId = this.getAttribute('data-id');
            const notificationItem = this.closest('.notification-item');
            
            // Send AJAX request to mark as read
            fetch('/NexInvent/src/modules/notifications/ajax/mark_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'notification_id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update notification in the list
                    notificationItem.classList.add('bg-light');
                    
                    // Remove new badge and mark as read button
                    const newBadge = notificationItem.querySelector('.badge.bg-primary');
                    if (newBadge) newBadge.remove();
                    
                    this.remove();
                    
                    // Update unread count
                    updateUnreadCount();
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
            });
        });
    });
    
    // Mark all notifications as read
    const markAllButton = document.getElementById('markAllReadBtn');
    if (markAllButton) {
        markAllButton.addEventListener('click', function() {
            // Send AJAX request to mark all as read
            fetch('/NexInvent/src/modules/notifications/ajax/mark_all_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'role=<?php echo $userRole; ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update all notifications in the list
                    document.querySelectorAll('.notification-item:not(.bg-light)').forEach(item => {
                        item.classList.add('bg-light');
                    });
                    
                    // Remove all new badges and mark as read buttons
                    document.querySelectorAll('.badge.bg-primary').forEach(badge => {
                        badge.remove();
                    });
                    
                    document.querySelectorAll('.mark-read').forEach(button => {
                        button.remove();
                    });
                    
                    // Hide the "Mark all as read" button
                    this.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error marking all notifications as read:', error);
            });
        });
    }
    
    // Function to update unread count
    function updateUnreadCount() {
        const unreadBadges = document.querySelectorAll('.badge.bg-primary');
        if (unreadBadges.length === 0) {
            // If no more unread notifications, hide "Mark all as read" button
            const markAllButton = document.getElementById('markAllReadBtn');
            if (markAllButton) {
                markAllButton.style.display = 'none';
            }
        }
    }
});
</script>

</body>
</html> 