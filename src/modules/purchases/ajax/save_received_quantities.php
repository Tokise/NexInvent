<?php
// Get the document root and project directory
$project_root = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/NexInvent';
$src_path = $project_root . '/src';

// Include required files
require_once $src_path . '/config/db.php';
require_once $src_path . '/includes/permissions.php';

header('Content-Type: application/json');

// Check if user has permission
if (!hasPermission('manage_purchases')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['po_id']) || !isset($input['items']) || !is_array($input['items'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    $po_id = intval($input['po_id']);

    // Verify PO exists
    $stmt = $pdo->prepare("SELECT po_id FROM purchase_orders WHERE po_id = ?");
    $stmt->execute([$po_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid purchase order');
    }

    // Update received quantities and calculate new total
    $stmt = $pdo->prepare("UPDATE purchase_order_items SET 
        received_quantity = ?, 
        quantity = ?,
        subtotal = ?, 
        updated_at = CURRENT_TIMESTAMP 
        WHERE po_item_id = ? AND po_id = ?");
    
    $total_amount = 0;
    
    foreach ($input['items'] as $item) {
        if (!isset($item['po_item_id']) || !isset($item['received_quantity'])) {
            throw new Exception('Invalid item data');
        }

        $po_item_id = intval($item['po_item_id']);
        $received_quantity = intval($item['received_quantity']);

        // Verify the received quantity is not negative
        if ($received_quantity < 0) {
            throw new Exception('Received quantity cannot be negative');
        }

        // Get item details including unit price and ordered quantity
        $check_stmt = $pdo->prepare("SELECT quantity, unit_price FROM purchase_order_items WHERE po_item_id = ? AND po_id = ?");
        $check_stmt->execute([$po_item_id, $po_id]);
        $row = $check_stmt->fetch();
        
        if (!$row) {
            throw new Exception('Invalid purchase order item');
        }

        if ($received_quantity > $row['quantity']) {
            throw new Exception('Received quantity cannot be greater than ordered quantity');
        }

        // Calculate subtotal for this item based on received quantity
        $subtotal = $received_quantity * $row['unit_price'];
        $total_amount += $subtotal;

        // Update received quantity, quantity, and subtotal
        $stmt->execute([
            $received_quantity,
            $received_quantity, // Update the main quantity to match received quantity
            $subtotal,
            $po_item_id,
            $po_id
        ]);

        // Update pending stock additions
        $update_pending = $pdo->prepare("
            UPDATE pending_stock_additions 
            SET quantity = ?,
                updated_at = CURRENT_TIMESTAMP 
            WHERE po_id = ? AND product_id = (
                SELECT product_id 
                FROM purchase_order_items 
                WHERE po_item_id = ?
            )
        ");
        $update_pending->execute([$received_quantity, $po_id, $po_item_id]);
    }

    // Update purchase order total amount
    $update_po = $pdo->prepare("
        UPDATE purchase_orders 
        SET total_amount = ?,
            updated_at = CURRENT_TIMESTAMP 
        WHERE po_id = ?
    ");
    $update_po->execute([$total_amount, $po_id]);

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'message' => 'Changes saved successfully',
        'total_amount' => $total_amount
    ]);

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 