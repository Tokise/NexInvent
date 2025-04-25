<?php
require_once __DIR__ . '/../../config/db.php';

// Update dashboard statistics
function updateDashboardStats() {
    $today = date('Y-m-d');
    
    // Calculate statistics
    $totalSales = getTotalSalesCount();
    $monthlyRevenue = getMonthlyRevenue();
    
    // Check if stats already exist for today
    $sql = "SELECT stat_id FROM dashboard_stats WHERE stat_date = ?";
    $existing = fetchOne($sql, [$today]);
    
    $data = [
        'total_sales' => $totalSales,
        'monthly_revenue' => $monthlyRevenue
    ];
    
    if ($existing) {
        // Update existing stats
        update('dashboard_stats', $data, 'stat_id = ?', [$existing['stat_id']]);
    } else {
        // Create new stats
        $data['stat_date'] = $today;
        insert('dashboard_stats', $data);
    }
}

// Get total sales count for today
function getTotalSalesCount() {
    $sql = "SELECT COUNT(*) FROM sales 
            WHERE DATE(created_at) = CURDATE() 
            AND payment_status = 'completed'";
    return fetchValue($sql);
}

// Get monthly revenue
function getMonthlyRevenue() {
    $sql = "SELECT COALESCE(SUM(total_amount), 0) FROM sales 
            WHERE MONTH(created_at) = MONTH(CURRENT_DATE) 
            AND YEAR(created_at) = YEAR(CURRENT_DATE)
            AND payment_status = 'completed'";
    return fetchValue($sql);
}

// Get top selling products
function getTopSellingProducts($limit = 5) {
    $sql = "SELECT p.product_id, p.name, p.sku,
                   COUNT(si.sale_item_id) as total_sales,
                   SUM(si.quantity) as total_quantity
            FROM products p
            LEFT JOIN sale_items si ON p.product_id = si.product_id
            LEFT JOIN sales s ON si.sale_id = s.sale_id
            WHERE s.payment_status = 'completed'
            AND s.created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
            GROUP BY p.product_id
            ORDER BY total_quantity DESC
            LIMIT ?";
    return fetchAll($sql, [$limit]);
}

// Get low stock products
function getLowStockProducts() {
    $sql = "SELECT product_id, name, in_stock_quantity, reorder_level
            FROM products
            WHERE in_stock_quantity <= reorder_level
            ORDER BY (in_stock_quantity / reorder_level) ASC";
    return fetchAll($sql);
}

// Get sales by category
function getSalesByCategory($days = 30) {
    $sql = "SELECT c.name as category_name,
                   COUNT(DISTINCT s.sale_id) as total_sales,
                   SUM(si.quantity) as total_quantity,
                   SUM(si.subtotal) as total_revenue
            FROM categories c
            LEFT JOIN products p ON c.category_id = p.category_id
            LEFT JOIN sale_items si ON p.product_id = si.product_id
            LEFT JOIN sales s ON si.sale_id = s.sale_id
            WHERE s.payment_status = 'completed'
            AND s.created_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
            GROUP BY c.category_id
            ORDER BY total_revenue DESC";
    return fetchAll($sql, [$days]);
}

// Get employee performance stats
function getEmployeePerformance($days = 30) {
    $sql = "SELECT u.user_id, u.full_name,
                   COUNT(DISTINCT s.sale_id) as total_sales,
                   SUM(s.total_amount) as total_revenue,
                   AVG(s.total_amount) as average_sale_value
            FROM users u
            LEFT JOIN sales s ON u.user_id = s.cashier_id
            WHERE s.payment_status = 'completed'
            AND s.created_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
            GROUP BY u.user_id
            ORDER BY total_revenue DESC";
    return fetchAll($sql, [$days]);
}

// Get stock movement summary
function getStockMovementSummary($days = 30) {
    $sql = "SELECT sm.type,
                   COUNT(*) as total_movements,
                   SUM(ABS(sm.quantity)) as total_quantity
            FROM stock_movements sm
            WHERE sm.created_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
            GROUP BY sm.type";
    return fetchAll($sql, [$days]);
}

// Get attendance summary
function getAttendanceSummary($days = 30) {
    $sql = "SELECT status,
                   COUNT(*) as total_count,
                   COUNT(DISTINCT employee_id) as unique_employees
            FROM attendance
            WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
            GROUP BY status";
    return fetchAll($sql, [$days]);
}

// Get system activity summary
function getSystemActivitySummary($days = 30) {
    $sql = "SELECT DATE(created_at) as activity_date,
                   COUNT(DISTINCT user_id) as active_users,
                   COUNT(CASE WHEN type = 'sale' THEN 1 END) as sales_count,
                   COUNT(CASE WHEN type = 'purchase' THEN 1 END) as purchase_count
            FROM stock_movements
            WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY activity_date DESC";
    return fetchAll($sql, [$days]);
} 