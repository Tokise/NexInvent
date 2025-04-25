<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON response header
header('Content-Type: application/json');

try {
    // Log the incoming request
    error_log("Received product save request: " . json_encode($_POST));
    error_log("Files received: " . json_encode($_FILES));

    // Check if user is logged in and has permission
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }

    requirePermission('manage_products');

    // Validate required fields
    $required_fields = ['sku', 'name', 'category_id', 'unit_price', 'initial_stock', 'reorder_level', 'in_threshold_amount', 'out_threshold_amount'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || $_POST[$field] === '') {
            error_log("Missing required field: $field");
            throw new Exception("$field is required");
        }
    }

    // Log validated data
    error_log("Validated data: " . json_encode($_POST));

    // Validate numeric fields
    if (!is_numeric($_POST['unit_price']) || $_POST['unit_price'] < 0) {
        throw new Exception('Unit price must be a positive number');
    }

    if (!is_numeric($_POST['initial_stock']) || $_POST['initial_stock'] < 0) {
        throw new Exception('Initial stock must be a positive number');
    }

    if (!is_numeric($_POST['reorder_level']) || $_POST['reorder_level'] < 0) {
        throw new Exception('Reorder level must be a positive number');
    }

    if (!is_numeric($_POST['in_threshold_amount']) || $_POST['in_threshold_amount'] < 0) {
        throw new Exception('IN stock threshold must be a positive number');
    }

    if (!is_numeric($_POST['out_threshold_amount']) || $_POST['out_threshold_amount'] < 0) {
        throw new Exception('OUT stock threshold must be a positive number');
    }

    // Check if SKU already exists
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = ?");
    $stmt->execute([$_POST['sku']]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('A product with this SKU already exists');
    }

    // Check if category exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE category_id = ?");
    $stmt->execute([$_POST['category_id']]);
    if ($stmt->fetchColumn() == 0) {
        throw new Exception('Invalid category selected');
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        $uploadDir = '../../../uploads/products/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $imageUrl = null;

        // Handle image upload if present
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            error_log("Processing image upload");
            
            $file = $_FILES['image'];
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception("Invalid file type. Allowed types: " . implode(', ', $allowed_extensions));
            }

            // Generate unique filename
            $new_filename = uniqid() . '.' . $file_extension;
            $targetPath = $uploadDir . $new_filename;

            error_log("Saving image to: " . $targetPath);

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new Exception("Failed to upload image");
            }

            error_log("Image saved successfully");
            $imageUrl = 'uploads/products/' . $new_filename;
        }

        // Insert product
        $sql = "INSERT INTO products (sku, name, description, category_id, unit_price, in_stock_quantity, 
                out_stock_quantity, reorder_level, out_threshold_amount, in_threshold_amount, image_url) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['sku'],
            $_POST['name'],
            $_POST['description'] ?? '',
            $_POST['category_id'],
            $_POST['unit_price'],
            $_POST['initial_stock'],
            0,
            $_POST['reorder_level'],
            $_POST['out_threshold_amount'],
            $_POST['in_threshold_amount'],
            $imageUrl
        ]);

        $product_id = $pdo->lastInsertId();

        // Create initial stock movement
        if ($_POST['initial_stock'] > 0) {
            $sql = "INSERT INTO stock_movements (product_id, type, quantity, notes, user_id) 
                    VALUES (?, 'in_initial', ?, 'Initial IN stock entry', ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $product_id,
                $_POST['initial_stock'],
                $_SESSION['user_id']
            ]);
        }

        // Commit transaction
        $pdo->commit();
        error_log("Transaction committed successfully");

        echo json_encode([
            'success' => true,
            'message' => 'Product saved successfully',
            'product_id' => $product_id
        ]);

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        error_log("Transaction rolled back: " . $e->getMessage());
        
        // If insert fails and we uploaded an image, delete it
        if (isset($imageUrl) && file_exists($uploadDir . basename($imageUrl))) {
            unlink($uploadDir . basename($imageUrl));
            error_log("Deleted uploaded image due to error");
        }
        
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error saving product: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 