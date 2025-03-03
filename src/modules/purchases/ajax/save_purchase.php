<?php
session_start();
require_once '../../../config/db.php';
header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // Insert purchase order
    $po = [
        'supplier_id' => $_POST['supplier_id'],
        'user_id' => $_SESSION['user_id'],
        'status' => 'pending',
        'total_amount' => 0
    ];
    
    $po_id = insert('purchase_orders', $po);
    
    // Insert items and calculate total
    $total = 0;
    for ($i = 0; $i < count($_POST['product_id']); $i++) {
        $item = [
            'po_id' => $po_id,
            'product_id' => $_POST['product_id'][$i],
            'quantity' => $_POST['quantity'][$i],
            'unit_price' => $_POST['unit_price'][$i],
            'subtotal' => $_POST['quantity'][$i] * $_POST['unit_price'][$i]
        ];
        
        insert('po_items', $item);
        $total += $item['subtotal'];
    }
    
    // Update total amount
    update('purchase_orders', 
           ['total_amount' => $total], 
           'po_id = ?', 
           [$po_id]);
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
