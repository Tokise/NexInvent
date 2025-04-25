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

requirePermission('manage_products');

try {
    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['product_id']) || !isset($data['quantity']) || !isset($data['notes'])) {
        throw new Exception('Missing required fields');
    }

    $product_id = $data['product_id'];
    $quantity = $data['quantity'];
    $notes = $data['notes'];

    if (!is_numeric($quantity) || $quantity <= 0) {
        throw new Exception('Quantity must be a positive number');
    }

    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // Check if product exists and has enough IN stock
    $stmt = $pdo->prepare("SELECT in_stock_quantity FROM products WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        throw new Exception('Product not found');
    }

    if ($product['in_stock_quantity'] < $quantity) {
        throw new Exception('Not enough IN stock quantity available');
    }

    // Update product quantities (keeping existing out_threshold_amount)
    $sql = "UPDATE products 
            SET in_stock_quantity = in_stock_quantity - ?, 
                out_stock_quantity = out_stock_quantity + ?
            WHERE product_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$quantity, $quantity, $product_id]);

    // Create stock movement record
    $sql = "INSERT INTO stock_movements (product_id, type, quantity, notes, user_id) 
            VALUES (?, 'in_to_out', ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $product_id,
        $quantity,
        $notes,
        $_SESSION['user_id']
    ]);

    $pdo->commit();

    // Get updated quantities
    $stmt = $pdo->prepare("SELECT in_stock_quantity, out_stock_quantity, out_threshold_amount FROM products WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Stock moved successfully',
        'in_stock_quantity' => $updated['in_stock_quantity'],
        'out_stock_quantity' => $updated['out_stock_quantity'],
        'out_threshold_amount' => $updated['out_threshold_amount']
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 