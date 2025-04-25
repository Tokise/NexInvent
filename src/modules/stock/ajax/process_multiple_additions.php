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

requirePermission('manage_inventory');

try {
    if (!isset($_POST['addition_ids']) || !isset($_POST['action'])) {
        throw new Exception('Missing required parameters');
    }

    $addition_ids = $_POST['addition_ids'];
    $action = $_POST['action'];
    $user_id = $_SESSION['user_id'];
    
    if (!is_array($addition_ids) || empty($addition_ids)) {
        throw new Exception('No items selected');
    }

    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // Get all pending additions
    $placeholders = str_repeat('?,', count($addition_ids) - 1) . '?';
    $sql = "SELECT * FROM pending_stock_additions 
            WHERE addition_id IN ($placeholders) 
            AND status = 'pending'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($addition_ids);
    $additions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($additions)) {
        throw new Exception('No pending additions found');
    }

    $processed = 0;
    foreach ($additions as $addition) {
        if ($action === 'approve') {
            // Update product stock quantity
            $sql = "UPDATE products 
                    SET in_stock_quantity = in_stock_quantity + ?,
                        updated_at = NOW() 
                    WHERE product_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$addition['quantity'], $addition['product_id']]);

            // Record stock movement
            $sql = "INSERT INTO stock_movements 
                    (product_id, user_id, quantity, type, reference_id, notes) 
                    VALUES (?, ?, ?, 'in_purchase', ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $addition['product_id'],
                $user_id,
                $addition['quantity'],
                $addition['po_id'],
                'Stock addition from purchase order'
            ]);

            // Update addition status
            $sql = "UPDATE pending_stock_additions 
                    SET status = 'approved',
                        approved_by = ?,
                        updated_at = NOW() 
                    WHERE addition_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $addition['addition_id']]);
        } else {
            // Update addition status to rejected
            $sql = "UPDATE pending_stock_additions 
                    SET status = 'rejected',
                        approved_by = ?,
                        updated_at = NOW() 
                    WHERE addition_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $addition['addition_id']]);
        }
        $processed++;
    }

    $pdo->commit();
    
    $action_text = $action === 'approve' ? 'approved' : 'rejected';
    echo json_encode([
        'success' => true,
        'message' => "$processed items have been $action_text successfully"
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