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
    $is_transfer = isset($_POST['is_transfer']) ? filter_var($_POST['is_transfer'], FILTER_VALIDATE_BOOLEAN) : false;

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
        $sql = "SELECT product_id, name, in_stock_quantity, out_stock_quantity FROM products WHERE product_id = ? FOR UPDATE";
        $product = fetchRow($sql, [$product_id]);

        if (!$product) {
            throw new Exception('Product not found');
        }

        $current_in_stock = $product['in_stock_quantity'];
        $current_out_stock = $product['out_stock_quantity'];
        $product_name = $product['name'];

        // Determine movement type and perform stock adjustment
        $movement_type = '';
        $success_message = '';
        $update_data = [];
        
        if ($type === 'in') {
            if ($is_transfer) {
                // This is a transfer from OUT to IN
                if ($current_out_stock < $quantity) {
                    throw new Exception('Insufficient OUT stock for transfer');
                }
                
                $update_data = [
                    'in_stock_quantity' => $current_in_stock + $quantity,
                    'out_stock_quantity' => $current_out_stock - $quantity,
                    'updated_by' => $_SESSION['user_id'],
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $movement_type = 'out_to_in';
                $success_message = "Transferred $quantity units of $product_name from OUT to IN stock";
            } else {
                // Regular stock addition
                $update_data = [
                    'in_stock_quantity' => $current_in_stock + $quantity,
                    'updated_by' => $_SESSION['user_id'],
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $movement_type = 'in_purchase';
                $success_message = "Added $quantity units to $product_name IN stock";
            }
        } else { // type === 'out'
            if ($is_transfer) {
                // This is a transfer from IN to OUT
                if ($current_in_stock < $quantity) {
                    throw new Exception('Insufficient IN stock for transfer');
                }
                
                $update_data = [
                    'in_stock_quantity' => $current_in_stock - $quantity,
                    'out_stock_quantity' => $current_out_stock + $quantity,
                    'updated_by' => $_SESSION['user_id'],
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $movement_type = 'in_to_out';
                $success_message = "Transferred $quantity units of $product_name from IN to OUT stock";
            } else {
                // Regular stock removal
                if ($current_in_stock < $quantity) {
                    throw new Exception('Insufficient stock for adjustment');
                }
                
                $update_data = [
                    'in_stock_quantity' => $current_in_stock - $quantity,
                    'updated_by' => $_SESSION['user_id'],
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $movement_type = 'out_adjustment';
                $success_message = "Removed $quantity units from $product_name IN stock";
            }
        }
        
        // Update product stock
        update('products', $update_data, 'product_id = ?', [$product_id]);

        // Calculate adjustment value based on movement type
        $adjustment = ($type === 'in' && !$is_transfer) || ($type === 'out' && $is_transfer) ? $quantity : -$quantity;

        // Create stock movement record
        $movement_data = [
            'product_id' => $product_id,
            'user_id' => $_SESSION['user_id'],
            'quantity' => $adjustment,
            'type' => $movement_type,
            'notes' => $reason,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        insert('stock_movements', $movement_data);

        $conn->commit();
        echo json_encode([
            'success' => true, 
            'message' => $success_message,
            'movement_type' => $movement_type,
            'product_name' => $product_name,
            'quantity' => $quantity
        ]);

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}