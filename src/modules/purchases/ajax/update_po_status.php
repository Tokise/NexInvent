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

// Validate input
$po_id = isset($_POST['po_id']) ? intval($_POST['po_id']) : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';

if (!$po_id || !in_array($status, ['approved', 'cancelled'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input.']);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Update purchase order status
    $sql = "UPDATE purchase_orders SET 
            status = ?,
            approved_by = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE po_id = ? AND status = 'pending'";
    
    $params = [$status, $_SESSION['user_id'], $po_id];
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Purchase order not found or already processed.');
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Purchase order status updated successfully.'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 