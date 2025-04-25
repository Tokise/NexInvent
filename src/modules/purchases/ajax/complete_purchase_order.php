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
    // Get and decode JSON data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || !isset($data['po_id']) || !isset($data['items'])) {
        throw new Exception('Invalid request data');
    }

    $po_id = $data['po_id'];
    $items = $data['items'];
    $notes = $data['notes'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // Update purchase order status
    $sql = "UPDATE purchase_orders 
            SET status = 'received',
                notes = CASE 
                    WHEN ? != '' THEN CONCAT(COALESCE(notes, ''), '\n', ?)
                    ELSE notes
                END,
                updated_at = NOW() 
            WHERE po_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$notes, $notes, $po_id]);

    // Process each item
    foreach ($items as $item) {
        if (!isset($item['product_id']) || !isset($item['received_quantity'])) {
            throw new Exception('Invalid item data');
        }

        $product_id = $item['product_id'];
        $received_qty = $item['received_quantity'];

        // Update received quantity in purchase_order_items
        $sql = "UPDATE purchase_order_items 
                SET received_quantity = ?
                WHERE po_id = ? AND product_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$received_qty, $po_id, $product_id]);

        if ($received_qty > 0) {
            // Get item details for stock movement
            $sql = "SELECT unit_price FROM purchase_order_items 
                   WHERE po_id = ? AND product_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$po_id, $product_id]);
            $item_details = $stmt->fetch(PDO::FETCH_ASSOC);

            // Insert into pending_stock_additions
            $sql = "INSERT INTO pending_stock_additions 
                    (po_id, product_id, quantity, unit_price, status, notes, created_by) 
                    VALUES (?, ?, ?, ?, 'pending', ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $po_id,
                $product_id,
                $received_qty,
                $item_details['unit_price'],
                $notes,
                $user_id
            ]);
        }
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Purchase order marked as received. Products will be added to stock after approval.'
    ]);

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 