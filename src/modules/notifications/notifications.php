<?php
require_once __DIR__ . '/../../config/db.php';

// Create a new notification
function createNotification($type, $title, $message, $forRole, $referenceId = null) {
    $data = [
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'for_role' => $forRole,
        'reference_id' => $referenceId,
        'is_read' => false
    ];
    
    return insert('notifications', $data);
}

// Get notifications for a specific role
function getNotifications($role, $limit = 10, $offset = 0) {
    $sql = "SELECT * FROM notifications 
            WHERE for_role = ? 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?";
    
    return fetchAll($sql, [$role, $limit, $offset]);
}

// Get unread notification count
function getUnreadCount($role) {
    $sql = "SELECT COUNT(*) FROM notifications 
            WHERE for_role = ? AND is_read = 0";
    
    return fetchValue($sql, [$role]);
}

// Mark notification as read
function markAsRead($notificationId) {
    $sql = "UPDATE notifications 
            SET is_read = 1 
            WHERE notification_id = ?";
    
    return executeQuery($sql, [$notificationId]);
}

// Mark all notifications as read for a role
function markAllAsRead($role) {
    $sql = "UPDATE notifications 
            SET is_read = 1 
            WHERE for_role = ? AND is_read = 0";
    
    return executeQuery($sql, [$role]);
}

// Delete old notifications (older than 30 days)
function cleanupOldNotifications() {
    $sql = "DELETE FROM notifications 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    
    return executeQuery($sql, []);
}

// Create low stock notification
function createLowStockNotification($productId) {
    $sql = "SELECT name, in_stock_quantity, reorder_level 
            FROM products 
            WHERE product_id = ?";
    $product = fetchOne($sql, [$productId]);
    
    if ($product) {
        $title = "Low Stock Alert";
        $message = "Product '{$product['name']}' is running low on stock. " .
                  "Current quantity: {$product['in_stock_quantity']}, " .
                  "Reorder level: {$product['reorder_level']}";
        
        return createNotification(
            'low_stock',
            $title,
            $message,
            'manager',
            $productId
        );
    }
    return false;
}

// Create new sale notification
function createSaleNotification($saleId) {
    $sql = "SELECT s.sale_id, s.total_amount, u.full_name 
            FROM sales s 
            JOIN users u ON s.cashier_id = u.user_id 
            WHERE s.sale_id = ?";
    $sale = fetchOne($sql, [$saleId]);
    
    if ($sale) {
        $title = "New Sale Completed";
        $message = "A new sale (#{$sale['sale_id']}) has been completed by {$sale['full_name']} " .
                  "for a total of $" . number_format($sale['total_amount'], 2);
        
        return createNotification(
            'new_sale',
            $title,
            $message,
            'manager',
            $saleId
        );
    }
    return false;
}

// Create purchase order notification
function createPurchaseOrderNotification($poId) {
    $sql = "SELECT po.po_id, po.total_amount, u.full_name 
            FROM purchase_orders po 
            JOIN users u ON po.created_by = u.user_id 
            WHERE po.po_id = ?";
    $po = fetchOne($sql, [$poId]);
    
    if ($po) {
        $title = "New Purchase Order";
        $message = "A new purchase order (#{$po['po_id']}) has been created by {$po['full_name']} " .
                  "for a total of $" . number_format($po['total_amount'], 2);
        
        return createNotification(
            'new_purchase_order',
            $title,
            $message,
            'admin',
            $poId
        );
    }
    return false;
} 