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
    requirePermission('manage_inventory');
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit();
}

try {
    // Validate required parameters
    if (!isset($_POST['addition_id']) || !isset($_POST['action'])) {
        throw new Exception('Missing required parameters: addition_id and action are required');
    }

    $addition_id = filter_var($_POST['addition_id'], FILTER_VALIDATE_INT);
    if ($addition_id === false) {
        throw new Exception('Invalid addition ID');
    }

    $action = $_POST['action'];
    if (!in_array($action, ['approve', 'reject'])) {
        throw new Exception('Invalid action. Must be either "approve" or "reject"');
    }

    $user_id = $_SESSION['user_id'];
    
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // Get addition details with product information
    $sql = "SELECT psa.*, p.name as product_name, p.in_stock_quantity as current_stock 
            FROM pending_stock_additions psa
            JOIN products p ON psa.product_id = p.product_id 
            WHERE psa.addition_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$addition_id]);
    $addition = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$addition) {
        throw new Exception('Stock addition not found');
    }

    if ($addition['status'] !== 'pending') {
        throw new Exception('This addition has already been processed');
    }

    // Validate quantity
    if ($addition['quantity'] <= 0) {
        throw new Exception('Invalid quantity. Must be greater than zero');
    }

    if ($action === 'approve') {
        // Check for integer overflow
        $new_quantity = $addition['current_stock'] + $addition['quantity'];
        if ($new_quantity < 0 || $new_quantity > PHP_INT_MAX) {
            throw new Exception('Invalid quantity. Would cause stock level overflow');
        }

        // Update product stock quantity
        $sql = "UPDATE products 
                SET in_stock_quantity = in_stock_quantity + ?,
                    updated_at = NOW() 
                WHERE product_id = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$addition['quantity'], $addition['product_id']]);
        
        if (!$result || $stmt->rowCount() === 0) {
            throw new Exception('Failed to update product stock quantity');
        }

        // Record stock movement
        $sql = "INSERT INTO stock_movements 
                (product_id, user_id, quantity, type, reference_id, notes) 
                VALUES (?, ?, ?, 'in_purchase', ?, ?)";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $addition['product_id'],
            $user_id,
            $addition['quantity'],
            $addition['po_id'],
            sprintf('Stock addition from purchase order for %s', $addition['product_name'])
        ]);

        if (!$result) {
            throw new Exception('Failed to record stock movement');
        }

        // Update addition status
        $sql = "UPDATE pending_stock_additions 
                SET status = 'approved',
                    approved_by = ?,
                    updated_at = NOW() 
                WHERE addition_id = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$user_id, $addition_id]);

        if (!$result) {
            throw new Exception('Failed to update addition status');
        }

        $message = sprintf('Successfully added %d units of %s to stock', 
                          $addition['quantity'], 
                          $addition['product_name']);
    } else {
        // Reject the addition
        $sql = "UPDATE pending_stock_additions 
                SET status = 'rejected',
                    approved_by = ?,
                    updated_at = NOW() 
                WHERE addition_id = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$user_id, $addition_id]);

        if (!$result) {
            throw new Exception('Failed to reject stock addition');
        }

        $message = sprintf('Successfully rejected stock addition for %s', 
                          $addition['product_name']);
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'addition_id' => $addition_id,
            'product_name' => $addition['product_name'],
            'quantity' => $addition['quantity'],
            'action' => $action
        ]
    ]);

} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log('Database error in process_stock_addition.php: ' . $e->getMessage());
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