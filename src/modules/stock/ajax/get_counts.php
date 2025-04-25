<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';

header('Content-Type: application/json');

try {
    // Get pending additions count
    $sql = "SELECT COUNT(*) FROM pending_stock_additions WHERE status = 'pending'";
    $pending_count = fetchValue($sql);

    // Get low stock count
    $sql = "SELECT COUNT(*) FROM products WHERE in_stock_quantity <= reorder_level";
    $low_stock_count = fetchValue($sql);

    echo json_encode([
        'success' => true,
        'pending_count' => $pending_count,
        'low_stock_count' => $low_stock_count
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch counts'
    ]);
} 