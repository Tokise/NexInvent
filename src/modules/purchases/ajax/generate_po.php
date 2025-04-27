<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Check role-based permissions - only admin and manager can generate POs
$userRole = $_SESSION['role'];
if ($userRole !== 'admin' && $userRole !== 'manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only administrators and managers can generate purchase orders']);
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
    $product_ids = [];

    // Add items to purchase order
    foreach ($input['items'] as $item) {
        if (!isset($item['product_id'], $item['quantity'], $item['unit_price'])) {
            throw new Exception('Invalid item data provided');
        }

        // Collect product IDs for verification
        $product_ids[] = $item['product_id'];

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
            'product_name' => $item['product_name'],
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

    // Verify if any of these products already have pending POs
    if (!empty($product_ids)) {
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $sql = "SELECT p.name, p.product_id, po.po_number 
                FROM products p 
                JOIN purchase_order_items poi ON p.product_id = poi.product_id
                JOIN purchase_orders po ON poi.po_id = po.po_id
                WHERE p.product_id IN ($placeholders)
                AND po.po_id != ? 
                AND po.status IN ('pending', 'approved')";
        
        $params = array_merge($product_ids, [$po_id]);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $existing_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($existing_items)) {
            $product_names = array_map(function($item) {
                return $item['name'] . " (PO: " . $item['po_number'] . ")";
            }, $existing_items);
            
            // Just log it, don't throw exception
            error_log("Warning: Some products already have pending POs: " . implode(", ", $product_names));
        }
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
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Database error in generate_po.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'A database error occurred. Please try again or contact support.'
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 