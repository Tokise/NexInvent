<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/permissions.php';

// Set JSON response header
header('Content-Type: application/json');

try {
    // Check if user is logged in and has permission
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('You must be logged in to perform this action');
    }

    if (!hasPermission('view_products')) {
        throw new Exception('You do not have permission to view categories');
    }

    // Validate category ID
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid category ID');
    }

    // Get category details with user information
    $stmt = $pdo->prepare("
        SELECT c.*, 
               u1.full_name as created_by_name,
               u2.full_name as updated_by_name
        FROM categories c
        LEFT JOIN users u1 ON c.created_by = u1.user_id
        LEFT JOIN users u2 ON c.updated_by = u2.user_id
        WHERE c.category_id = ?
    ");
    
    $stmt->execute([$_GET['id']]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        throw new Exception('Category not found');
    }

    // Get product count for this category
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as product_count 
        FROM products 
        WHERE category_id = ?
    ");
    
    $stmt->execute([$_GET['id']]);
    $productCount = $stmt->fetchColumn();

    $category['product_count'] = $productCount;

    echo json_encode([
        'success' => true,
        'data' => $category
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 