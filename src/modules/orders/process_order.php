<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/permissions.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log request information
error_log("Request received at process_order.php");
error_log("Session data: " . print_r($_SESSION, true));

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in");
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if user has permission to create sales
if (!hasPermission('create_sale')) {
    error_log("User lacks create_sale permission");
    http_response_code(403);
    echo json_encode(['error' => 'You do not have permission to perform this action']);
    exit();
}

try {
    // Get JSON input and log it
    $raw_input = file_get_contents('php://input');
    error_log("Received raw order data: " . $raw_input);
    
    $input = json_decode($raw_input, true);
    if (!$input) {
        $json_error = json_last_error_msg();
        error_log("JSON decode error: " . $json_error);
        throw new Exception('Invalid JSON data received: ' . $json_error);
    }

    error_log("Decoded input data: " . print_r($input, true));

    // Validate required fields
    $required_fields = ['customer_id', 'shipping_address', 'billing_address', 'due_date', 'items'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            error_log("Missing required field: $field");
            throw new Exception("Missing required field: $field");
        }
    }

    // Validate customer exists
    $customer = fetchOne("SELECT customer_id FROM customers WHERE customer_id = ?", [$input['customer_id']]);
    if (!$customer) {
        error_log("Invalid customer ID: {$input['customer_id']}");
        throw new Exception("Invalid customer ID: {$input['customer_id']}");
    }

    // Start transaction
    $conn = getDBConnection();
    $conn->beginTransaction();
    error_log("Transaction started");

    // Insert sales order
    $order_data = [
        'customer_id' => $input['customer_id'],
        'user_id' => $_SESSION['user_id'],
        'shipping_address' => $input['shipping_address'],
        'billing_address' => $input['billing_address'],
        'due_date' => $input['due_date'],
        'total_amount' => $input['subtotal'],
        'tax_amount' => $input['tax_amount'],
        'discount_amount' => $input['discount_amount'],
        'grand_total' => $input['grand_total'],
        'status' => $input['status'] ?? 'confirmed',
        'notes' => $input['notes'] ?? null
    ];
    
    // Add payment information if provided
    if (isset($input['payment_status'])) {
        $order_data['payment_status'] = $input['payment_status'];
    }
    
    if (isset($input['payment_method']) && !empty($input['payment_method'])) {
        $order_data['payment_method'] = $input['payment_method'];
    }
    
    if (isset($input['amount_paid']) && $input['amount_paid'] > 0) {
        $order_data['amount_paid'] = $input['amount_paid'];
        $order_data['payment_date'] = date('Y-m-d H:i:s');
        
        // If fully paid, set status to completed
        if ($input['payment_status'] === 'paid') {
            $order_data['status'] = 'completed';
        }
    }

    error_log("Attempting to insert order with data: " . print_r($order_data, true));
    
    try {
        $sale_id = insert('sales_orders', $order_data);
        error_log("Created sale order with ID: " . $sale_id);
    } catch (Exception $e) {
        error_log("Error inserting sales order: " . $e->getMessage());
        throw new Exception("Failed to create sales order: " . $e->getMessage());
    }

    // Insert order items and update stock
    foreach ($input['items'] as $index => $item) {
        error_log("Processing item " . ($index + 1) . ": " . print_r($item, true));

        // Validate product exists and has enough stock
        $product = fetchOne("SELECT product_id, quantity_in_stock FROM products WHERE product_id = ? FOR UPDATE", [$item['product_id']]);
        if (!$product) {
            error_log("Invalid product ID: {$item['product_id']}");
            throw new Exception("Invalid product ID: {$item['product_id']}");
        }

        if ($product['quantity_in_stock'] < $item['quantity']) {
            error_log("Insufficient stock for product ID {$item['product_id']}: requested {$item['quantity']}, available {$product['quantity_in_stock']}");
            throw new Exception("Insufficient stock for product ID {$item['product_id']}: requested {$item['quantity']}, available {$product['quantity_in_stock']}");
        }

        // Calculate item subtotal
        $subtotal = $item['quantity'] * $item['unit_price'];
        $tax = $subtotal * ($item['tax_rate'] / 100);
        $discount = $subtotal * ($item['discount_rate'] / 100);

        // Insert order item
        $item_data = [
            'sale_id' => $sale_id,
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'tax_rate' => $item['tax_rate'],
            'discount_rate' => $item['discount_rate'],
            'subtotal' => $subtotal
        ];

        error_log("Inserting order item: " . print_r($item_data, true));
        try {
            insert('sales_order_items', $item_data);
        } catch (Exception $e) {
            error_log("Error inserting order item: " . $e->getMessage());
            throw new Exception("Failed to insert order item: " . $e->getMessage());
        }

        // Update product stock
        try {
            $sql = "UPDATE products 
                    SET quantity_in_stock = quantity_in_stock - ? 
                    WHERE product_id = ?";
            executeQuery($sql, [$item['quantity'], $item['product_id']]);
            error_log("Updated stock for product ID: {$item['product_id']}");
        } catch (Exception $e) {
            error_log("Error updating product stock: " . $e->getMessage());
            throw new Exception("Failed to update product stock: " . $e->getMessage());
        }

        // Log stock movement
        $movement_data = [
            'product_id' => $item['product_id'],
            'user_id' => $_SESSION['user_id'],
            'quantity' => -$item['quantity'], // Negative for sales
            'type' => 'sale',
            'reference_id' => $sale_id,
            'notes' => "Sale order #{$sale_id}"
        ];

        error_log("Inserting stock movement: " . print_r($movement_data, true));
        try {
            // First check if stock_movements table has the reference_id column
            $check_column = "SELECT COUNT(*) 
                           FROM information_schema.columns 
                           WHERE table_schema = '" . DB_NAME . "' 
                           AND table_name = 'stock_movements' 
                           AND column_name = 'reference_id'";
            
            $has_reference_id = fetchValue($check_column) > 0;
            
            if (!$has_reference_id) {
                // If reference_id doesn't exist, remove it from the data
                unset($movement_data['reference_id']);
            }
            
            insert('stock_movements', $movement_data);
            error_log("Stock movement inserted successfully");
        } catch (Exception $e) {
            error_log("Error inserting stock movement: " . $e->getMessage());
            throw new Exception("Failed to insert stock movement: " . $e->getMessage());
        }
    }

    // Commit transaction
    $conn->commit();
    error_log("Transaction committed successfully");

    // Return success response
    $response = [
        'success' => true,
        'message' => 'Sales order created successfully',
        'sale_id' => $sale_id
    ];
    error_log("Sending success response: " . print_r($response, true));
    echo json_encode($response);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
        error_log("Transaction rolled back due to error");
    }

    error_log("Error creating sales order: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    $error_response = [
        'error' => 'Failed to create sales order',
        'message' => $e->getMessage(),
        'details' => $e->getTraceAsString()
    ];
    error_log("Sending error response: " . print_r($error_response, true));
    echo json_encode($error_response);
} 