<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';

header('Content-Type: application/json');

try {
    if (!hasPermission('manage_purchases')) {
        throw new Exception('Permission denied');
    }

    if (empty($_POST['po_id'])) {
        throw new Exception('Purchase order ID is required');
    }

    $po_id = $_POST['po_id'];
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    try {
        // Get purchase order items
        $items = fetchAll(
            "SELECT poi.*, p.quantity_in_stock 
             FROM po_items poi 
             JOIN products p ON poi.product_id = p.product_id 
             WHERE poi.po_id = ?", 
            [$po_id]
        );

        // Update product quantities and create stock movements
        foreach ($items as $item) {
            // Update product quantity
            $new_quantity = $item['quantity_in_stock'] + $item['quantity'];
            update('products', 
                ['quantity_in_stock' => $new_quantity], 
                'product_id = ?', 
                [$item['product_id']]
            );

            // Record stock movement
            insert('stock_movements', [
                'product_id' => $item['product_id'],
                'user_id' => $_SESSION['user_id'],
                'quantity' => $item['quantity'],
                'type' => 'purchase',
                'reference_id' => $po_id,
                'notes' => 'Purchase order received'
            ]);
        }

        // Update purchase order status
        update('purchase_orders', 
            [
                'status' => 'received',
                'updated_at' => date('Y-m-d H:i:s')
            ], 
            'po_id = ?', 
            [$po_id]
        );

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Purchase order received successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error receiving purchase order: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
