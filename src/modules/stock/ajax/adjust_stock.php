<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'You must be logged in to perform this action']);
    exit();
}

// Check if user has permission
if (!hasPermission('manage_inventory')) {
    echo json_encode(['success' => false, 'error' => 'You do not have permission to manage inventory']);
    exit();
}

try {
    // Validate required fields
    $required_fields = ['product_id', 'type', 'quantity', 'reason'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            throw new Exception("$field is required");
        }
    }

    // Sanitize and validate inputs
    $product_id = filter_var($_POST['product_id'], FILTER_VALIDATE_INT);
    $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_FLOAT);
    $type = trim($_POST['type']);
    $reason = trim($_POST['reason']);

    if ($product_id === false) {
        throw new Exception('Invalid product ID');
    }

    // Validate quantity
    if ($quantity === false || $quantity <= 0) {
        throw new Exception('Quantity must be a positive number');
    }

    // Validate adjustment type
    if (!in_array($type, ['in', 'out'])) {
        throw new Exception('Invalid adjustment type');
    }

    $conn = getDBConnection();
    $conn->beginTransaction();

    try {
        // Check if product exists and get current stock
        $sql = "SELECT product_id, quantity_in_stock FROM products WHERE product_id = ? FOR UPDATE";
        $product = fetchOne($sql, [$product_id]);

        if (!$product) {
            throw new Exception('Product not found');
        }

        $current_stock = $product['quantity_in_stock'];

        // For stock out, check if we have enough quantity
        if ($type === 'out' && $current_stock < $quantity) {
            throw new Exception('Insufficient stock for adjustment');
        }

        // Calculate new stock level
        $adjustment = $type === 'in' ? $quantity : -$quantity;
        $new_stock = $current_stock + $adjustment;

        // Update product stock
        $update_data = [
            'quantity_in_stock' => $new_stock,
            'updated_by' => $_SESSION['user_id'],
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        update('products', $update_data, 'product_id = ?', [$product_id]);

        // Create stock movement record
        $movement_data = [
            'product_id' => $product_id,
            'user_id' => $_SESSION['user_id'],
            'quantity' => $adjustment,
            'type' => 'adjustment',
            'notes' => $reason,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        insert('stock_movements', $movement_data);

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Stock adjusted successfully']);

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}