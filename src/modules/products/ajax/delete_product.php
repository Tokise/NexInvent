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

    // Validate product ID
    if (!isset($_POST['product_id']) || !is_numeric($_POST['product_id'])) {
        throw new Exception('Invalid product ID');
    }

    // Start transaction
    $conn = getDBConnection();
    $conn->beginTransaction();

    // Check if product exists and get its details
    $sql = "SELECT * FROM products WHERE product_id = ?";
    $product = fetchOne($sql, [$_POST['product_id']]);

    if (!$product) {
        throw new Exception('Product not found');
    }

    // Check if product is used in any sales orders
    $sql = "SELECT COUNT(*) FROM sales_order_items WHERE product_id = ?";
    if (fetchValue($sql, [$_POST['product_id']]) > 0) {
        throw new Exception('Cannot delete product: It is referenced in sales orders');
    }

    // Check if product is used in any purchase orders
    $sql = "SELECT COUNT(*) FROM po_items WHERE product_id = ?";
    if (fetchValue($sql, [$_POST['product_id']]) > 0) {
        throw new Exception('Cannot delete product: It is referenced in purchase orders');
    }

    // Delete stock movements
    delete('stock_movements', 'product_id = ?', [$_POST['product_id']]);

    // Delete product
    delete('products', 'product_id = ?', [$_POST['product_id']]);

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Product deleted successfully'
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