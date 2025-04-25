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

    // Check if user has permission to process sales (either manage_sales OR view_sales)
    if (!hasAnyPermission(['manage_sales', 'view_sales'])) {
        throw new Exception('You do not have permission to process sales');
    }

    // Get JSON data from request body
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        throw new Exception('Invalid request data');
    }

    // Validate required data
    if (empty($data['items']) || !isset($data['payment_method'], $data['subtotal'], $data['tax'], $data['total'])) {
        throw new Exception('Missing required sale information');
    }

    // Get database connection and start transaction
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    try {
        // Generate unique transaction number
        $transaction_number = date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

        // Insert sale record
        $sale_data = [
            'transaction_number' => $transaction_number,
            'subtotal' => $data['subtotal'],
            'tax_amount' => $data['tax'],
            'total_amount' => $data['total'],
            'payment_method' => $data['payment_method'],
            'payment_status' => 'completed',
            'cashier_id' => $_SESSION['user_id']
        ];

        $sale_id = insert('sales', $sale_data);

        if (!$sale_id) {
            throw new Exception('Failed to create sale record');
        }

        // Process each item in the cart
        foreach ($data['items'] as $item) {
            // Validate stock availability again
            $current_stock = fetchValue(
                "SELECT out_stock_quantity FROM products WHERE product_id = ?", 
                [$item['product_id']]
            );

            if ($current_stock < $item['quantity']) {
                throw new Exception("Insufficient stock for product: {$item['name']}");
            }

            // Insert sale item
            $sale_item_data = [
                'sale_id' => $sale_id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $item['quantity'] * $item['unit_price']
            ];

            if (!insert('sale_items', $sale_item_data)) {
                throw new Exception('Failed to save sale item');
            }

            // Create stock movement record
            $movement_data = [
                'product_id' => $item['product_id'],
                'user_id' => $_SESSION['user_id'],
                'quantity' => -$item['quantity'], // Negative for sales
                'type' => 'out_sale',
                'reference_id' => $sale_id,
                'notes' => "Sale transaction: {$transaction_number}"
            ];

            if (!insert('stock_movements', $movement_data)) {
                throw new Exception('Failed to create stock movement record');
            }

            // Update product stock
            $update_stock = $pdo->prepare(
                "UPDATE products SET out_stock_quantity = out_stock_quantity - ? WHERE product_id = ?"
            );
            
            if (!$update_stock->execute([$item['quantity'], $item['product_id']])) {
                throw new Exception('Failed to update product stock');
            }
        }

        // Commit transaction
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Sale processed successfully',
            'transaction_number' => $transaction_number
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 