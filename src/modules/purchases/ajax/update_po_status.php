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

// Validate status and enforce role-based permissions
$po_id = isset($_POST['po_id']) ? intval($_POST['po_id']) : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';

// Validate input
if (!$po_id || !in_array($status, ['approved', 'cancelled'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input.']);
    exit();
}

// Enforce role-based permissions
$userRole = $_SESSION['role'];

// Only admin can approve
if ($status === 'approved' && $userRole !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Only administrators can approve purchase orders.']);
    exit();
}

// Admin and manager can cancel
if ($status === 'cancelled' && $userRole !== 'admin' && $userRole !== 'manager') {
    echo json_encode(['success' => false, 'error' => 'Only administrators and managers can cancel purchase orders.']);
    exit();
}

try {
    // Start transaction
    $pdo = getDBConnection();
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

    // Get the PO details for the response
    $sql = "SELECT po_number FROM purchase_orders WHERE po_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$po_id]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Commit transaction
    $pdo->commit();

    $actionMsg = $status === 'approved' ? 'approved' : 'cancelled';
    echo json_encode([
        'success' => true,
        'message' => "Purchase order {$po['po_number']} has been {$actionMsg} successfully."
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 