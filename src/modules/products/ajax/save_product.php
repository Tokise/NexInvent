<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';

// Set JSON response header
header('Content-Type: application/json');

try {
    // Check if user is logged in and has permission
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('You must be logged in to perform this action');
    }

    if (!hasPermission('manage_products')) {
        throw new Exception('You do not have permission to manage products');
    }

    // Validate required fields
    $required_fields = ['sku', 'name', 'category_id', 'unit_price', 'quantity_in_stock', 'reorder_level'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("$field is required");
        }
    }

    // Validate numeric fields
    if (!is_numeric($_POST['unit_price']) || $_POST['unit_price'] < 0) {
        throw new Exception('Unit price must be a positive number');
    }

    if (!is_numeric($_POST['quantity_in_stock']) || $_POST['quantity_in_stock'] < 0) {
        throw new Exception('Initial stock must be a positive number');
    }

    if (!is_numeric($_POST['reorder_level']) || $_POST['reorder_level'] < 0) {
        throw new Exception('Reorder level must be a positive number');
    }

    // Check if SKU already exists
    $sql = "SELECT COUNT(*) FROM products WHERE sku = ?";
    if (fetchValue($sql, [$_POST['sku']]) > 0) {
        throw new Exception('A product with this SKU already exists');
    }

    // Check if category exists
    $sql = "SELECT COUNT(*) FROM categories WHERE category_id = ?";
    if (fetchValue($sql, [$_POST['category_id']]) == 0) {
        throw new Exception('Invalid category selected');
    }

    // Start transaction
    $conn = getDBConnection();
    $conn->beginTransaction();

    // Insert product
    $product_data = [
        'sku' => $_POST['sku'],
        'name' => $_POST['name'],
        'description' => $_POST['description'] ?? '',
        'category_id' => $_POST['category_id'],
        'unit_price' => $_POST['unit_price'],
        'quantity_in_stock' => $_POST['quantity_in_stock'],
        'reorder_level' => $_POST['reorder_level']
    ];

    $product_id = insert('products', $product_data);

    // If initial stock is greater than 0, create a stock movement record
    if ($_POST['quantity_in_stock'] > 0) {
        $movement_data = [
            'product_id' => $product_id,
            'user_id' => $_SESSION['user_id'],
            'quantity' => $_POST['quantity_in_stock'],
            'type' => 'initial',
            'notes' => 'Initial stock entry'
        ];

        insert('stock_movements', $movement_data);
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Product saved successfully',
        'product_id' => $product_id
    ]);

} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 