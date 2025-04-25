<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

requirePermission('manage_purchases');

try {
    if (!isset($_GET['id'])) {
        throw new Exception('Purchase order ID is required');
    }

    $po_id = $_GET['id'];
    $pdo = getDBConnection();
    
    // Get purchase order items with product details
    $sql = "SELECT poi.*, p.name, p.sku, c.name as category_name
            FROM purchase_order_items poi
            JOIN products p ON poi.product_id = p.product_id
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE poi.po_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$po_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 