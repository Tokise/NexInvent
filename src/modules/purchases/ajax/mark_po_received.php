<?php
header('Content-Type: application/json');
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

requirePermission('manage_purchases');

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }

    // Validate input
    if (!isset($input['po_id'])) {
        throw new Exception('Missing required fields');
    }

    $po_id = filter_var($input['po_id'], FILTER_VALIDATE_INT);
    if (!$po_id) {
        throw new Exception('Invalid purchase order ID');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Check PO status
    $sql = "SELECT po.*, COUNT(poi.po_item_id) as item_count 
            FROM purchase_orders po 
            LEFT JOIN purchase_order_items poi ON po.po_id = poi.po_id 
            WHERE po.po_id = ? 
            GROUP BY po.po_id 
            FOR UPDATE";
    $po = fetchRow($sql, [$po_id]);
    
    if (!$po) {
        throw new Exception('Purchase order not found');
    }
    
    if ($po['status'] !== 'ordered' && $po['status'] !== 'approved') {
        throw new Exception('Purchase order must be in ordered or approved status to be marked as received');
    }

    if ($po['item_count'] === 0) {
        throw new Exception('Purchase order has no items');
    }

    // Get PO items
    $sql = "SELECT poi.*, p.name as product_name, p.in_stock_quantity as current_stock
            FROM purchase_order_items poi 
            JOIN products p ON poi.product_id = p.product_id 
            WHERE poi.po_id = ?";
    $items = fetchAll($sql, [$po_id]);

    // Create pending stock additions for each item
    foreach ($items as $item) {
        // Insert into pending_stock_additions
        $sql = "INSERT INTO pending_stock_additions 
                (po_id, product_id, quantity, unit_price, status, notes, created_by) 
                VALUES (?, ?, ?, ?, 'pending', ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $po_id,
            $item['product_id'],
            $item['quantity'],
            $item['unit_price'],
            "Stock addition from PO #{$po['po_number']}",
            $_SESSION['user_id']
        ]);
    }

    // Update purchase order status
    $sql = "UPDATE purchase_orders 
            SET status = 'received',
                received_by = ?,
                received_at = NOW() 
            WHERE po_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id'], $po_id]);

    // Create notification for managers to approve stock additions
    $sql = "INSERT INTO notifications 
            (type, title, message, reference_id, for_role) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'stock_approval',
        'Stock Approval Required',
        "New stock additions from PO #{$po['po_number']} require approval",
        $po_id,
        'manager'
    ]);

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Purchase order has been marked as received. Stock additions are pending approval.'
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 