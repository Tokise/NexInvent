<?php
// Include necessary functions
require_once __DIR__ . '/../config/db.php';

// Get current user ID and role
$currentUserId = $_SESSION['user_id'] ?? 0;
$userRole = $_SESSION['role'] ?? '';

// Query for notifications - use for_role instead of user_id
$conn = getDBConnection();
$query = "SELECT * FROM notifications WHERE (for_role = ? OR for_role = 'admin') AND is_read = 0 ORDER BY created_at DESC LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute([$userRole]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count unread notifications
$unreadCount = count($notifications);
?>

<div class="dropdown">
    <button class="btn btn-ghost-secondary btn-icon rounded-circle position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-bell"></i>
        <?php if ($unreadCount > 0): ?>
        <span class="notification-badge"><?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?></span>
        <?php endif; ?>
    </button>
    
    <div class="dropdown-menu dropdown-menu-end p-0 shadow-sm border-0" style="width: 300px; max-height: 400px; overflow-y: auto; border-radius: 10px;" aria-labelledby="notificationDropdown">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">Notifications</h6>
            <?php if ($unreadCount > 0): ?>
            <a href="javascript:void(0)" class="text-decoration-none text-sm mark-all-read">Mark all as read</a>
            <?php endif; ?>
        </div>
        
        <div class="notifications-wrapper">
            <?php if (empty($notifications)): ?>
            <div class="text-center py-4">
                <i class="bi bi-bell-slash text-muted opacity-50" style="font-size: 2rem;"></i>
                <p class="mt-2 text-muted small">No new notifications</p>
            </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                <div class="notification-item" data-notification-id="<?php echo $notification['notification_id']; ?>">
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
                    <div class="notification-icon bg-light-<?php echo $colorClass; ?> text-<?php echo $colorClass; ?>">
                        <i class="bi <?php echo $iconClass; ?>"></i>
                    </div>
                    <div class="notification-content">
                        <p class="notification-title"><?php echo htmlspecialchars($notification['title'] ?? 'Notification'); ?></p>
                        <p class="notification-text"><?php echo htmlspecialchars($notification['message']); ?></p>
                        <div class="notification-meta">
                            <span class="notification-time"><?php echo timeAgo($notification['created_at']); ?></span>
                            <div class="notification-actions">
                                <button class="btn btn-sm btn-link p-0 mark-read" data-id="<?php echo $notification['notification_id']; ?>">
                                    <i class="bi bi-check2-all"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($notifications)): ?>
        <div class="p-2 text-center border-top">
            <a href="/NexInvent/src/modules/notifications/index.php" class="text-decoration-none">View all notifications</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mark single notification as read
    const markReadButtons = document.querySelectorAll('.mark-read');
    markReadButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
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
                    // Remove notification from list
                    notificationItem.style.opacity = '0.5';
                    setTimeout(() => {
                        notificationItem.remove();
                        
                        // Update badge count
                        const badge = document.querySelector('.notification-badge');
                        if (badge) {
                            let count = parseInt(badge.textContent);
                            count--;
                            
                            if (count <= 0) {
                                badge.remove();
                            } else {
                                badge.textContent = count > 9 ? '9+' : count;
                            }
                        }
                        
                        // Show "no notifications" message if empty
                        const notificationItems = document.querySelectorAll('.notification-item');
                        if (notificationItems.length === 0) {
                            const notificationsWrapper = document.querySelector('.notifications-wrapper');
                            notificationsWrapper.innerHTML = `
                                <div class="text-center py-4">
                                    <i class="bi bi-bell-slash text-muted opacity-50" style="font-size: 2rem;"></i>
                                    <p class="mt-2 text-muted small">No new notifications</p>
                                </div>
                            `;
                            
                            // Remove "mark all as read" button
                            const markAllButton = document.querySelector('.mark-all-read');
                            if (markAllButton) markAllButton.remove();
                            
                            // Remove "view all" footer
                            const viewAllFooter = document.querySelector('.border-top');
                            if (viewAllFooter) viewAllFooter.remove();
                        }
                    }, 300);
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
            });
        });
    });
    
    // Mark all notifications as read
    const markAllButton = document.querySelector('.mark-all-read');
    if (markAllButton) {
        markAllButton.addEventListener('click', function(e) {
            e.preventDefault();
            
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
                    // Update UI to reflect all notifications are read
                    const notificationsWrapper = document.querySelector('.notifications-wrapper');
                    notificationsWrapper.innerHTML = `
                        <div class="text-center py-4">
                            <i class="bi bi-bell-slash text-muted opacity-50" style="font-size: 2rem;"></i>
                            <p class="mt-2 text-muted small">No new notifications</p>
                        </div>
                    `;
                    
                    // Remove badge
                    const badge = document.querySelector('.notification-badge');
                    if (badge) badge.remove();
                    
                    // Remove "mark all as read" button
                    this.remove();
                    
                    // Remove "view all" footer
                    const viewAllFooter = document.querySelector('.border-top');
                    if (viewAllFooter) viewAllFooter.remove();
                }
            })
            .catch(error => {
                console.error('Error marking all notifications as read:', error);
            });
        });
    }
});

// Helper function to format time ago
function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);
    
    if (diffSecs < 60) {
        return 'just now';
    } else if (diffMins < 60) {
        return `${diffMins}m ago`;
    } else if (diffHours < 24) {
        return `${diffHours}h ago`;
    } else if (diffDays < 7) {
        return `${diffDays}d ago`;
    } else {
        const options = { month: 'short', day: 'numeric' };
        return date.toLocaleDateString(undefined, options);
    }
}
</script> 