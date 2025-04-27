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
if (!hasPermission('manage_stock')) {
    echo json_encode(['success' => false, 'error' => 'You do not have permission to manage stock']);
    exit();
}

try {
    // Get POST data
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $direction = isset($_POST['direction']) ? trim($_POST['direction']) : '';
    
    // Validate input
    if (!$product_id) {
        throw new Exception('Invalid product ID');
    }
    
    if ($quantity <= 0) {
        throw new Exception('Quantity must be greater than zero');
    }
    
    if (!in_array($direction, ['in_to_out', 'out_to_in'])) {
        throw new Exception('Invalid direction specified');
    }
    
    // Get product details
    $sql = "SELECT * FROM products WHERE product_id = ?";
    $product = fetchRow($sql, [$product_id]);
    
    if (!$product) {
        throw new Exception('Product not found');
    }
    
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    // Process stock movement
    if ($direction === 'in_to_out') {
        // Check if we have enough stock
        if ($product['in_stock_quantity'] < $quantity) {
            throw new Exception('Not enough stock available. Current IN stock: ' . $product['in_stock_quantity']);
        }
        
        // Update product stock levels
        $sql = "UPDATE products 
                SET in_stock_quantity = in_stock_quantity - ?, 
                    out_stock_quantity = out_stock_quantity + ?,
                    updated_at = NOW()
                WHERE product_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$quantity, $quantity, $product_id]);
        
        // Create stock movement record
        $sql = "INSERT INTO stock_movements (product_id, type, quantity, notes, user_id, created_at) 
                VALUES (?, 'in_to_out', ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$product_id, $quantity, $notes, $_SESSION['user_id']]);
        
        $message = 'Successfully moved ' . $quantity . ' units from IN stock to OUT stock';
    } else { // out_to_in
        // Check if we have enough OUT stock
        if ($product['out_stock_quantity'] < $quantity) {
            throw new Exception('Not enough OUT stock available. Current OUT stock: ' . $product['out_stock_quantity']);
        }
        
        // Update product stock levels
        $sql = "UPDATE products 
                SET in_stock_quantity = in_stock_quantity + ?, 
                    out_stock_quantity = out_stock_quantity - ?,
                    updated_at = NOW()
                WHERE product_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$quantity, $quantity, $product_id]);
        
        // Create stock movement record
        $sql = "INSERT INTO stock_movements (product_id, type, quantity, notes, user_id, created_at) 
                VALUES (?, 'out_to_in', ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$product_id, $quantity, $notes, $_SESSION['user_id']]);
        
        $message = 'Successfully moved ' . $quantity . ' units from OUT stock to IN stock';
    }
    
    $pdo->commit();
    
    // Return updated stock levels
    $sql = "SELECT in_stock_quantity, out_stock_quantity FROM products WHERE product_id = ?";
    $updated = fetchRow($sql, [$product_id]);
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'in_stock' => $updated['in_stock_quantity'],
        'out_stock' => $updated['out_stock_quantity'],
        'alert' => [
            'title' => 'Success!',
            'text' => $message,
            'icon' => 'success'
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'alert' => [
            'title' => 'Error!',
            'text' => $e->getMessage(),
            'icon' => 'error'
        ]
    ]);
} 