<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/permissions.php';


// Check if user is logged in and has permission
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        sendJsonError('You must be logged in to perform this action');
        exit;
    }
}

function sendJsonError($message) {
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function sendJsonSuccess($message) {
    echo json_encode(['success' => true, 'message' => $message]);
    exit;
}

function getCurrentUserId() {
    return $_SESSION['user_id'];
}

checkLogin();
if (!hasPermission('manage_inventory')) {
    sendJsonError('You do not have permission to manage inventory');
}

try {
    // Validate required fields
    $required_fields = ['sku', 'name', 'category_id', 'in_stock_quantity', 'unit_price', 'reorder_level'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("$field is required");
        }
    }

    // Validate numeric fields
    if (!is_numeric($_POST['in_stock_quantity']) || $_POST['in_stock_quantity'] < 0) {
        throw new Exception('In stock quantity must be a non-negative number');
    }
    if (!is_numeric($_POST['unit_price']) || $_POST['unit_price'] < 0) {
        throw new Exception('Unit price must be a non-negative number');
    }
    if (!is_numeric($_POST['reorder_level']) || $_POST['reorder_level'] < 0) {
        throw new Exception('Reorder level must be a non-negative number');
    }

    // Check if SKU exists
    $sql = "SELECT COUNT(*) FROM products WHERE sku = ?";
    if (fetchValue($sql, [trim($_POST['sku'])]) > 0) {
        throw new Exception('A product with this SKU already exists');
    }

    // Check if category exists
    $sql = "SELECT COUNT(*) FROM categories WHERE category_id = ?";
    if (fetchValue($sql, [$_POST['category_id']]) == 0) {
        throw new Exception('Selected category does not exist');
    }

    // Start transaction
    $conn = getDBConnection();
    $conn->beginTransaction();

    // Insert product
    $product_data = [
        'sku' => trim($_POST['sku']),
        'name' => trim($_POST['name']),
        'description' => trim($_POST['description'] ?? ''),
        'category_id' => $_POST['category_id'],
        'unit_price' => $_POST['unit_price'],
        'in_stock_quantity' => $_POST['in_stock_quantity'],
        'reorder_level' => $_POST['reorder_level'],
        'created_by' => getCurrentUserId(),
        'updated_by' => getCurrentUserId()
    ];

    $product_id = insert('products', $product_data);

    // Create initial stock movement if quantity > 0
    if ($_POST['in_stock_quantity'] > 0) {
        $movement_data = [
            'product_id' => $product_id,
            'user_id' => getCurrentUserId(),
            'quantity' => $_POST['in_stock_quantity'],
            'type' => 'initial',
            'notes' => 'Initial stock'
        ];
        insert('stock_movements', $movement_data);
    }

    $conn->commit();
    sendJsonSuccess('Item saved successfully');

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    sendJsonError($e->getMessage());
} 