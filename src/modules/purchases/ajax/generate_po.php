<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';

header('Content-Type: application/json');

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    requirePermission('manage_purchases');
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit();
}

try {
    // Validate input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['items']) || empty($input['items'])) {
        throw new Exception('No items selected for purchase order');
    }

    $user_id = $_SESSION['user_id'];
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // Generate PO number
    $year = date('Y');
    $month = date('m');
    $sql = "SELECT COUNT(*) FROM purchase_orders WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$year, $month]);
    $count = $stmt->fetchColumn();
    $po_number = sprintf('PO-%s%s-%03d', $year, $month, $count + 1);

    // Create purchase order
    $sql = "INSERT INTO purchase_orders (po_number, status, total_amount, notes, is_auto_generated, created_by) 
            VALUES (?, 'pending', 0, ?, 1, ?)";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $po_number,
        'Auto-generated for low stock items',
        $user_id
    ]);

    if (!$result) {
        throw new Exception('Failed to create purchase order');
    }

    $po_id = $pdo->lastInsertId();
    $total_amount = 0;
    $items_processed = [];

    // Add items to purchase order
    foreach ($input['items'] as $item) {
        if (!isset($item['product_id'], $item['quantity'], $item['unit_price'])) {
            throw new Exception('Invalid item data provided');
        }

        $subtotal = $item['quantity'] * $item['unit_price'];
        $sql = "INSERT INTO purchase_order_items (po_id, product_id, quantity, unit_price, subtotal) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $po_id,
            $item['product_id'],
            $item['quantity'],
            $item['unit_price'],
            $subtotal
        ]);

        if (!$result) {
            throw new Exception('Failed to add item to purchase order');
        }

        $total_amount += $subtotal;
        $items_processed[] = [
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'subtotal' => $subtotal
        ];
    }

    // Update purchase order total
    $sql = "UPDATE purchase_orders SET total_amount = ? WHERE po_id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$total_amount, $po_id]);

    if (!$result) {
        throw new Exception('Failed to update purchase order total');
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Purchase order generated successfully',
        'data' => [
            'po_id' => $po_id,
            'po_number' => $po_number,
            'total_amount' => $total_amount,
            'items' => $items_processed
        ]
    ]);

} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log('Database error in generate_po.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'A database error occurred. Please try again or contact support.'
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