<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!hasPermission('view_inventory')) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'You do not have permission to perform this action']);
    exit();
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Item ID is required']);
    exit();
}

try {
    // Fetch item details
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.category_id 
            WHERE p.product_id = ?";
    
    $item = fetchOne($sql, [$_GET['id']]);
    
    if (!$item) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Item not found']);
        exit();
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $item
    ]);
} catch (Exception $e) {
    error_log("Error fetching item: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Failed to fetch item details',
        'message' => $e->getMessage()
    ]);
} 