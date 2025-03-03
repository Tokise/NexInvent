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

    if (!hasPermission('manage_products')) {
        throw new Exception('You do not have permission to manage categories');
    }

    // Validate required fields
    if (!isset($_POST['name']) || empty(trim($_POST['name']))) {
        throw new Exception('Category name is required');
    }

    // Check if category name already exists
    $sql = "SELECT COUNT(*) FROM categories WHERE name = ?";
    if (fetchValue($sql, [trim($_POST['name'])]) > 0) {
        throw new Exception('A category with this name already exists');
    }

    // Insert category
    $category_data = [
        'name' => trim($_POST['name']),
        'description' => $_POST['description'] ?? '',
        'created_by' => $_SESSION['user_id'],
        'created_at' => date('Y-m-d H:i:s')
    ];

    $category_id = insert('categories', $category_data);

    echo json_encode([
        'success' => true,
        'message' => 'Category saved successfully',
        'category_id' => $category_id
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 