<?php
session_start();
require_once '../../../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Check if sale_id is provided
if (!isset($_GET['sale_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sale ID is required']);
    exit();
}

$sale_id = intval($_GET['sale_id']);

// Fetch sale items with product details including image
$sql = "SELECT si.*, p.name, p.image_url, p.unit_price
        FROM sale_items si
        LEFT JOIN products p ON si.product_id = p.product_id
        WHERE si.sale_id = ?";

try {
    $items = fetchAll($sql, [$sale_id]);
    
    // Format the response
    $formatted_items = array_map(function($item) {
        return [
            'name' => $item['name'],
            'quantity' => (int)$item['quantity'],
            'unit_price' => (float)$item['unit_price'],
            'image_url' => $item['image_url'],
            'subtotal' => (float)($item['unit_price'] * $item['quantity'])
        ];
    }, $items);
    
    echo json_encode([
        'success' => true,
        'items' => $formatted_items
    ]);
} catch (Exception $e) {
    error_log("Error fetching sale items: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch sale items'
    ]);
} 