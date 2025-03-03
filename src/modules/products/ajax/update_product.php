<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';

// Set JSON response header
header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('You must be logged in to perform this action');
    }

    // Check if user has permission
    if (!hasPermission('manage_products')) {
        throw new Exception('You do not have permission to manage products');
    }

    // Validate required fields
    $required_fields = ['sku', 'name', 'category_id', 'unit_price', 'reorder_level'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            throw new Exception("$field is required");
        }
    }

    // Validate numeric fields
    if (!is_numeric($_POST['unit_price']) || $_POST['unit_price'] < 0) {
        throw new Exception('Unit price must be a positive number');
    }

    if (!is_numeric($_POST['reorder_level']) || $_POST['reorder_level'] < 0) {
        throw new Exception('Reorder level must be a positive number');
    }

    // Get database connection
    $conn = getDBConnection();
    
    // Start transaction
    $conn->beginTransaction();

    try {
        // Get product by SKU
        $sql = "SELECT * FROM products WHERE sku = ?";
        $product = fetchOne($sql, [trim($_POST['sku'])]);
        
        if (!$product) {
            throw new Exception('Product not found with the given SKU');
        }

        // Check if category exists
        $sql = "SELECT COUNT(*) FROM categories WHERE category_id = ?";
        if (fetchValue($sql, [$_POST['category_id']]) == 0) {
            throw new Exception('Invalid category selected');
        }

        // Update product
        $product_data = [
            'name' => trim($_POST['name']),
            'description' => trim($_POST['description'] ?? ''),
            'category_id' => $_POST['category_id'],
            'unit_price' => $_POST['unit_price'],
            'reorder_level' => $_POST['reorder_level'],
            'updated_by' => $_SESSION['user_id'],
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $result = update('products', $product_data, 'sku = ?', [trim($_POST['sku'])]);

        if ($result === false) {
            throw new Exception('Failed to update product');
        }

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Product updated successfully'
        ]);

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 