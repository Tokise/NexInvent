<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';

header('Content-Type: application/json');

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id']) || !hasPermission('manage_purchases')) {
    echo json_encode(['success' => false, 'error' => 'You do not have permission to perform this action.']);
    exit();
}

try {
    // Get JSON data from request body
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    if (!$data) {
        throw new Exception('Invalid request data.');
    }

    // Validate required fields
    if (empty($data['items']) || !is_array($data['items'])) {
        throw new Exception('Missing required fields.');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Generate PO number (format: PO-YYYYMMDD-XXXX)
    $date = date('Ymd');
    $sql = "SELECT COUNT(*) FROM purchase_orders WHERE DATE(created_at) = CURDATE()";
    $count = fetchValue($sql) + 1;
    $po_number = sprintf("PO-%s-%04d", $date, $count);

    // Calculate total amount
    $total_amount = 0;
    foreach ($data['items'] as $item) {
        $total_amount += $item['quantity'] * $item['unit_price'];
    }

    // Insert purchase order header
    $sql = "INSERT INTO purchase_orders (po_number, notes, status, total_amount, created_by, created_at) 
            VALUES (?, ?, 'pending', ?, ?, CURRENT_TIMESTAMP)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $po_number,
        $data['notes'] ?? '',
        $total_amount,
        $_SESSION['user_id']
    ]);
    $po_id = $pdo->lastInsertId();

    // Insert purchase order items
    foreach ($data['items'] as $item) {
        $subtotal = $item['quantity'] * $item['unit_price'];
        $sql = "INSERT INTO purchase_order_items (po_id, product_id, quantity, unit_price, subtotal) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $po_id,
            $item['product_id'],
            $item['quantity'],
            $item['unit_price'],
            $subtotal
        ]);
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Purchase order created successfully.',
        'po_number' => $po_number
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 